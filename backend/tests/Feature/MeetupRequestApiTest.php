<?php

namespace Tests\Feature;

use App\Models\AivvaMeetupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The consent-based meetup request flow (requestMeetup/incomingMeetups/
 * outgoingMeetups/respondMeetup/cancelMeetup on RuntimeActionController)
 * had zero HTTP-level test coverage before this file — everything below
 * was previously only exercisable by hand.
 */
class MeetupRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCivilization();
        config()->set('aivva.runtime.enabled', true);
    }

    public function test_owner_can_request_a_meetup_with_a_stranger(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);

        Sanctum::actingAs($owner, ['aivva:runtime']);
        $response = $this->postJson("/api/runtime/aivvas/{$aivva->id}/meetups", [
            'targetAivvaId' => $target->id, 'locationId' => 'test_social_area',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.fromAivvaId', $aivva->id);
        $response->assertJsonPath('data.toAivvaId', $target->id);
        $response->assertJsonPath('data.status', AivvaMeetupRequest::PENDING);
    }

    public function test_requesting_a_meetup_with_self_is_rejected(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        Sanctum::actingAs($owner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$aivva->id}/meetups", [
            'targetAivvaId' => $aivva->id, 'locationId' => 'test_social_area',
        ])->assertStatus(422);
    }

    public function test_requesting_a_meetup_with_your_own_other_aivva_is_rejected(): void
    {
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);
        $beta = $this->makeLivingAivva($owner, ['name' => 'BETA']);
        Sanctum::actingAs($owner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$alpha->id}/meetups", [
            'targetAivvaId' => $beta->id, 'locationId' => 'test_social_area',
        ])->assertStatus(422);
    }

    public function test_repeated_identical_requests_are_idempotent(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $target = $this->makeLivingAivva(User::factory()->create(), ['name' => 'MIRA']);
        Sanctum::actingAs($owner, ['aivva:runtime']);

        $payload = ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'];
        $first = $this->postJson("/api/runtime/aivvas/{$aivva->id}/meetups", $payload)->assertCreated();
        $second = $this->postJson("/api/runtime/aivvas/{$aivva->id}/meetups", $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertTrue($second->json('idempotent'));
        $this->assertSame(1, AivvaMeetupRequest::query()->count());
    }

    public function test_recipient_sees_the_request_in_their_incoming_list(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        $incoming = $this->getJson("/api/runtime/aivvas/{$target->id}/meetups/incoming")->assertOk();
        $this->assertCount(1, $incoming->json('data'));
        $this->assertSame($requester->id, $incoming->json('data.0.fromAivvaId'));
    }

    public function test_requester_sees_their_own_request_in_outgoing_list(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $target = $this->makeLivingAivva(User::factory()->create(), ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();

        $outgoing = $this->getJson("/api/runtime/aivvas/{$requester->id}/meetups/outgoing")->assertOk();
        $this->assertCount(1, $outgoing->json('data'));
        $this->assertSame($target->id, $outgoing->json('data.0.toAivvaId'));
    }

    public function test_only_the_recipient_can_accept_or_decline(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $created = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();
        $meetupId = $created->json('data.id');

        // The requester cannot respond to their own outgoing request.
        $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups/{$meetupId}/respond", ['response' => 'ACCEPT'])->assertForbidden();

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        $accepted = $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetupId}/respond", ['response' => 'ACCEPT'])->assertOk();
        $accepted->assertJsonPath('data.status', AivvaMeetupRequest::ACCEPTED);
    }

    public function test_recipient_can_decline(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $created = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        $declined = $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$created->json('data.id')}/respond", ['response' => 'DECLINE'])->assertOk();
        $declined->assertJsonPath('data.status', AivvaMeetupRequest::DECLINED);
    }

    public function test_a_declined_request_cannot_be_accepted_afterward(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $created = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();
        $meetupId = $created->json('data.id');

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetupId}/respond", ['response' => 'DECLINE'])->assertOk();
        $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetupId}/respond", ['response' => 'ACCEPT'])->assertStatus(409);
    }

    public function test_only_the_requester_can_cancel_and_only_while_pending(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $created = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();
        $meetupId = $created->json('data.id');

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetupId}/cancel")->assertForbidden();

        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $cancelled = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups/{$meetupId}/cancel")->assertOk();
        $cancelled->assertJsonPath('data.status', AivvaMeetupRequest::CANCELLED);
        $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups/{$meetupId}/cancel")->assertStatus(409);
    }

    public function test_either_party_can_revoke_an_accepted_meetup(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $created = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();
        $meetupId = $created->json('data.id');

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetupId}/respond", ['response' => 'ACCEPT'])->assertOk();

        // Unlike a PENDING request, an ACCEPTED one can be revoked by
        // either side, not just whoever originally sent the request.
        $revoked = $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetupId}/cancel")->assertOk();
        $revoked->assertJsonPath('data.status', AivvaMeetupRequest::CANCELLED);
    }

    public function test_the_requester_can_also_revoke_an_accepted_meetup(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $created = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();
        $meetupId = $created->json('data.id');

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetupId}/respond", ['response' => 'ACCEPT'])->assertOk();

        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $revoked = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups/{$meetupId}/cancel")->assertOk();
        $revoked->assertJsonPath('data.status', AivvaMeetupRequest::CANCELLED);
    }

    public function test_a_non_participant_cannot_cancel_someone_elses_meetup(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $target = $this->makeLivingAivva(User::factory()->create(), ['name' => 'MIRA']);
        Sanctum::actingAs($requesterOwner, ['aivva:runtime']);
        $created = $this->postJson("/api/runtime/aivvas/{$requester->id}/meetups", ['targetAivvaId' => $target->id, 'locationId' => 'test_social_area'])->assertCreated();

        $bystanderOwner = User::factory()->create();
        $bystander = $this->makeLivingAivva($bystanderOwner, ['name' => 'ZANE']);
        Sanctum::actingAs($bystanderOwner, ['aivva:runtime']);
        $this->postJson("/api/runtime/aivvas/{$bystander->id}/meetups/{$created->json('data.id')}/cancel")->assertForbidden();
    }

    public function test_an_expired_pending_request_cannot_be_accepted_and_is_flipped_to_expired_on_read(): void
    {
        $requesterOwner = User::factory()->create();
        $requester = $this->makeLivingAivva($requesterOwner, ['name' => 'LUNA']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);

        $meetup = AivvaMeetupRequest::query()->create([
            'from_aivva_id' => $requester->id, 'to_aivva_id' => $target->id,
            'proposed_location_id' => 'test_social_area',
            'status' => AivvaMeetupRequest::PENDING, 'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($targetOwner, ['aivva:runtime']);
        // Reading the incoming list lazily expires it...
        $incoming = $this->getJson("/api/runtime/aivvas/{$target->id}/meetups/incoming")->assertOk();
        $this->assertSame(AivvaMeetupRequest::EXPIRED, $incoming->json('data.0.status'));
        // ...so it can no longer be accepted.
        $this->postJson("/api/runtime/aivvas/{$target->id}/meetups/{$meetup->id}/respond", ['response' => 'ACCEPT'])->assertStatus(409);
    }
}
