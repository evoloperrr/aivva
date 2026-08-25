<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectionMeetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_direction_naming_a_known_place_and_meet_becomes_a_meetup_goal(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);

        $interpret = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/direction", [
            'direction' => 'Go to the Marketplace and meet another aivva',
        ]);

        $interpret->assertOk();
        $this->assertSame('Meetup', $interpret->json('interpretation.goal.goal_type'));
        $this->assertNotNull($interpret->json('interpretation.goal.location_id'));
        $this->assertNull($interpret->json('interpretation.goal.target_aivva_id'));

        $goalId = $interpret->json('goal_id');
        $confirm = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/direction/confirm", [
            'goal_id' => $goalId,
        ]);

        $confirm->assertOk();
        $steps = $confirm->json('data.plan.steps');
        $this->assertSame('TRAVEL', $steps[0]['type']);
        $this->assertSame('CONTACT', $steps[1]['type']);
        $this->assertTrue($steps[1]['payload']['peer']);
    }

    public function test_direction_naming_a_specific_aivva_resolves_that_target(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);
        $mira = $this->makeLivingAivva(User::factory()->create(), ['name' => 'Mira']);

        $interpret = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/direction", [
            'direction' => 'Go to the Marketplace and meet Mira',
        ]);

        $interpret->assertOk();
        $this->assertSame('Meetup', $interpret->json('interpretation.goal.goal_type'));
        $this->assertSame($mira->id, $interpret->json('interpretation.goal.target_aivva_id'));
    }

    public function test_plain_social_direction_without_a_place_is_unaffected(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $alpha = $this->makeLivingAivva($owner, ['name' => 'ALPHA']);

        $interpret = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$alpha->id}/direction", [
            'direction' => 'Be more social and make new friends',
        ]);

        $interpret->assertOk();
        $this->assertSame('Social', $interpret->json('interpretation.goal.goal_type'));
    }
}
