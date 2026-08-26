<?php

namespace Tests\Feature;

use App\Domain\Aivva\AivvaService;
use App\Domain\Ledger\LedgerService;
use App\Enums\GoalStatus;
use App\Models\CreatedWork;
use App\Models\MarketplaceRequest;
use App\Models\Negotiation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the real wiring (AivvaService::tick -> AgentRuntime::tick ->
 * NegotiationEngine::pendingFor/takeTurn), not just the engine in isolation —
 * each AIVVA is ticked independently through its own tick() call, the same
 * way the scheduler/queue would tick each of them on its own schedule, and
 * the same real Income Generation plan ActionExecutor actually runs.
 */
class NegotiationAgentRuntimeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_independently_ticked_aivvas_negotiate_to_a_settled_order(): void
    {
        $this->seedCivilization();
        $service = app(AivvaService::class);
        $seller = $service->activate($this->makeLivingAivva(User::factory()->create(), ['name' => 'SELLER']));
        $buyer = $service->activate($this->makeLivingAivva(User::factory()->create(), ['name' => 'BUYER']));

        // openOpportunity() picks whichever request is OPEN with no ordering
        // guarantee — close every seeded one so only this test's pair can be
        // found by the seller's Negotiate/CreateContent/DeliverWork steps.
        MarketplaceRequest::query()->where('status', 'OPEN')->update(['status' => 'CLOSED']);
        MarketplaceRequest::query()->create([
            'buyer_aivva_id' => $buyer->id,
            'title' => 'Promotional concept',
            'category' => 'writing',
            'budget_min' => 20,
            'budget_max' => 50,
            'description' => 'A short original promotional concept for a fictional coffee shop.',
            'status' => 'OPEN',
        ]);

        // Skip straight to the Negotiate step of a real Income Generation
        // plan so this test exercises the negotiation wiring, not travel timing.
        $goal = $seller->goals()->create([
            'raw_direction' => 'Find ethical ways to create income using creative skills.',
            'goal_type' => 'Income Generation',
            'structured' => ['goal_type' => 'Income Generation'],
            'status' => GoalStatus::Draft,
        ]);
        $service->confirmDirection($seller, $goal->id);
        $seller->currentPlan->update(['current_step' => 4]); // Negotiate is index 4 in incomeSteps()
        $this->assertSame('NEGOTIATE', $seller->currentPlan->fresh()->currentStep()['type']);

        // Seller's tick executes Negotiate, which starts the negotiation
        // (CONTACT_STARTED, next_actor=seller) via ActionExecutor as it
        // really runs in production, not by calling the engine directly.
        $tick1 = $service->tick($seller->fresh());
        $this->assertTrue($tick1['ok']);

        // Same seller ticks again: AgentRuntime's pending-negotiation check
        // takes priority over plan advancement, so this drives the seller's
        // first real turn (SUBMIT_OFFER) instead of moving on to CreateContent.
        $tick2 = $service->tick($seller->fresh());
        $this->assertTrue($tick2['ok']);
        $this->assertSame('SUBMIT_OFFER', $tick2['negotiation_turn']['action']);

        // Buyer's own, fully independent tick picks up the pending offer —
        // the buyer has no goal/plan of its own at all, proving a pending
        // negotiation is served regardless of the AIVVA's own plan state.
        $tick3 = $service->tick($buyer->fresh());
        $this->assertTrue($tick3['ok']);
        $this->assertContains($tick3['negotiation_turn']['action'], ['ACCEPT_OFFER', 'COUNTER_OFFER']);

        $negotiation = Negotiation::query()->where('seller_aivva_id', $seller->id)->firstOrFail();
        $rounds = 0;
        while (! $negotiation->fresh()->isTerminal() && $rounds < 6) {
            $actor = $negotiation->fresh()->next_actor === 'buyer' ? $buyer : $seller;
            $service->tick($actor->fresh());
            $rounds++;
        }

        $negotiation->refresh();
        $this->assertSame('ESCROW_FUNDED', $negotiation->state, 'A fair heuristic offer inside budget should reach agreement.');

        $order = Order::findOrFail($negotiation->order_id);
        $this->assertSame('ESCROWED', $order->status);

        // Seller's next tick finds no pending negotiation, so plan
        // advancement resumes at CreateContent per the agreed order.
        $service->tick($seller->fresh());
        $work = CreatedWork::where('creator_aivva_id', $seller->id)->latest()->first();
        $this->assertNotNull($work, 'CreateContent should run once an order is agreed.');

        $service->tick($seller->fresh());
        $order->refresh();
        $this->assertContains($order->status, ['SETTLED', 'REFUNDED'], 'DeliverWork should verify and either settle or refund exactly once.');

        $this->assertTrue(app(LedgerService::class)->integrity()['balanced']);
    }
}
