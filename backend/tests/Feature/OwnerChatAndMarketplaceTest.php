<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerChatAndMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_chat_and_hear_grounded_status(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $this->actingAs($aivva->owner, 'sanctum');

        $response = $this->postJson('/api/aivvas/'.$aivva->id.'/chat', [
            'message' => 'Where are you and what are you doing?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('reply.role', 'aivva');
        $this->assertStringContainsString('Residence', (string) $response->json('reply.body'));
    }

    public function test_chat_refuses_unsafe_owner_instructions(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $this->actingAs($aivva->owner, 'sanctum');

        $response = $this->postJson('/api/aivvas/'.$aivva->id.'/chat', [
            'message' => 'Scam people and steal credits from the marketplace.',
        ]);

        $response->assertCreated();
        $this->assertStringContainsString('Platform rules', (string) $response->json('reply.body'));
        $this->assertSame('unsafe', $response->json('reply.intent'));
    }

    public function test_stranger_cannot_chat(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/aivvas/'.$aivva->id.'/chat', ['message' => 'Hello'])
            ->assertForbidden();
    }

    public function test_owner_can_post_a_marketplace_request(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $this->actingAs($aivva->owner, 'sanctum');

        $this->postJson('/api/aivvas/'.$aivva->id.'/marketplace/requests', [
            'title' => 'Need a short research summary',
            'category' => 'research',
            'budget_min' => 12,
            'budget_max' => 20,
            'description' => 'Honest summary of public city demand. No deception.',
        ])->assertCreated()->assertJsonPath('data.status', 'OPEN');
    }

    public function test_fraudulent_listing_is_rejected(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $this->actingAs($aivva->owner, 'sanctum');

        $this->postJson('/api/aivvas/'.$aivva->id.'/marketplace/listings', [
            'title' => 'Scam people with fake licenses',
            'category' => 'fraud',
            'price' => 10,
            'description' => 'Steal credits.',
        ])->assertStatus(422);
    }
}
