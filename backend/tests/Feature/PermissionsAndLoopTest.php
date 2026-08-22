<?php

namespace Tests\Feature;

use App\Domain\Aivva\AivvaService;
use App\Domain\Agent\ActionValidator;
use App\Enums\ActionType;
use App\Enums\AutonomyLevel;
use App\Enums\AivvaStatus;
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
