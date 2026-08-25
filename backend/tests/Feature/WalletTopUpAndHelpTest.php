<?php

namespace Tests\Feature;

use App\Domain\Aivva\AivvaService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTopUpAndHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_top_up_their_aivvas_wallet(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $halo = $this->makeLivingAivva($owner, ['name' => 'HALO']);
        $before = (int) $halo->fresh('wallet')->wallet->available_balance;

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$halo->id}/wallet/topup", [
            'amount' => 1000,
        ]);

        $response->assertOk();
        $this->assertSame($before + 1000, (int) $halo->fresh('wallet')->wallet->available_balance);
    }

    public function test_stranger_cannot_top_up_someone_elses_aivva(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $halo = $this->makeLivingAivva($owner, ['name' => 'HALO']);

        $this->actingAs($stranger, 'sanctum')->postJson("/api/aivvas/{$halo->id}/wallet/topup", [
            'amount' => 1000,
        ])->assertForbidden();
    }

    public function test_direction_naming_amount_and_target_gives_credits(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $halo = $this->makeLivingAivva($owner, ['name' => 'HALO']);
        $cass = $this->makeLivingAivva(User::factory()->create(), ['name' => 'CASS']);

        app(AivvaService::class)->topUpWallet($halo, 1000);
        $haloBefore = (int) $halo->fresh('wallet')->wallet->available_balance;
        $cassBefore = (int) $cass->fresh('wallet')->wallet->available_balance;

        // Default permissions cap a transaction at 50 credits (max_per_transaction),
        // so this uses an amount inside that default cap.
        $interpret = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$halo->id}/direction", [
            'direction' => 'Give CASS 40 credits',
        ]);

        $interpret->assertOk();
        $this->assertSame('Help', $interpret->json('interpretation.goal.goal_type'));
        $this->assertSame($cass->id, $interpret->json('interpretation.goal.target_aivva_id'));
        $this->assertSame(40, $interpret->json('interpretation.goal.amount'));

        $goalId = $interpret->json('goal_id');
        $confirm = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$halo->id}/direction/confirm", [
            'goal_id' => $goalId,
        ]);
        $confirm->assertOk();

        $service = app(AivvaService::class);
        $tick = $service->tick($halo->fresh());
        $this->assertTrue($tick['ok']);

        $this->assertSame($haloBefore - 40, (int) $halo->fresh('wallet')->wallet->available_balance);
        $this->assertSame($cassBefore + 40, (int) $cass->fresh('wallet')->wallet->available_balance);
    }

    public function test_cannot_give_more_than_available_balance(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $halo = $this->makeLivingAivva($owner, ['name' => 'HALO']);
        $cass = $this->makeLivingAivva(User::factory()->create(), ['name' => 'CASS']);
        // Raise the per-transaction/approval caps so the only remaining
        // blocker under test is the wallet's actual available balance.
        $halo->permissions->update(['max_per_transaction' => 5000, 'require_approval_above' => 5000, 'daily_spend_limit' => 5000]);
        $haloBefore = (int) $halo->fresh('wallet')->wallet->available_balance;
        $cassBefore = (int) $cass->fresh('wallet')->wallet->available_balance;

        $interpret = $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$halo->id}/direction", [
            'direction' => 'Give CASS '.($haloBefore + 500).' credits',
        ]);
        $goalId = $interpret->json('goal_id');
        $this->actingAs($owner, 'sanctum')->postJson("/api/aivvas/{$halo->id}/direction/confirm", [
            'goal_id' => $goalId,
        ]);

        app(AivvaService::class)->tick($halo->fresh());

        $this->assertSame($haloBefore, (int) $halo->fresh('wallet')->wallet->available_balance);
        $this->assertSame($cassBefore, (int) $cass->fresh('wallet')->wallet->available_balance);
    }
}
