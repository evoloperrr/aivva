<?php

namespace Tests\Feature;

use App\Models\AivvaMeetupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_send_two_aivvas_to_the_same_custom_spot(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);
        $beta = $this->makeLivingAivva($owner, ['name' => 'BETA']);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/meetup", [
            'target_aivva_id' => $beta->id,
            'name' => '8th Street Corner',
            'x' => 500,
            'y' => 320,
        ]);

        $response->assertCreated();
        $alphaGoal = $response->json('data.goal');
        $betaGoal = $response->json('target.goal');

        $this->assertSame('Meetup', $alphaGoal['goal_type']);
        $this->assertSame('Meetup', $betaGoal['goal_type']);
        $this->assertSame($alphaGoal['structured']['location_id'], $betaGoal['structured']['location_id']);
        $this->assertSame($beta->id, $alphaGoal['structured']['target_aivva_id']);
        $this->assertSame($alpha->id, $betaGoal['structured']['target_aivva_id']);

        $alphaSteps = $response->json('data.plan.steps');
        $this->assertSame('TRAVEL', $alphaSteps[0]['type']);
        $this->assertSame('CONTACT', $alphaSteps[1]['type']);
        $this->assertSame($beta->id, $alphaSteps[1]['payload']['target_aivva_id']);
    }

    public function test_cannot_target_itself(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);

        $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/meetup", [
            'target_aivva_id' => $alpha->id,
            'name' => 'Nowhere',
            'x' => 100,
            'y' => 100,
        ])->assertStatus(422);
    }

    public function test_stranger_cannot_set_a_meetup_for_another_owners_aivva(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);
        $beta = $this->makeLivingAivva($owner, ['name' => 'BETA']);

        $this->actingAs($stranger, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/meetup", [
            'target_aivva_id' => $beta->id,
            'name' => 'Nowhere',
            'x' => 100,
            'y' => 100,
        ])->assertForbidden();
    }

    /**
     * Security fix, Phase 5B: this endpoint used to forcibly create a
     * "Meetup" goal on the TARGET's own AIVVA with zero consent from the
     * target's owner. It must now require the same ACCEPTED, unexpired
     * AivvaMeetupRequest the consent-request flow produces before it will
     * touch a cross-owner AIVVA at all.
     */
    public function test_cross_owner_meetup_is_rejected_without_an_accepted_meetup_request(): void
    {
        $this->seedCivilization();
        $initiatorOwner = User::factory()->create();
        $targetOwner = User::factory()->create();
        $alpha = $this->makeLivingAivva($initiatorOwner, ['name' => 'ALPHA']);
        $mira = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);

        $this->actingAs($initiatorOwner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/meetup", [
            'target_aivva_id' => $mira->id,
            'name' => '8th Street Corner',
            'x' => 500,
            'y' => 320,
        ])->assertForbidden();

        $this->assertSame(0, $mira->fresh()->goals()->count());
    }

    public function test_cross_owner_meetup_succeeds_once_the_target_has_accepted_a_meetup_request(): void
    {
        $this->seedCivilization();
        $initiatorOwner = User::factory()->create();
        $targetOwner = User::factory()->create();
        $alpha = $this->makeLivingAivva($initiatorOwner, ['name' => 'ALPHA']);
        $mira = $this->makeLivingAivva($targetOwner, ['name' => 'MIRA']);

        AivvaMeetupRequest::query()->create([
            'from_aivva_id' => $alpha->id, 'to_aivva_id' => $mira->id,
            'proposed_location_id' => 'test_social_area',
            'status' => AivvaMeetupRequest::ACCEPTED, 'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($initiatorOwner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/meetup", [
            'target_aivva_id' => $mira->id,
            'name' => '8th Street Corner',
            'x' => 500,
            'y' => 320,
        ]);

        $response->assertCreated();
        $this->assertSame('Meetup', $response->json('target.goal.goal_type'));
    }
}
