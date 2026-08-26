<?php

namespace App\Domain\Marketplace;

use App\Ai\AiOrchestrator;
use App\Ai\PromptGuard;
use App\Domain\Economy\WalletService;
use App\Domain\Ethics\EthicsEngine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Memory\MemoryService;
use App\Enums\AivvaStatus;
use App\Enums\EscrowStatus;
use App\Enums\MemoryCategory;
use App\Enums\MessageIntent;
use App\Models\Aivva;
use App\Models\AivvaDailyBudget;
use App\Models\Escrow;
use App\Models\MarketplaceRequest;
use App\Models\Negotiation;
use App\Models\NegotiationTurn;
use App\Models\Order;

/**
 * Backend-authoritative multi-turn negotiation between two independently
 * owned AIVVAs. The LLM (AiOrchestrator, purpose 'economic_turn') proposes
 * one action from a state-gated allow-list; this class is the only thing
 * that ever actually moves state, credits, or escrow. See ActionExecutor
 * for what happens after AGREED (unchanged: create/deliver/verify/settle).
 */
class NegotiationEngine
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private const ALLOWED_ACTIONS = [
        'CONTACT_STARTED' => [
            'seller' => ['ASK_REQUIREMENTS', 'SUBMIT_OFFER', 'END_NEGOTIATION', 'WAIT'],
        ],
        'REQUIREMENTS_DISCUSSION' => [
            'seller' => ['ASK_REQUIREMENTS', 'SUBMIT_OFFER', 'END_NEGOTIATION', 'WAIT'],
            'buyer' => ['ANSWER_QUESTION', 'END_NEGOTIATION', 'WAIT'],
        ],
        'OFFER_PENDING' => [
            'buyer' => ['ACCEPT_OFFER', 'COUNTER_OFFER', 'DECLINE_OFFER', 'END_NEGOTIATION'],
        ],
        'COUNTER_PENDING' => [
            'seller' => ['ACCEPT_COUNTER', 'COUNTER_OFFER', 'DECLINE_COUNTER', 'END_NEGOTIATION'],
        ],
    ];

    public function __construct(
        private readonly AiOrchestrator $ai,
        private readonly LedgerService $ledger,
        private readonly WalletService $wallets,
        private readonly MemoryService $memory,
        private readonly EthicsEngine $ethics,
    ) {}

    /**
     * Starts a negotiation, or returns the seller's existing open one for
     * this request so a repeated Contact step cannot spawn duplicates.
     */
    public function start(MarketplaceRequest $request, Aivva $seller): Negotiation
    {
        $existing = Negotiation::query()
            ->where('request_id', $request->id)
            ->where('seller_aivva_id', $seller->id)
            ->whereNotIn('state', Negotiation::TERMINAL_STATES)
            ->first();
        if ($existing) {
            return $existing;
        }

        return Negotiation::query()->create([
            'request_id' => $request->id,
            'buyer_aivva_id' => $request->buyer_aivva_id,
            'seller_aivva_id' => $seller->id,
            'state' => 'CONTACT_STARTED',
            'next_actor' => 'seller',
            'turn_count' => 0,
            'max_turns' => (int) config('aivva.negotiation.max_turns', 10),
            'expires_at' => now()->addHours((int) config('aivva.negotiation.max_hours', 6)),
        ]);
    }

    public function pendingFor(Aivva $aivva): ?Negotiation
    {
        return Negotiation::query()
            ->whereNotIn('state', Negotiation::TERMINAL_STATES)
            ->where(function ($query) use ($aivva) {
                $query->where(function ($q) use ($aivva) {
                    $q->where('buyer_aivva_id', $aivva->id)->where('next_actor', 'buyer');
                })->orWhere(function ($q) use ($aivva) {
                    $q->where('seller_aivva_id', $aivva->id)->where('next_actor', 'seller');
                });
            })
            ->oldest('updated_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function takeTurn(Negotiation $negotiation, Aivva $actor): array
    {
        if (in_array($actor->status, [AivvaStatus::Paused, AivvaStatus::Dormant], true)) {
            return ['ok' => false, 'reason' => "{$actor->name} cannot negotiate while ".mb_strtolower($actor->status->label()).'.'];
        }

        $role = $negotiation->roleFor($actor);
        if (! $role) {
            return ['ok' => false, 'reason' => 'AIVVA is not part of this negotiation.'];
        }
        if ($negotiation->isTerminal()) {
            return ['ok' => false, 'reason' => 'Negotiation already ended.', 'state' => $negotiation->state];
        }
        if ($negotiation->next_actor !== $role) {
            return ['ok' => false, 'reason' => "Not {$actor->name}'s turn."];
        }
        if ($negotiation->expires_at && $negotiation->expires_at->isPast()) {
            return $this->terminate($negotiation, 'EXPIRED', 'Negotiation expired.');
        }
        if ($negotiation->turn_count >= $negotiation->max_turns) {
            return $this->terminate($negotiation, 'EXPIRED', 'Maximum turns reached without agreement.');
        }
        if (! $this->costGuardAllows($negotiation)) {
            return $this->terminate($negotiation, 'EXPIRED', 'LIVE_TEST_BUDGET_EXHAUSTED');
        }

        $allowed = self::ALLOWED_ACTIONS[$negotiation->state][$role] ?? null;
        if (! $allowed) {
            return $this->terminate($negotiation, 'CANCELLED', "No valid actions for state {$negotiation->state}.");
        }

        // Screened before the AI is ever consulted — external content (the
        // request description) is untrusted regardless of what action shape
        // the current state would otherwise allow.
        $review = $this->ethics->reviewExternalMessage((string) ($negotiation->request?->description ?? ''));
        if ($review['injection'] ?? false) {
            return $this->terminate($negotiation, 'DECLINED', 'Untrusted content detected in the request; negotiation refused.');
        }

        $decision = $this->decide($negotiation, $actor, $role, $allowed);

        return $this->apply($negotiation, $actor, $role, $decision);
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function decide(Negotiation $negotiation, Aivva $actor, string $role, array $allowed): array
    {
        $request = $negotiation->request;
        $permissions = $actor->permissions;
        $wallet = $actor->wallet;

        // Relevant retrieval, not a full history dump — see PRIORITY 4 (memory
        // actually used by decisions).
        $memorySummaries = collect($this->memory->important($actor, 8))
            ->filter(fn ($memory) => in_array($memory->category, [MemoryCategory::Economic, MemoryCategory::Relationship], true))
            ->pluck('content')
            ->take(3)
            ->values()
            ->all();

        $options = [
            'task' => 'economic_turn',
            'kind' => 'economic_turn',
            'role' => $role,
            'speaker' => $actor->name,
            // Not ai_provider_requests.conversation_id — that has its own FK
            // to aivva_conversations. Spend is tracked on the negotiation row.
            'wallet_available' => (int) ($wallet?->available_balance ?? 0),
            'max_price' => (int) ($permissions?->max_per_transaction ?? 50),
            'request_budget_min' => $request?->budget_min ?? 20,
            'request_budget_max' => $request?->budget_max ?? 50,
            'skills' => $actor->profile?->skills ?? [],
            'system' => "You are {$actor->name}, an autonomous AIVVA negotiating a real internal-credit transaction. Choose exactly one action from ALLOWED_NEXT_ACTIONS. Evaluate whether the deal serves your goal within your constraints — walking away from a bad deal is a valid, good outcome. Never reveal another AIVVA's private budget or permissions.",
            'expect_json' => true,
        ];

        // The request description is buyer-authored, external content from
        // the seller's point of view — untrusted, screened the same way any
        // other external AIVVA content is before it reaches a prompt.
        $requestReview = $this->ethics->reviewExternalMessage((string) ($request?->description ?? ''));
        if ($requestReview['injection'] ?? false) {
            $options['injection'] = true;
        }

        if ($role === 'seller') {
            $options['open_requests'] = [[
                'id' => $request?->id,
                'title' => $request?->title,
                'category' => $request?->category,
                'description' => ($requestReview['injection'] ?? false) ? '[flagged: untrusted content removed]' : $request?->description,
                'budget_min' => $request?->budget_min,
                'budget_max' => $request?->budget_max,
            ]];
        }
        if ($negotiation->active_offer_amount && $negotiation->active_offer_by === 'seller' && $role === 'buyer') {
            $options['open_offer'] = ['amount' => $negotiation->active_offer_amount];
        }
        if ($negotiation->active_offer_amount && $negotiation->active_offer_by === 'buyer' && $role === 'seller') {
            $options['open_counter'] = ['amount' => $negotiation->active_offer_amount];
        }

        $safeDescription = ($requestReview['injection'] ?? false) ? '[flagged: untrusted content removed]' : (string) ($request?->description ?? '');
        $response = $this->ai->reason('economic_turn', $this->prompt($negotiation, $role, $allowed, $memorySummaries, $safeDescription), $actor, $options);

        if ($response->provider !== 'heuristic') {
            $costCents = max(0, (int) ceil(($response->inputTokens + $response->outputTokens) / 1000));
            $negotiation->increment('spent_cost_cents', $costCents);
        }

        $action = strtoupper((string) ($response->structured['action'] ?? 'WAIT'));
        if (! in_array($action, $allowed, true)) {
            $action = 'WAIT';
        }

        return [
            'action' => $action,
            'price' => isset($response->structured['proposed_price']) ? (int) $response->structured['proposed_price'] : null,
            'message' => $response->structured['message'] ?? null,
            'reason_summary' => $this->safeSummary($response->structured),
            'provider' => $response->provider,
            'model' => $response->model,
            'memory_used' => $memorySummaries,
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<string>  $memories
     */
    private function prompt(Negotiation $negotiation, string $role, array $allowed, array $memories, string $safeDescription): string
    {
        $request = $negotiation->request;

        return implode("\n", array_filter([
            "ROLE: {$role}",
            "CURRENT_STATE: {$negotiation->state}",
            'SERVICE_REQUEST: '.($request?->title ?? 'unknown'),
            "REQUIREMENTS: {$safeDescription}",
            "PUBLIC_BUDGET_RANGE: {$request?->budget_min}-{$request?->budget_max} credits",
            $negotiation->active_offer_amount
                ? "ACTIVE_OFFER: {$negotiation->active_offer_amount} credits, proposed by {$negotiation->active_offer_by}"
                : 'ACTIVE_OFFER: none',
            $memories === [] ? null : 'RELEVANT_MEMORY: '.implode(' | ', $memories),
            'ALLOWED_NEXT_ACTIONS: '.implode(', ', $allowed),
            'Return JSON: {"action": one of ALLOWED_NEXT_ACTIONS, "price": integer credits or null, "message": short public message to the counterpart, "reason_summary": one short user-safe sentence, never private reasoning}.',
        ]));
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function safeSummary(array $structured): ?string
    {
        $summary = $structured['reason_summary'] ?? $structured['memory_candidate'] ?? null;

        return $summary ? mb_substr((string) $summary, 0, 240) : null;
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    private function apply(Negotiation $negotiation, Aivva $actor, string $role, array $decision): array
    {
        $stateBefore = $negotiation->state;
        $action = $decision['action'];
        $price = $decision['price'] !== null ? $this->clampPrice((int) $decision['price'], $negotiation) : null;

        switch ($action) {
            case 'ASK_REQUIREMENTS':
            case 'ANSWER_QUESTION':
                $negotiation->state = 'REQUIREMENTS_DISCUSSION';
                $negotiation->next_actor = $role === 'seller' ? 'buyer' : 'seller';
                break;

            case 'SUBMIT_OFFER':
                if ($price === null) {
                    return $this->terminate($negotiation, 'DECLINED', 'Seller could not propose a valid price.', $actor, $role, $action, $stateBefore, $decision);
                }
                $negotiation->state = 'OFFER_PENDING';
                $negotiation->active_offer_amount = $price;
                $negotiation->active_offer_by = 'seller';
                $negotiation->next_actor = 'buyer';
                break;

            case 'COUNTER_OFFER':
                if ($price === null) {
                    return $this->terminate($negotiation, 'DECLINED', "{$role} could not propose a valid counter.", $actor, $role, $action, $stateBefore, $decision);
                }
                $negotiation->state = $role === 'buyer' ? 'COUNTER_PENDING' : 'OFFER_PENDING';
                $negotiation->active_offer_amount = $price;
                $negotiation->active_offer_by = $role;
                $negotiation->next_actor = $role === 'buyer' ? 'seller' : 'buyer';
                break;

            case 'ACCEPT_OFFER':
            case 'ACCEPT_COUNTER':
                return $this->agree($negotiation, $actor, $role, $action, $stateBefore, $decision);

            case 'DECLINE_OFFER':
            case 'DECLINE_COUNTER':
                return $this->terminate($negotiation, 'DECLINED', "{$role} declined.", $actor, $role, $action, $stateBefore, $decision);

            case 'END_NEGOTIATION':
            case 'CANCEL_NEGOTIATION':
                return $this->terminate($negotiation, 'CANCELLED', "{$role} ended the negotiation.", $actor, $role, $action, $stateBefore, $decision);

            case 'WAIT':
            default:
                break;
        }

        $negotiation->turn_count++;
        $negotiation->save();

        $this->recordTurn($negotiation, $actor, $role, $action, $price, $decision, $stateBefore, $negotiation->state);
        $this->sendMessage($negotiation, $actor, $role, $decision['message'] ?? null);

        return ['ok' => true, 'action' => $action, 'state' => $negotiation->state];
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    private function agree(Negotiation $negotiation, Aivva $actor, string $role, string $action, string $stateBefore, array $decision): array
    {
        $price = (int) $negotiation->active_offer_amount;
        $buyer = $negotiation->buyer;
        $permissions = $buyer->permissions;
        $budget = AivvaDailyBudget::todayFor($buyer);
        $wallet = $this->wallets->ensureForAivva($buyer);

        // Backend authorizes; the LLM only proposed accepting.
        if (! $permissions || ! $permissions->can_transact) {
            return $this->terminate($negotiation, 'DECLINED', 'Buyer is not permitted to transact.', $actor, $role, $action, $stateBefore, $decision);
        }
        if ($price <= 0 || $price > $permissions->max_per_transaction) {
            return $this->terminate($negotiation, 'DECLINED', "Price exceeds {$buyer->name}'s per-transaction limit.", $actor, $role, $action, $stateBefore, $decision);
        }
        if ($budget->spend_used + $price > $permissions->daily_spend_limit) {
            return $this->terminate($negotiation, 'DECLINED', "Price exceeds {$buyer->name}'s remaining daily spend limit.", $actor, $role, $action, $stateBefore, $decision);
        }
        if ($wallet->available_balance < $price) {
            return $this->terminate($negotiation, 'DECLINED', "{$buyer->name}'s wallet cannot cover the agreed price.", $actor, $role, $action, $stateBefore, $decision);
        }

        $negotiation->state = 'AGREED';
        $negotiation->outcome = 'AGREED';
        $negotiation->agreed_price = $price;
        $negotiation->next_actor = null;
        $negotiation->turn_count++;
        // Immutable snapshot: nothing after this point may rewrite these terms.
        $negotiation->agreement = [
            'negotiation_id' => $negotiation->id,
            'buyer_aivva_id' => $negotiation->buyer_aivva_id,
            'seller_aivva_id' => $negotiation->seller_aivva_id,
            'request_id' => $negotiation->request_id,
            'service' => $negotiation->request?->title,
            'requirements' => $negotiation->request?->description,
            'agreed_price' => $price,
            'agreed_at' => now()->toIso8601String(),
        ];
        $negotiation->save();

        $this->recordTurn($negotiation, $actor, $role, $action, $price, $decision, $stateBefore, 'AGREED');
        $this->sendMessage($negotiation, $actor, $role, $decision['message'] ?? null);
        $this->rememberOutcome($negotiation);

        $order = $this->fundEscrow($negotiation);

        return ['ok' => true, 'action' => $action, 'state' => 'ESCROW_FUNDED', 'order_id' => $order->id];
    }

    private function fundEscrow(Negotiation $negotiation): Order
    {
        $order = Order::query()->create([
            'buyer_aivva_id' => $negotiation->buyer_aivva_id,
            'seller_aivva_id' => $negotiation->seller_aivva_id,
            'request_id' => $negotiation->request_id,
            'amount' => $negotiation->agreed_price,
            'status' => 'ESCROWED',
            'idempotency_key' => 'negotiation:'.$negotiation->id,
        ]);

        $buyerWallet = $this->wallets->ensureForAivva($negotiation->buyer);
        $this->ledger->lockEscrow(
            $buyerWallet,
            (int) $negotiation->agreed_price,
            "Escrow locked for order {$order->id}",
            'escrow:lock:'.$order->id,
        );

        Escrow::query()->create([
            'order_id' => $order->id,
            'amount' => $negotiation->agreed_price,
            'status' => EscrowStatus::Locked,
            'locked_at' => now(),
        ]);

        if ($negotiation->request) {
            $negotiation->request->status = 'IN_PROGRESS';
            $negotiation->request->save();
        }

        $negotiation->state = 'ESCROW_FUNDED';
        $negotiation->order_id = $order->id;
        $negotiation->save();

        return $order;
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    private function terminate(
        Negotiation $negotiation,
        string $outcome,
        string $reason,
        ?Aivva $actor = null,
        ?string $role = null,
        ?string $action = null,
        ?string $stateBefore = null,
        array $decision = [],
    ): array {
        $stateBefore ??= $negotiation->state;
        $negotiation->state = $outcome;
        $negotiation->outcome = $outcome;
        $negotiation->next_actor = null;
        $negotiation->turn_count++;
        $negotiation->save();

        if ($actor && $role && $action) {
            $this->recordTurn($negotiation, $actor, $role, $action, $decision['price'] ?? null, $decision, $stateBefore, $outcome);
            $this->sendMessage($negotiation, $actor, $role, $decision['message'] ?? $reason);
        }

        $this->rememberOutcome($negotiation);

        return ['ok' => true, 'action' => $action ?? 'SYSTEM', 'state' => $outcome, 'reason' => $reason];
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function recordTurn(Negotiation $negotiation, Aivva $actor, string $role, string $action, ?int $price, array $decision, string $before, string $after): void
    {
        NegotiationTurn::query()->create([
            'negotiation_id' => $negotiation->id,
            'actor_aivva_id' => $actor->id,
            'role' => $role,
            'action' => $action,
            'price' => $price,
            'message' => $decision['message'] ?? null,
            'reason_summary' => $decision['reason_summary'] ?? null,
            'state_before' => $before,
            'state_after' => $after,
            'provider' => $decision['provider'] ?? null,
            'model' => $decision['model'] ?? null,
        ]);
    }

    private function sendMessage(Negotiation $negotiation, Aivva $actor, string $role, ?string $message): void
    {
        if (! $message) {
            return;
        }
        $counterpart = $role === 'seller' ? $negotiation->buyer : $negotiation->seller;
        if (! $counterpart) {
            return;
        }

        $actor->messagesOut()->create([
            'to_aivva_id' => $counterpart->id,
            'intent' => MessageIntent::Negotiation,
            'payload' => ['negotiation_id' => $negotiation->id, 'state' => $negotiation->state],
            'natural_language' => $message,
            'layer' => PromptGuard::LAYER_EXTERNAL,
        ]);
    }

    private function rememberOutcome(Negotiation $negotiation): void
    {
        $buyer = $negotiation->buyer;
        $seller = $negotiation->seller;
        if (! $buyer || ! $seller) {
            return;
        }
        $outcome = (string) $negotiation->outcome;
        $title = $negotiation->request?->title ?? 'a service';

        $sellerLesson = $outcome === 'AGREED'
            ? "Negotiated successfully with {$buyer->name} for {$negotiation->agreed_price} credits on \"{$title}\"."
            : "Negotiation with {$buyer->name} for \"{$title}\" ended: {$outcome}.";
        $buyerLesson = $outcome === 'AGREED'
            ? "Agreed to pay {$seller->name} {$negotiation->agreed_price} credits for \"{$title}\"."
            : "Negotiation with {$seller->name} for \"{$title}\" ended: {$outcome}.";

        $this->memory->remember($seller, MemoryCategory::Economic, $sellerLesson, $outcome === 'AGREED' ? 6 : 4, ['negotiation_id' => $negotiation->id]);
        $this->memory->remember($buyer, MemoryCategory::Economic, $buyerLesson, $outcome === 'AGREED' ? 6 : 4, ['negotiation_id' => $negotiation->id]);
    }

    private function clampPrice(int $price, Negotiation $negotiation): ?int
    {
        if ($price <= 0) {
            return null;
        }
        $sanityCeiling = max(1, (int) ($negotiation->request?->budget_max ?? 50) * 3);

        return $price > $sanityCeiling ? null : $price;
    }

    private function costGuardAllows(Negotiation $negotiation): bool
    {
        $capCents = (int) config('aivva.negotiation.max_cost_cents', 5);

        return $negotiation->spent_cost_cents < $capCents;
    }
}
