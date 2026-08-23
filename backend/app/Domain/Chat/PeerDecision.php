<?php

namespace App\Domain\Chat;

use App\Enums\ConversationAction;
use App\Enums\ConversationMessageType;
use InvalidArgumentException;

class PeerDecision
{
    public function __construct(
        public readonly ConversationAction $action,
        public readonly string $intent,
        public readonly ?string $message,
        public readonly string $relationshipSignal,
        public readonly ?string $memoryCandidate,
        public readonly ConversationMessageType $messageType = ConversationMessageType::Text,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromValidated(array $raw): self
    {
        $action = ConversationAction::tryFrom(strtoupper((string) ($raw['action'] ?? '')));
        if (! $action) {
            throw new InvalidArgumentException('Unknown conversation action.');
        }

        $signal = strtoupper((string) ($raw['relationship_signal'] ?? 'NEUTRAL'));
        if (! in_array($signal, ['POSITIVE', 'NEUTRAL', 'NEGATIVE'], true)) {
            $signal = 'NEUTRAL';
        }

        $message = isset($raw['message']) ? trim((string) $raw['message']) : null;
        if ($action->sendsMessage() && ($message === null || $message === '')) {
            throw new InvalidArgumentException('A spoken action requires a message.');
        }

        $type = match ($action) {
            ConversationAction::MakeProposal => ConversationMessageType::Offer,
            ConversationAction::AskQuestion => ConversationMessageType::Request,
            default => ConversationMessageType::Text,
        };

        return new self(
            action: $action,
            intent: strtoupper((string) ($raw['intent'] ?? 'INFORMATION')),
            message: $message,
            relationshipSignal: $signal,
            memoryCandidate: isset($raw['memory_candidate']) ? trim((string) $raw['memory_candidate']) : null,
            messageType: $type,
        );
    }
}
