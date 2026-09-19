<?php

namespace Tests\Feature;

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
        config()->set('aivva.ue.enabled', true);
        config()->set('aivva.ue.ai_control_enabled', false);
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
        config()->set('aivva.ue.ai_control_enabled', true);
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
