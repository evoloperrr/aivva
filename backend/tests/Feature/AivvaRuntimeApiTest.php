<?php

namespace Tests\Feature;

use App\Enums\AivvaControlMode;
use App\Models\AivvaMeetupRequest;
use App\Models\User;
use App\Models\XentozIdentityLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AivvaRuntimeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCivilization();
        config()->set('aivva.runtime.enabled', true);
        config()->set('aivva.runtime.ai_control_enabled', false);
        config()->set('aivva.ue.wallet_enabled', false);
    }

    public function test_runtime_profile_uses_stable_aivva_and_xentoz_ids_without_wallet_data(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner);
        $this->link($owner, 'usr_123');
        Sanctum::actingAs($owner, ['aivva:runtime']);

        $response = $this->getJson('/api/runtime/aivvas/'.$aivva->id);
        $response->assertOk()
            ->assertJsonPath('data.id', $aivva->id)
            ->assertJsonPath('data.ownerUserId', 'usr_123')
            ->assertJsonPath('data.controlMode', 'HUMAN')
            ->assertJsonPath('data.aiPermissions.privateChat', false)
            ->assertJsonPath('data.aiPermissions.wallet', false)
            ->assertJsonMissingPath('data.wallet');
    }

    public function test_ai_twin_switch_requires_feature_flag_and_keeps_same_identity(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner);
        Sanctum::actingAs($owner, ['aivva:runtime']);

        $this->patchJson('/api/runtime/aivvas/'.$aivva->id.'/control-mode', ['controlMode' => 'AI_TWIN'])->assertStatus(503);
        config()->set('aivva.runtime.ai_control_enabled', true);
        $response = $this->patchJson('/api/runtime/aivvas/'.$aivva->id.'/control-mode', ['controlMode' => 'AI_TWIN']);
        $response->assertOk()->assertJsonPath('data.id', $aivva->id)->assertJsonPath('data.controlMode', 'AI_TWIN');
        $this->assertDatabaseHas('aivvas', ['id' => $aivva->id, 'control_mode' => 'AI_TWIN']);
    }

    public function test_runtime_scope_and_ownership_are_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner);
        Sanctum::actingAs($other, ['aivva:runtime']);
        $this->getJson('/api/runtime/aivvas/'.$aivva->id)->assertForbidden();
        Sanctum::actingAs($owner, ['aivva:read']);
        $this->getJson('/api/runtime/aivvas/'.$aivva->id)->assertForbidden();
    }

    /**
     * Owner decision, Phase 5B: the Plaza is private by default — before
     * any accepted meetup, an owner sees only their own AIVVA, never a
     * platform NPC (NOVA), and never an unrelated real AIVVA even if it
     * would otherwise be "visible on the map." This supersedes the old
     * "platform bots are always ambient scenery" test, which is no longer
     * the intended behavior once cross-owner visibility became
     * consent-gated (see AivvaRuntimeController::scene()).
     */
    public function test_scene_is_private_to_the_owner_before_any_accepted_meetup(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $strangerOwner = User::factory()->create();
        $this->makeLivingAivva($strangerOwner, ['name' => 'STRANGER']);
        $hiddenOwner = User::factory()->create();
        $this->makeLivingAivva($hiddenOwner, ['name' => 'HIDDEN', 'visible_on_map' => false]);
        Sanctum::actingAs($owner, ['aivva:runtime']);
        $response = $this->getJson('/api/runtime/aivvas/'.$aivva->id.'/scene')->assertOk();
        $names = collect($response->json('data.characters'))->pluck('displayName');
        $this->assertSame(['LUNA'], $names->values()->all());
        $this->assertFalse($names->contains('NOVA'));
        $this->assertFalse($names->contains('STRANGER'));
        $this->assertFalse($names->contains('HIDDEN'));
    }

    public function test_scene_reveals_only_the_accepted_meetup_partner(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $partnerOwner = User::factory()->create();
        $partner = $this->makeLivingAivva($partnerOwner, ['name' => 'PARTNER']);
        $unrelatedOwner = User::factory()->create();
        $this->makeLivingAivva($unrelatedOwner, ['name' => 'UNRELATED']);

        AivvaMeetupRequest::query()->create([
            'from_aivva_id' => $aivva->id, 'to_aivva_id' => $partner->id,
            'proposed_location_id' => 'test_social_area',
            'status' => AivvaMeetupRequest::ACCEPTED, 'expires_at' => now()->addMinutes(15),
        ]);

        Sanctum::actingAs($owner, ['aivva:runtime']);
        $response = $this->getJson('/api/runtime/aivvas/'.$aivva->id.'/scene')->assertOk();
        $names = collect($response->json('data.characters'))->pluck('displayName')->sort()->values();
        $this->assertSame(['LUNA', 'PARTNER'], $names->all());
        $this->assertFalse($names->contains('UNRELATED'));
    }

    public function test_scene_hides_a_partner_once_the_meetup_expires(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $partnerOwner = User::factory()->create();
        $partner = $this->makeLivingAivva($partnerOwner, ['name' => 'PARTNER']);

        AivvaMeetupRequest::query()->create([
            'from_aivva_id' => $aivva->id, 'to_aivva_id' => $partner->id,
            'proposed_location_id' => 'test_social_area',
            'status' => AivvaMeetupRequest::ACCEPTED, 'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($owner, ['aivva:runtime']);
        $response = $this->getJson('/api/runtime/aivvas/'.$aivva->id.'/scene')->assertOk();
        $names = collect($response->json('data.characters'))->pluck('displayName');
        $this->assertSame(['LUNA'], $names->values()->all());
    }

    public function test_a_human_can_request_face_target_and_interact_against_an_accepted_meetup_partner(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $partnerOwner = User::factory()->create();
        $partner = $this->makeLivingAivva($partnerOwner, ['name' => 'PARTNER']);
        AivvaMeetupRequest::query()->create([
            'from_aivva_id' => $aivva->id, 'to_aivva_id' => $partner->id,
            'proposed_location_id' => 'test_social_area',
            'status' => AivvaMeetupRequest::ACCEPTED, 'expires_at' => now()->addMinutes(15),
        ]);

        Sanctum::actingAs($owner, ['aivva:runtime']);
        $face = $this->postJson('/api/runtime/aivvas/'.$aivva->id.'/actions/request', [
            'type' => 'FACE_TARGET', 'payload' => ['targetAivvaId' => $partner->id],
        ])->assertCreated();
        $this->assertSame('FACE_TARGET', $face->json('data.type'));
        $this->assertSame($partner->id, $face->json('data.payload.targetAivvaId'));

        $this->service()->cancel(\App\Models\AivvaRuntimeAction::query()->findOrFail($face->json('data.actionId')));

        $interact = $this->postJson('/api/runtime/aivvas/'.$aivva->id.'/actions/request', [
            'type' => 'INTERACT', 'payload' => ['targetAivvaId' => $partner->id],
        ])->assertCreated();
        $this->assertSame('INTERACT', $interact->json('data.type'));
    }

    public function test_a_human_cannot_request_interact_against_a_non_consenting_aivva(): void
    {
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $stranger = $this->makeLivingAivva(User::factory()->create(), ['name' => 'STRANGER']);

        Sanctum::actingAs($owner, ['aivva:runtime']);
        $this->postJson('/api/runtime/aivvas/'.$aivva->id.'/actions/request', [
            'type' => 'INTERACT', 'payload' => ['targetAivvaId' => $stranger->id],
        ])->assertForbidden();
    }

    private function service(): \App\Domain\Runtime\RuntimeActionService
    {
        return app(\App\Domain\Runtime\RuntimeActionService::class);
    }

    public function test_resolves_a_xentoz_user_id_to_their_aivva_for_the_meetup_ui(): void
    {
        $caller = User::factory()->create();
        $this->makeLivingAivva($caller, ['name' => 'CALLER']);
        $targetOwner = User::factory()->create();
        $target = $this->makeLivingAivva($targetOwner, ['name' => 'KNZ']);
        $this->link($targetOwner, 'xentoz-knzvalle-id');

        Sanctum::actingAs($caller, ['aivva:runtime']);
        $response = $this->getJson('/api/runtime/lookup/xentoz-user/xentoz-knzvalle-id')->assertOk();
        $response->assertJsonPath('data.aivvaId', $target->id)->assertJsonPath('data.displayName', 'KNZ');
        // Only id + name — never wallet, location, or personality data.
        $this->assertSame(['aivvaId', 'displayName'], array_keys($response->json('data')));
    }

    public function test_resolving_an_unlinked_xentoz_user_id_404s(): void
    {
        $caller = User::factory()->create();
        $this->makeLivingAivva($caller);
        Sanctum::actingAs($caller, ['aivva:runtime']);
        $this->getJson('/api/runtime/lookup/xentoz-user/no-such-xentoz-user')->assertNotFound();
    }

    public function test_resolving_a_linked_user_with_no_aivva_yet_404s(): void
    {
        $caller = User::factory()->create();
        $this->makeLivingAivva($caller);
        $targetOwner = User::factory()->create(); // no AIVVA created for them
        $this->link($targetOwner, 'xentoz-no-aivva-yet');
        Sanctum::actingAs($caller, ['aivva:runtime']);
        $this->getJson('/api/runtime/lookup/xentoz-user/xentoz-no-aivva-yet')->assertNotFound();
    }

    public function test_runtime_action_routes_bind_the_owned_aivva_and_start_the_server_demo(): void
    {
        config()->set('aivva.runtime.ai_control_enabled', true);
        config()->set('aivva.runtime.plaza_demo_enabled', true);
        $owner = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner, ['name' => 'LUNA']);
        $aivva->forceFill(['control_mode' => AivvaControlMode::AiTwin])->save();
        Sanctum::actingAs($owner, ['aivva:runtime']);

        $response = $this->postJson('/api/runtime/aivvas/'.$aivva->id.'/demo/start')->assertCreated();
        $response->assertJsonPath('data.aivvaId', $aivva->id)->assertJsonPath('data.type', 'MOVE_TO');
        $this->getJson('/api/runtime/aivvas/'.$aivva->id.'/actions/active')->assertOk()->assertJsonPath('data.actionId', $response->json('data.actionId'));
    }

    private function link(User $user, string $xentozUserId): void
    {
        $link = new XentozIdentityLink();
        $link->user_id = $user->id;
        $link->xentoz_user_id = $xentozUserId;
        $link->linked_at = now();
        $link->last_asserted_at = now();
        $link->save();
    }
}
