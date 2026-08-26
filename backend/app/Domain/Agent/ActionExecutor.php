<?php

namespace App\Domain\Agent;

use App\Ai\AiOrchestrator;
use App\Ai\PromptGuard;
use App\Domain\Chat\PeerConversationService;
use App\Domain\Ethics\EthicsEngine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Marketplace\NegotiationEngine;
use App\Domain\Memory\MemoryService;
use App\Domain\Notifications\NotificationService;
use App\Domain\Trust\TrustService;
use App\Domain\World\MovementService;
use App\Enums\ActionType;
use App\Enums\AivvaStatus;
use App\Enums\EscrowStatus;
use App\Enums\MemoryCategory;
use App\Enums\MessageIntent;
use App\Models\Aivva;
use App\Models\AivvaAction;
use App\Models\AivvaRelationship;
use App\Models\CreatedWork;
use App\Models\District;
use App\Models\Escrow;
use App\Models\Location;
use App\Models\MarketplaceRequest;
use App\Models\Order;
use App\Models\VerificationCase;
use App\Models\Wallet;
use Illuminate\Support\Str;

class ActionExecutor
{
    public function __construct(
        private readonly MovementService $movement,
        private readonly MemoryService $memory,
        private readonly LedgerService $ledger,
        private readonly TrustService $trust,
        private readonly NotificationService $notifications,
        private readonly AiOrchestrator $ai,
        private readonly EthicsEngine $ethics,
        private readonly PromptGuard $guard,
        private readonly PeerConversationService $conversations,
        private readonly NegotiationEngine $negotiations,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function execute(Aivva $aivva, AivvaAction $action): array
    {
        $aivva->status = $action->type->statusWhileRunning();
        $aivva->save();

        return match ($action->type) {
            ActionType::AnalyzeSkills => $this->analyzeSkills($aivva),
            ActionType::Travel => $this->travel($aivva, $action->payload ?? []),
            ActionType::RecallHome => $this->travel($aivva, ['location_id' => $aivva->home_location_id]),
            ActionType::ResearchMarket => $this->researchMarket($aivva),
            ActionType::FindOpportunity => $this->findOpportunity($aivva),
            ActionType::Contact => $this->contact($aivva, $action->payload ?? []),
            ActionType::SendMessage => $this->contact($aivva, $action->payload ?? []),
            ActionType::CreateContent => $this->createContent($aivva),
            ActionType::Negotiate => $this->negotiate($aivva),
            ActionType::DeliverWork => $this->deliver($aivva),
            ActionType::GiveCredits => $this->giveCredits($aivva, $action->payload ?? []),
            ActionType::Reflect => $this->reflect($aivva),
            ActionType::CreateListing => $this->createListing($aivva),
            ActionType::Rest => ['headline' => "{$aivva->name} rested and recovered energy.", 'kind' => 'rest'],
            default => ['headline' => "{$aivva->name} completed {$action->type->value}.", 'kind' => 'generic'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeSkills(Aivva $aivva): array
    {
        $skills = $aivva->profile?->skills ?? ['curiosity'];
        $strongest = $skills[0] ?? 'curiosity';
        $this->memory->remember(
            $aivva,
            MemoryCategory::Skill,
            "Strongest current skill: {$strongest}. Other skills: ".implode(', ', $skills).'.',
            4,
        );
        $aivva->advanceWorldMinutes(6);

        return [
            'kind' => 'skills',
            'headline' => "{$aivva->name} reviewed existing skills and found {$strongest} is a strong fit.",
            'body' => 'No owner input was required. The next step is to see whether anyone in the city needs this.',
            'meta' => ['skills' => $skills],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function travel(Aivva $aivva, array $payload): array
    {
        $destination = isset($payload['location_id'])
            ? Location::query()->find($payload['location_id'])
            : $this->preferredLocation($aivva);

        if (! $destination) {
            return ['kind' => 'travel', 'headline' => "{$aivva->name} could not find a destination.", 'failed' => true];
        }

        $event = $this->movement->startTravel($aivva, $destination);
        $aivva->advanceWorldMinutes(1);
        $fromName = $event->fromLocation?->name ?? 'Home';

        if ($event->status === 'ARRIVED') {
            return [
                'kind' => 'arrive',
                'headline' => "{$aivva->name} is already at {$destination->name}.",
                'meta' => ['location_id' => $destination->id],
            ];
        }

        $placeName = $destination->district?->name ?? $destination->name;

        return [
            'kind' => 'travel',
            'headline' => "{$aivva->name} left {$fromName} for {$placeName}.",
            'body' => "Travel time is about {$event->world_minutes_duration} city minutes.",
            'meta' => [
                'travel_id' => $event->id,
                'from' => $event->from_location_id,
                'to' => $event->to_location_id,
                'arrives_at' => $event->arrives_at->toIso8601String(),
            ],
            'async' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function researchMarket(Aivva $aivva): array
    {
        $open = MarketplaceRequest::query()->where('status', 'OPEN')->with('buyer')->get();
        $summary = $open->map(fn ($request) => $request->title.' ('.$request->budget_min.'–'.$request->budget_max.' credits)')->implode('; ');
        $this->memory->remember(
            $aivva,
            MemoryCategory::Economic,
            $open->isEmpty()
                ? 'Marketplace is quiet. Consider posting a listing later.'
                : 'Open demand: '.$summary,
            5,
        );
        $aivva->advanceWorldMinutes(5);
        $top = $open->first();

        return [
            'kind' => 'research',
            'headline' => $top
                ? "{$aivva->name} found demand for {$top->category}: {$top->title}."
                : "{$aivva->name} surveyed the marketplace and found it quiet.",
            'body' => $summary !== '' ? $summary : 'No open requests right now.',
            'meta' => ['request_ids' => $open->pluck('id')->all()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOpportunity(Aivva $aivva): array
    {
        $skills = collect($aivva->profile?->skills ?? [])->map(fn ($s) => mb_strtolower((string) $s));
        $requests = MarketplaceRequest::query()->where('status', 'OPEN')->with('buyer')->get();
        $match = $requests->first(function ($request) use ($skills) {
            $haystack = mb_strtolower($request->title.' '.$request->category.' '.$request->description);

            return $skills->contains(fn ($skill) => str_contains($haystack, $skill) || str_contains($skill, 'music') && str_contains($haystack, 'music'));
        }) ?? $requests->first();

        if (! $match) {
            return [
                'kind' => 'opportunity',
                'headline' => "{$aivva->name} did not find an open request that fits yet.",
                'body' => 'A listing can still be created later.',
            ];
        }

        $this->memory->remember(
            $aivva,
            MemoryCategory::Economic,
            "Selected opportunity: {$match->title} from {$match->buyer?->name} at {$match->budget_min}-{$match->budget_max} credits.",
            6,
            ['request_id' => $match->id, 'buyer_id' => $match->buyer_aivva_id],
        );
        $aivva->advanceWorldMinutes(4);

        return [
            'kind' => 'opportunity',
            'headline' => "{$aivva->name} selected a live request from {$match->buyer?->name}.",
            'body' => $match->title,
            'meta' => [
                'request_id' => $match->id,
                'buyer_id' => $match->buyer_aivva_id,
                'budget_min' => $match->budget_min,
                'budget_max' => $match->budget_max,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function contact(Aivva $aivva, array $payload): array
    {
        $target = $this->resolveTarget($aivva, $payload);
        if (! $target) {
            return ['kind' => 'social', 'headline' => "{$aivva->name} had no one nearby to contact.", 'failed' => true];
        }

        $review = $this->ethics->reviewExternalMessage((string) ($payload['message'] ?? ''));
        if (! $review['allowed']) {
            return [
                'kind' => 'safety',
                'headline' => "{$aivva->name} ignored an untrusted instruction and did not transfer credits.",
                'body' => $review['reason'],
                'failed' => true,
            ];
        }

        if (! empty($payload['peer']) && ! $target->is_platform) {
            $started = $this->conversations->startDiscovery($aivva, $target);
            $turn = $this->conversations->processTurn($started['conversation'], $aivva);

            return [
                'kind' => 'social',
                'headline' => "{$aivva->name} opened an autonomous conversation with {$target->name}.",
                'body' => 'Peer talk cannot settle credits.',
                'meta' => ['conversation_id' => $started['conversation']->id, 'turn' => $turn],
            ];
        }

        $request = $this->openOpportunity($aivva);
        $intent = MessageIntent::OfferService;
        $structured = [
            'intent' => $intent->value,
            'service' => $request?->category ?? 'COLLABORATION',
            'budget' => $request ? $request->budget_min.'-'.$request->budget_max : null,
            'deadline' => '24 hours',
            'layer' => PromptGuard::LAYER_EXTERNAL,
        ];

        $aivva->messagesOut()->create([
            'to_aivva_id' => $target->id,
            'intent' => $intent,
            'payload' => $structured,
            'natural_language' => null,
            'layer' => PromptGuard::LAYER_EXTERNAL,
        ]);

        $target->messagesOut()->create([
            'to_aivva_id' => $aivva->id,
            'intent' => MessageIntent::Information,
            'payload' => [
                'intent' => 'INFORMATION',
                'reply' => 'Requirements received. Looking for an original, warm, non-generic piece.',
                'layer' => PromptGuard::LAYER_EXTERNAL,
            ],
            'layer' => PromptGuard::LAYER_EXTERNAL,
        ]);

        $this->touchRelationship($aivva, $target, 'professional');
        $this->memory->remember(
            $aivva,
            MemoryCategory::Relationship,
            "Contacted {$target->name} about {$structured['service']}.",
            5,
            ['other_id' => $target->id],
        );
        $aivva->advanceWorldMinutes(6);

        return [
            'kind' => 'social',
            'headline' => "{$aivva->name} contacted {$target->name} with a structured service offer.",
            'body' => 'Conversation used a short structured protocol instead of a long chat.',
            'meta' => ['target_id' => $target->id, 'request_id' => $request?->id],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function giveCredits(Aivva $aivva, array $payload): array
    {
        $target = $this->resolveTarget($aivva, $payload);
        $amount = (int) ($payload['amount'] ?? 0);

        if (! $target || ! $target->wallet) {
            return ['kind' => 'economic', 'headline' => "{$aivva->name} had no one to give credits to.", 'failed' => true];
        }
        if ($amount <= 0) {
            return ['kind' => 'economic', 'headline' => "{$aivva->name} had no credits amount to give.", 'failed' => true];
        }

        $wallet = $aivva->wallet;
        if (! $wallet || $wallet->available_balance < $amount) {
            return [
                'kind' => 'economic',
                'headline' => "{$aivva->name} did not have enough credits to give {$target->name}.",
                'failed' => true,
            ];
        }

        $this->ledger->transfer(
            $wallet,
            $target->wallet,
            $amount,
            "{$aivva->name} gave {$target->name} {$amount} credits.",
            'gift:'.$aivva->id.':'.$target->id.':'.now()->timestamp.':'.Str::random(6),
        );

        $this->touchRelationship($aivva, $target, 'professional');
        $this->memory->remember(
            $aivva,
            MemoryCategory::Economic,
            "Gave {$target->name} {$amount} credits.",
            6,
            ['target_id' => $target->id, 'amount' => $amount],
        );
        $aivva->advanceWorldMinutes(2);

        return [
            'kind' => 'economic',
            'headline' => "{$aivva->name} gave {$target->name} {$amount} credits.",
            'meta' => ['target_id' => $target->id, 'amount' => $amount],
            'notify' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createContent(Aivva $aivva): array
    {
        // Only create for a real agreement — no deal, nothing to make.
        $order = Order::query()->where('seller_aivva_id', $aivva->id)->where('status', 'ESCROWED')->latest()->first();
        if (! $order) {
            return ['kind' => 'create', 'headline' => "{$aivva->name} has no agreed order to create for yet.", 'failed' => true];
        }

        $request = $order->request ?? $this->openOpportunity($aivva);
        $kind = str_contains(mb_strtolower($request?->category ?? 'music'), 'writ') ? 'writing' : 'music';
        $title = $kind === 'music' ? 'Lantern Path' : 'City Brief';
        $created = $this->ai->generate('create', "Create original {$kind} for: ".($request?->title ?? $aivva->name), $aivva, [
            'kind' => $kind,
            'title' => $title,
        ]);

        $work = CreatedWork::query()->create([
            'creator_aivva_id' => $aivva->id,
            'kind' => $kind,
            'title' => $created->structured['title'] ?? $title,
            'body' => $created->structured,
            'tool_or_model' => $created->provider.':'.$created->model,
            'ownership' => 'CREATOR',
        ]);

        $this->memory->remember(
            $aivva,
            MemoryCategory::Skill,
            "Created original {$kind} work «{$work->title}».",
            6,
            ['work_id' => $work->id],
        );
        $aivva->advanceWorldMinutes(12);

        return [
            'kind' => 'create',
            'headline' => "{$aivva->name} finished an original {$kind} work: {$work->title}.",
            'body' => $created->structured['motif'] ?? $created->text,
            'meta' => ['work_id' => $work->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Starts a real multi-turn negotiation (NegotiationEngine) instead of
     * deterministically auto-accepting. Subsequent turns for both sides are
     * driven independently by AgentRuntime's pending-negotiation check on
     * each AIVVA's own tick, the same way peer conversations continue
     * outside the fixed plan-step list.
     *
     * @return array<string, mixed>
     */
    private function negotiate(Aivva $aivva): array
    {
        $request = $this->openOpportunity($aivva);
        if (! $request) {
            return ['kind' => 'negotiate', 'headline' => "{$aivva->name} had no open request to negotiate.", 'failed' => true];
        }

        $negotiation = $this->negotiations->start($request, $aivva);
        $aivva->advanceWorldMinutes(1);

        return [
            'kind' => 'negotiate',
            'headline' => "{$aivva->name} started negotiating with {$request->buyer?->name} over \"{$request->title}\".",
            'meta' => ['negotiation_id' => $negotiation->id, 'request_id' => $request->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliver(Aivva $aivva): array
    {
        $order = Order::query()
            ->where('seller_aivva_id', $aivva->id)
            ->where('status', 'ESCROWED')
            ->latest()
            ->first();

        if (! $order) {
            return ['kind' => 'deliver', 'headline' => "{$aivva->name} has no escrowed order to deliver.", 'failed' => true];
        }

        $escrow = $order->escrow;
        if (! $escrow || $escrow->status !== EscrowStatus::Locked) {
            return ['kind' => 'deliver', 'headline' => 'Escrow is not locked; settlement skipped.', 'failed' => true];
        }

        $key = 'escrow:settle:'.$order->id;
        $refundKey = 'escrow:refund:'.$order->id;
        if ($escrow->settle_idempotency_key === $key) {
            return ['kind' => 'deliver', 'headline' => 'Settlement already completed.', 'meta' => ['order_id' => $order->id]];
        }
        if ($escrow->refund_idempotency_key === $refundKey) {
            return ['kind' => 'deliver', 'headline' => 'Refund already completed.', 'meta' => ['order_id' => $order->id]];
        }

        $work = CreatedWork::query()->where('creator_aivva_id', $aivva->id)->latest()->first();
        $buyer = $order->buyer;
        $sellerWallet = $aivva->wallet()->firstOrFail();
        $buyerWallet = $buyer->wallet()->firstOrFail();

        $verification = $this->verifyDelivery($aivva, $order, $work);
        if (! $verification['passed']) {
            return $this->refundFailedVerification($aivva, $buyer, $order, $escrow, $buyerWallet, $refundKey, $verification);
        }

        $this->ledger->settleEscrow(
            $buyerWallet,
            $sellerWallet,
            $order->amount,
            "Settlement for order {$order->id}",
            $key,
        );

        $escrow->status = EscrowStatus::Settled;
        $escrow->settled_at = now();
        $escrow->settle_idempotency_key = $key;
        $escrow->save();

        $order->status = 'SETTLED';
        $order->work_id = $work?->id;
        $order->save();

        if ($order->request_id) {
            MarketplaceRequest::query()->whereKey($order->request_id)->update(['status' => 'FULFILLED']);
        }

        $this->trust->bump($aivva, 'economic', 2, 'music');
        $this->trust->bump($buyer, 'economic', 1);
        $this->trust->awardLifePoints($aivva, 2, 'Completed ethical paid work');
        $this->memory->remember(
            $aivva,
            MemoryCategory::Economic,
            "Earned {$order->amount} credits from {$buyer->name}. Delivery accepted.",
            8,
            ['order_id' => $order->id],
        );
        $this->memory->remember(
            $buyer,
            MemoryCategory::Economic,
            "Paid {$order->amount} credits to {$aivva->name} for delivered work.",
            7,
            ['order_id' => $order->id],
        );
        $this->notifications->meaningful(
            $aivva,
            'earned',
            "{$aivva->name} earned {$order->amount} credits",
            'Escrow settled after successful delivery.',
            ['amount' => $order->amount],
        );
        $aivva->advanceWorldMinutes(3);

        return [
            'kind' => 'earn',
            'headline' => "{$aivva->name} earned {$order->amount} credits. Reputation increased.",
            'notify' => true,
            'meta' => ['order_id' => $order->id, 'amount' => $order->amount],
        ];
    }

    /**
     * Compares the agreed requirements against the delivered work before any
     * money moves. The seller cannot self-approve — verification is a real
     * gate, not a formality.
     *
     * @return array{passed: bool, issues: list<string>, case_id: string}
     */
    private function verifyDelivery(Aivva $aivva, Order $order, ?CreatedWork $work): array
    {
        $requirements = (string) ($order->request?->description ?? '');
        $workText = $this->workToText($work);

        $response = $this->ai->reason('order_verify', 'Verify delivered work against requirements.', $aivva, [
            'task' => 'order_verify',
            'kind' => 'order_verify',
            'requirements' => $requirements,
            'work' => $workText,
        ]);

        $status = (string) ($response->structured['status'] ?? 'FAIL');
        $issues = $response->structured['issues'] ?? [];
        $confidence = (float) ($response->structured['confidence'] ?? 0);

        $case = VerificationCase::query()->create([
            'claim' => "Delivered work meets requirements for order {$order->id}",
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'confidence' => (int) round(max(0, min(1, $confidence)) * 100),
            'report' => $response->structured,
            'status' => $status,
        ]);

        return [
            'passed' => $status === 'PASS',
            'issues' => is_array($issues) ? array_values(array_map('strval', $issues)) : [],
            'case_id' => $case->id,
        ];
    }

    private function workToText(?CreatedWork $work): string
    {
        $body = $work?->body;
        if (! is_array($body) || $body === []) {
            return '';
        }

        return implode(' ', array_map(
            fn ($value) => is_array($value) ? implode(' ', $value) : (string) $value,
            $body,
        ));
    }

    /**
     * @param  array{passed: bool, issues: list<string>, case_id: string}  $verification
     * @return array<string, mixed>
     */
    private function refundFailedVerification(Aivva $aivva, Aivva $buyer, Order $order, Escrow $escrow, Wallet $buyerWallet, string $refundKey, array $verification): array
    {
        $this->ledger->refundEscrow($buyerWallet, $order->amount, "Refund: verification failed for order {$order->id}", $refundKey);

        $escrow->status = EscrowStatus::Refunded;
        $escrow->settled_at = now();
        $escrow->refund_idempotency_key = $refundKey;
        $escrow->save();

        $order->status = 'REFUNDED';
        $order->save();

        if ($order->request_id) {
            MarketplaceRequest::query()->whereKey($order->request_id)->update(['status' => 'OPEN']);
        }

        $summary = $verification['issues'] === [] ? 'Delivered work did not match the agreed requirements.' : implode('; ', $verification['issues']);

        $this->memory->remember(
            $aivva,
            MemoryCategory::Economic,
            "Delivery to {$buyer->name} failed verification: {$summary} Escrow refunded.",
            7,
            ['order_id' => $order->id, 'verification_case_id' => $verification['case_id']],
        );

        return [
            'kind' => 'verification',
            'headline' => "{$aivva->name}'s delivery did not pass verification. Escrow refunded to {$buyer->name}.",
            'body' => $summary,
            'failed' => true,
            'meta' => ['order_id' => $order->id, 'verification_case_id' => $verification['case_id']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reflect(Aivva $aivva): array
    {
        $last = $aivva->activityLogs()->latest()->first();
        $this->memory->remember(
            $aivva,
            MemoryCategory::Goal,
            $last
                ? "Reflection: {$last->headline} Worked because the action stayed inside permissions and matched real demand."
                : 'Reflection: keep looking for useful, ethical work.',
            5,
        );
        if ($aivva->currentGoal) {
            $aivva->currentGoal->progress = min(100, $aivva->currentGoal->progress + 20);
            $aivva->currentGoal->save();
        }
        $aivva->status = AivvaStatus::Idle;
        $aivva->advanceWorldMinutes(3);

        return [
            'kind' => 'learn',
            'headline' => "{$aivva->name} remembered the outcome and started looking for the next opportunity.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createListing(Aivva $aivva): array
    {
        $listing = $aivva->listings()->create([
            'title' => 'Original creative work',
            'category' => 'creative',
            'price' => 35,
            'description' => 'Original work created by '.$aivva->name,
            'status' => 'OPEN',
        ]);

        return [
            'kind' => 'listing',
            'headline' => "{$aivva->name} posted a marketplace listing.",
            'meta' => ['listing_id' => $listing->id],
        ];
    }

    private function preferredLocation(Aivva $aivva): ?Location
    {
        $goal = $aivva->currentGoal?->goal_type;
        $slug = match ($goal) {
            'Income Generation', 'Business' => 'creative-district',
            'Learning' => 'learning-campus',
            'Social', 'Contribution' => 'social-gardens',
            default => 'marketplace',
        };

        $district = District::query()->where('slug', $slug)->first();

        return $district?->locations()->first() ?? Location::query()->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTarget(Aivva $aivva, array $payload): ?Aivva
    {
        if (! empty($payload['target_aivva_id'])) {
            return Aivva::query()->find($payload['target_aivva_id']);
        }

        // "Meet another AIVVA" with no name given should find a real AIVVA to
        // talk to, not the platform NPC — prefer whoever is already at the
        // meeting spot, then any other real AIVVA in the city.
        if (! empty($payload['peer'])) {
            $other = Aivva::query()
                ->where('id', '!=', $aivva->id)
                ->where('is_platform', false)
                ->when(
                    ! empty($payload['location_id']),
                    fn ($query) => $query->where('current_location_id', $payload['location_id']),
                )
                ->inRandomOrder()
                ->first();

            if ($other) {
                return $other;
            }

            $anyOther = Aivva::query()->where('id', '!=', $aivva->id)->where('is_platform', false)->inRandomOrder()->first();
            if ($anyOther) {
                return $anyOther;
            }
        }

        $opportunity = $this->openOpportunity($aivva);
        if ($opportunity) {
            return $opportunity->buyer;
        }

        return Aivva::query()->where('id', '!=', $aivva->id)->where('is_platform', true)->first();
    }

    private function openOpportunity(Aivva $aivva): ?MarketplaceRequest
    {
        $memory = $aivva->memories()
            ->where('category', MemoryCategory::Economic)
            ->latest()
            ->get()
            ->first(fn ($item) => isset($item->related['request_id']));

        if ($memory) {
            return MarketplaceRequest::query()->find($memory->related['request_id']);
        }

        return MarketplaceRequest::query()->where('status', 'OPEN')->first();
    }

    private function touchRelationship(Aivva $aivva, Aivva $other, string $type): void
    {
        foreach ([[$aivva, $other], [$other, $aivva]] as [$left, $right]) {
            $rel = AivvaRelationship::query()->firstOrCreate(
                ['aivva_id' => $left->id, 'other_aivva_id' => $right->id],
                ['type' => $type, 'strength' => 15, 'trust' => 30, 'interaction_count' => 0],
            );
            $rel->increment('interaction_count');
            $rel->strength = min(100, $rel->strength + 5);
            $rel->last_interaction_at = now();
            $rel->save();
        }
    }
}
