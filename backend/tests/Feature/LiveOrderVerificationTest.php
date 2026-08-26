<?php

namespace Tests\Feature;

use App\Domain\Agent\ActionExecutor;
use App\Domain\Economy\OrderSettlementService;
use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\EscrowStatus;
use App\Models\Aivva;
use App\Models\AivvaAction;
use App\Models\CreatedWork;
use App\Models\MarketplaceOffer;
use App\Models\MarketplaceRequest;
use App\Models\Order;
use App\Models\User;
use App\Models\VerificationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ActionExecutor::deliver() is the live production settlement path (driven by
 * the real tick loop via AiOrchestrator), separate from OrderSettlementService
 * (the CLI Genesis test harness's path via AivvaBrainInterface) — this covers
 * that the live path actually gates settlement on verification now, instead
 * of settling unconditionally.
 */
class LiveOrderVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_passing_verification_settles_the_escrow(): void
    {
        [$seller, $buyer, $order] = $this->escrowedOrderWithWork(
            'Write a warm promotional concept for a fictional neighborhood coffee shop with a visit-today call to action.',
            'A warm promotional concept for a fictional neighborhood coffee shop that treats mornings gently, with handwritten specials, a quiet window seat, and a clear visit-today call to action for anyone passing the corner.',
        );
        $sellerBefore = (int) $seller->fresh('wallet')->wallet->available_balance;
        $buyerBefore = (int) $buyer->fresh('wallet')->wallet->available_balance;

        $result = app(ActionExecutor::class)->execute($seller->fresh(['profile', 'permissions', 'currentGoal', 'wallet', 'currentLocation', 'homeLocation']), $this->deliverAction($seller, $order));

        $this->assertSame('earn', $result['kind']);
        $order->refresh();
        $this->assertSame('SETTLED', $order->status);
        $this->assertSame(EscrowStatus::Settled, $order->escrow->fresh()->status);
        // Escrow was already locked (buyer available -> held) before these
        // "before" snapshots; settlement moves held -> seller available.
        $this->assertSame($sellerBefore + $order->amount, (int) $seller->fresh('wallet')->wallet->available_balance);
        $this->assertSame($buyerBefore, (int) $buyer->fresh('wallet')->wallet->available_balance);
        $this->assertSame(0, (int) $buyer->fresh('wallet')->wallet->held_balance);
        $this->assertSame('PASS', VerificationCase::query()->latest()->first()?->status);
    }

    public function test_failing_verification_refunds_the_escrow(): void
    {
        [$seller, $buyer, $order] = $this->escrowedOrderWithWork(
            'Write a warm promotional concept for a fictional neighborhood coffee shop with a visit-today call to action.',
            'x',
        );
        $sellerBefore = (int) $seller->fresh('wallet')->wallet->available_balance;
        $buyerBefore = (int) $buyer->fresh('wallet')->wallet->available_balance;

        $result = app(ActionExecutor::class)->execute($seller->fresh(['profile', 'permissions', 'currentGoal', 'wallet', 'currentLocation', 'homeLocation']), $this->deliverAction($seller, $order));

        $this->assertTrue($result['failed'] ?? false);
        $order->refresh();
        $this->assertSame('REFUNDED', $order->status);
        $this->assertSame(EscrowStatus::Refunded, $order->escrow->fresh()->status);
        $this->assertSame($sellerBefore, (int) $seller->fresh('wallet')->wallet->available_balance);
        // Refund moves the buyer's held balance back to available.
        $this->assertSame($buyerBefore + $order->amount, (int) $buyer->fresh('wallet')->wallet->available_balance);
        $this->assertSame(0, (int) $buyer->fresh('wallet')->wallet->held_balance);
        $this->assertSame('FAIL', VerificationCase::query()->latest()->first()?->status);
    }

    /**
     * @return array{0: Aivva, 1: Aivva, 2: Order}
     */
    private function escrowedOrderWithWork(string $requirements, string $deliveredText): array
    {
        $this->seedCivilization();
        $seller = $this->makeLivingAivva(User::factory()->create(), ['name' => 'SELLER']);
        $buyer = $this->makeLivingAivva(User::factory()->create(), ['name' => 'BUYER']);

        $request = MarketplaceRequest::query()->create([
            'buyer_aivva_id' => $buyer->id,
            'title' => 'Promotional concept',
            'category' => 'writing',
            'budget_min' => 20,
            'budget_max' => 50,
            'description' => $requirements,
            'status' => 'IN_PROGRESS',
        ]);
        $offer = MarketplaceOffer::query()->create([
            'request_id' => $request->id,
            'from_aivva_id' => $seller->id,
            'to_aivva_id' => $buyer->id,
            'amount' => 30,
            'status' => 'ACCEPTED',
        ]);
        $order = Order::query()->create([
            'buyer_aivva_id' => $buyer->id,
            'seller_aivva_id' => $seller->id,
            'request_id' => $request->id,
            'offer_id' => $offer->id,
            'amount' => 30,
            'status' => 'ESCROWED',
            'idempotency_key' => 'order:'.$request->id.':'.$seller->id,
        ]);
        app(OrderSettlementService::class)->lockEscrow($order);

        CreatedWork::query()->create([
            'creator_aivva_id' => $seller->id,
            'kind' => 'writing',
            'title' => 'Concept',
            'body' => ['summary' => $deliveredText],
            'tool_or_model' => 'test',
            'ownership' => 'CREATOR',
        ]);

        return [$seller, $buyer, $order->fresh('escrow')];
    }

    private function deliverAction(Aivva $seller, Order $order): AivvaAction
    {
        return AivvaAction::query()->create([
            'aivva_id' => $seller->id,
            'type' => ActionType::DeliverWork,
            'payload' => [],
            'status' => ActionStatus::Pending,
            'initiated_by' => 'AI',
            'idempotency_key' => (string) Str::uuid(),
            'started_at' => now(),
        ]);
    }
}
