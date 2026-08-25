<?php

namespace Tests\Feature;

use App\Domain\Agent\ActionValidator;
use App\Domain\Aivva\AivvaService;
use App\Enums\ActionType;
use App\Enums\AivvaStatus;
use App\Enums\AutonomyLevel;
use App\Enums\GoalStatus;
use App\Models\AivvaDailyBudget;
use App\Models\AivvaPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsAndLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_one_cannot_spend(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $aivva->permissions->update(['autonomy_level' => AutonomyLevel::Social]);
        $aivva->refresh()->load('permissions');

        $decision = app(ActionValidator::class)->validate($aivva, ActionType::Negotiate, [
            'amount' => 35,
            'role' => 'seller',
        ]);

        $this->assertFalse($decision['allowed']);
    }

    public function test_confirmed_direction_starts_autonomous_loop(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $service = app(AivvaService::class);
        $preview = $service->previewDirection($aivva, 'Find ethical ways to create income using creative skills.');
        $this->assertTrue($preview['interpretation']['allowed']);

        $service->confirmDirection($aivva, $preview['goal']->id);
        $aivva->refresh();
        $this->assertNotNull($aivva->current_goal_id);
        $this->assertNotNull($aivva->current_plan_id);
        $this->assertNotSame(AivvaStatus::Dormant, $aivva->status);

        $tick = $service->tick($aivva->fresh());
        $this->assertArrayHasKey('ok', $tick);
        $this->assertGreaterThan(0, $aivva->activityLogs()->count());
    }

    public function test_exhausted_token_budget_backs_off_instead_of_looping(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $service = app(AivvaService::class);
        $preview = $service->previewDirection($aivva, 'Find ethical ways to create income using creative skills.');
        $service->confirmDirection($aivva, $preview['goal']->id);

        $budget = AivvaDailyBudget::todayFor($aivva->fresh());
        $budget->update(['tokens_used' => $aivva->fresh()->permissions->daily_token_budget]);

        $before = $aivva->activityLogs()->count();
        $tick = $service->tick($aivva->fresh());
        $after = $aivva->activityLogs()->count();

        $this->assertFalse($tick['ok']);
        $this->assertSame('Daily token budget exhausted.', $tick['reason']);
        $this->assertSame($before, $after, 'An exhausted-budget tick must not create a new plan or log entry.');

        $aivva->refresh();
        $this->assertSame(AivvaStatus::Idle, $aivva->status);
        $this->assertTrue($aivva->next_scheduled_at->gt(now()->addMinutes(50)));
    }

    public function test_a_finished_plan_completes_the_goal_instead_of_repeating_forever(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        $service = app(AivvaService::class);

        $goal = $aivva->goals()->create([
            'raw_direction' => 'Look around home',
            'goal_type' => 'Visit',
            'structured' => [
                'goal_type' => 'Visit',
                'location_id' => $aivva->current_location_id,
                'meeting_name' => 'Home',
            ],
            'status' => GoalStatus::Draft,
        ]);

        $service->confirmDirection($aivva, $goal->id);

        // Same-location travel resolves in one tick, so: Travel, then Reflect
        // completes the (2-step) plan, then this third tick must finish the
        // goal instead of building yet another identical plan.
        $tick1 = $service->tick($aivva->fresh());
        $this->assertTrue($tick1['ok']);
        $tick2 = $service->tick($aivva->fresh());
        $this->assertTrue($tick2['ok']);

        $plansBefore = AivvaPlan::query()->where('aivva_id', $aivva->id)->count();
        $tick3 = $service->tick($aivva->fresh());
        $plansAfter = AivvaPlan::query()->where('aivva_id', $aivva->id)->count();

        $this->assertTrue($tick3['ok']);
        $this->assertTrue($tick3['completed_goal'] ?? false);
        $this->assertSame($plansBefore, $plansAfter, 'A finished goal must not spawn another plan.');
        $this->assertSame(GoalStatus::Completed, $goal->fresh()->status);
    }

    public function test_over_limit_transaction_is_rejected(): void
    {
        $this->seedCivilization();
        $aivva = $this->makeLivingAivva();
        app(AivvaService::class)->activate($aivva);
        $aivva->permissions->update(['max_per_transaction' => 10]);
        $aivva->refresh()->load('permissions');

        $decision = app(ActionValidator::class)->validate($aivva, ActionType::Negotiate, [
            'amount' => 35,
        ]);

        $this->assertFalse($decision['allowed']);
        $this->assertStringContainsString('per-transaction', (string) $decision['reason']);
    }
}
