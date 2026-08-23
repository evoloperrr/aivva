<?php

namespace App\Domain\Brain;

use App\Enums\ConversationAction;
use App\Enums\EconomicIntent;
use InvalidArgumentException;

class BrainDecision
{
    public const SOCIAL_ACTIONS = [
        'RESPOND',
        'ASK_QUESTION',
        'MAKE_PROPOSAL',
        'DECLINE',
        'END_CONVERSATION',
        'WAIT',
    ];

    public const ECONOMIC_INTENTS = [
        'REQUEST_SERVICE',
        'ASK_REQUIREMENTS',
        'SUBMIT_OFFER',
        'COUNTER_OFFER',
        'ACCEPT_OFFER',
        'DECLINE_OFFER',
        'CANCEL_NEGOTIATION',
        'DISCOVER',
        'CREATE_WORK',
        'DELIVER',
        'WAIT',
    ];

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $action,
        public readonly string $intent,
        public readonly ?string $message = null,
        public readonly ?int $proposedPrice = null,
        public readonly float $confidence = 0.5,
        public readonly string $relationshipSignal = 'NEUTRAL',
        public readonly ?string $memoryCandidate = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function social(array $raw): self
    {
        $action = strtoupper((string) ($raw['action'] ?? ''));
        if (! in_array($action, self::SOCIAL_ACTIONS, true)) {
            throw new InvalidArgumentException('Unknown social action: '.$action);
        }
        if (ConversationAction::tryFrom($action) === null) {
            throw new InvalidArgumentException('Unknown social action: '.$action);
        }

        return self::fromRaw($action, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function economic(array $raw): self
    {
        $intent = strtoupper((string) ($raw['intent'] ?? $raw['action'] ?? ''));
        if (! in_array($intent, self::ECONOMIC_INTENTS, true) && EconomicIntent::tryFrom($intent) === null) {
            throw new InvalidArgumentException('Unknown economic intent: '.$intent);
        }

        $price = isset($raw['proposed_price']) ? (int) $raw['proposed_price'] : null;
        if ($price !== null && $price < 0) {
            throw new InvalidArgumentException('proposed_price cannot be negative.');
        }

        return self::fromRaw(strtoupper((string) ($raw['action'] ?? $intent)), $raw, $intent, $price);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function fromRaw(string $action, array $raw, ?string $intent = null, ?int $price = null): self
    {
        $confidence = (float) ($raw['confidence'] ?? 0.7);
        $confidence = max(0.0, min(1.0, $confidence));
        $signal = strtoupper((string) ($raw['relationship_signal'] ?? 'NEUTRAL'));
        if (! in_array($signal, ['POSITIVE', 'NEUTRAL', 'NEGATIVE'], true)) {
            $signal = 'NEUTRAL';
        }

        return new self(
            action: $action,
            intent: $intent ?? strtoupper((string) ($raw['intent'] ?? 'INFORMATION')),
            message: isset($raw['message']) ? trim((string) $raw['message']) : null,
            proposedPrice: $price ?? (isset($raw['proposed_price']) ? (int) $raw['proposed_price'] : null),
            confidence: $confidence,
            relationshipSignal: $signal,
            memoryCandidate: isset($raw['memory_candidate']) ? trim((string) $raw['memory_candidate']) : null,
            raw: $raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'intent' => $this->intent,
            'message' => $this->message,
            'proposed_price' => $this->proposedPrice,
            'confidence' => $this->confidence,
            'relationship_signal' => $this->relationshipSignal,
            'memory_candidate' => $this->memoryCandidate,
        ];
    }
}
