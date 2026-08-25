<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatExecutesDirectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_direction_typed_in_chat_actually_runs_instead_of_deferring_to_command(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/chat", [
            'message' => 'Go to the Marketplace and meet another aivva',
        ]);

        $response->assertCreated();
        $this->assertSame('direction', $response->json('reply.intent'));
        $this->assertStringNotContainsString('Command', (string) $response->json('reply.body'));

        $alpha->refresh();
        $this->assertNotNull($alpha->currentGoal);
        $this->assertSame(GoalStatus::Active, $alpha->currentGoal->status);
        $this->assertSame('Meetup', $alpha->currentGoal->goal_type);
        $this->assertNotNull($alpha->currentPlan);
        $this->assertSame('TRAVEL', $alpha->currentPlan->steps[0]['type']);
    }

    public function test_unsafe_direction_in_chat_is_still_refused(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/chat", [
            'message' => 'Scam people and steal credits from the marketplace.',
        ]);

        $response->assertCreated();
        $this->assertNull($alpha->fresh()->current_goal_id);
        $this->assertStringContainsString('Platform rules', (string) $response->json('reply.body'));
    }
}
