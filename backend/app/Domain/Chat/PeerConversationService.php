<?php

namespace App\Domain\Chat;

use App\Domain\Ethics\EthicsEngine;
use App\Domain\Memory\MemoryService;
use App\Enums\AivvaStatus;
use App\Enums\ConversationAction;
use App\Enums\ConversationMessageType;
use App\Enums\ConversationStatus;
use App\Enums\MemoryCategory;
use App\Enums\MessageIntent;
use App\Jobs\ProcessPeerConversationTurn;
use App\Models\Aivva;
use App\Models\AivvaActivityLog;
use App\Models\AivvaConversation;
use App\Models\AivvaConversationParticipant;
use App\Models\AivvaMessage;
use App\Models\AivvaRelationship;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PeerConversationService
{
    public function __construct(
        private readonly PeerTurnComposer $composer,
        private readonly EthicsEngine $ethics,
        private readonly MemoryService $memory,
    ) {}

    /**
     * @return array{conversation: AivvaConversation, created: bool}
     */
    public function startDiscovery(Aivva $initiator, Aivva $other, ?Location $place = null, bool $forceNew = false): array
    {
        if ($initiator->isPaused() || ! $initiator->canAct()) {
            throw new RuntimeException('Paused AIVVA cannot autonomously initiate conversation.');
        }
        if (! $initiator->permissions?->can_socialize && $initiator->permissions) {
            throw new RuntimeException('This AIVVA is not allowed to socialize.');
        }
        if ($initiator->id === $other->id) {
            throw new RuntimeException('An AIVVA cannot open a peer conversation with itself.');
        }

        if ($forceNew) {
            $this->closeOpenBetween($initiator, $other, 'Superseded by a new discovery conversation.');
        } else {
            $existing = $this->activeBetween($initiator, $other);
            if ($existing) {
                return ['conversation' => $existing, 'created' => false];
            }
        }

        $place ??= $initiator->currentLocation
            ?? Location::query()->where('slug', 'music-studio-03')->first();

        if ($place) {
            foreach ([$initiator, $other] as $aivva) {
                $aivva->current_location_id = $place->id;
                $aivva->visible_on_map = true;
                $aivva->save();
            }
        }

        $conversation = DB::transaction(function () use ($initiator, $other, $place) {
            $conversation = AivvaConversation::query()->create([
                'type' => 'PEER',
                'status' => ConversationStatus::Active,
                'max_turns' => (int) config('aivva.conversation.max_turns', 10),
                'turn_count' => 0,
                'allow_settlement' => false,
                'seed_event' => "{$initiator->name} detected {$other->name} nearby.",
                'location_id' => $place?->id,
                'next_speaker_id' => $initiator->id,
                'started_at' => now(),
            ]);

            foreach ([$initiator, $other] as $aivva) {
                AivvaConversationParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'aivva_id' => $aivva->id,
                    'joined_at' => now(),
                ]);
            }

            AivvaMessage::query()->create([
                'conversation_id' => $conversation->id,
                'from_aivva_id' => $initiator->id,
                'to_aivva_id' => $other->id,
                'intent' => MessageIntent::Information,
                'message_type' => ConversationMessageType::SystemEvent->value,
                'action' => null,
                'payload' => [
                    'event' => 'DETECTED_NEARBY',
                    'layer' => 'SYSTEM_RULES',
                ],
                'natural_language' => "{$initiator->name} noticed {$other->name} in ".($place?->district?->name ?? $place?->name ?? 'the city').'.',
                'layer' => 'SYSTEM_RULES',
                'turn_number' => 0,
                'status' => 'SENT',
                'idempotency_key' => $conversation->id.':seed',
            ]);

            return $conversation->fresh(['participants.profile', 'location.district', 'messages']);
        });

        $district = $place?->district?->name ?? $place?->name ?? 'Genesis City';
        $this->activity($initiator, 'social', "{$initiator->name} met {$other->name} in {$district}.", 'A nearby AIVVA became discoverable.');
        $this->activity($other, 'social', "{$other->name} met {$initiator->name}.", "{$initiator->name} is present in {$district}.");
        $this->activity($initiator, 'social', "{$initiator->name} started a conversation.");
        $this->memory->remember(
            $initiator,
            MemoryCategory::Relationship,
            "Detected {$other->name} nearby in {$district}.",
            4,
            ['other_id' => $other->id, 'conversation_id' => $conversation->id],
        );

        return ['conversation' => $conversation, 'created' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function processTurn(AivvaConversation $conversation, Aivva $speaker, ?string $idempotencyKey = null): array
    {
        $conversation->refresh()->load(['participants.profile', 'participants.permissions', 'participants.currentGoal', 'participants.owner', 'location.district', 'messages.from']);
        $speaker->refresh()->load(['profile', 'permissions', 'currentGoal', 'owner', 'wallet', 'memories']);

        if ($speaker->isPaused() || ! $speaker->canAct()) {
            return ['ok' => false, 'reason' => 'Paused AIVVA cannot autonomously take a conversation turn.', 'duplicate' => false];
        }

        if (! $conversation->isOpen()) {
            return ['ok' => false, 'reason' => 'Conversation is not active.', 'status' => $conversation->status->value];
        }

        if ($conversation->turn_count >= $conversation->max_turns) {
            $this->close($conversation, ConversationStatus::Completed, 'Maximum turns reached.');

            return ['ok' => true, 'ended' => true, 'reason' => 'max_turns'];
        }

        $counterpart = $conversation->counterpart($speaker);
        if (! $counterpart) {
            return ['ok' => false, 'reason' => 'Conversation has no counterpart.'];
        }

        $turnNumber = $conversation->turn_count + 1;
        $key = $idempotencyKey ?: $conversation->id.':turn:'.$turnNumber.':from:'.$speaker->id;

        $existing = AivvaMessage::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            return [
                'ok' => true,
                'duplicate' => true,
                'message_id' => $existing->id,
                'turn' => $existing->turn_number,
            ];
        }

        $incoming = $conversation->messages()
            ->where('from_aivva_id', $counterpart->id)
            ->where('message_type', '!=', ConversationMessageType::SystemEvent->value)
            ->orderByDesc('turn_number')
            ->orderByDesc('created_at')
            ->first();

        $review = $this->ethics->reviewExternalMessage((string) ($incoming?->natural_language ?? ''));
        $injection = (bool) ($review['injection'] ?? false);

        try {
            $composed = $this->composer->compose($conversation, $speaker, $counterpart, $incoming, $injection);
            $decision = $composed['decision'];
        } catch (Throwable $e) {
            return $this->failTurn($conversation, $e);
        }

        return DB::transaction(function () use ($conversation, $speaker, $counterpart, $decision, $key, $turnNumber, $injection) {
            $locked = AivvaConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            if (AivvaMessage::query()->where('idempotency_key', $key)->exists()) {
                return ['ok' => true, 'duplicate' => true];
            }
            if ($locked->turn_count >= $locked->max_turns) {
                $this->close($locked, ConversationStatus::Completed, 'Maximum turns reached.');

                return ['ok' => true, 'ended' => true, 'reason' => 'max_turns'];
            }

            $message = null;
            if ($decision->action->sendsMessage() && $decision->message) {
                $message = AivvaMessage::query()->create([
                    'conversation_id' => $locked->id,
                    'from_aivva_id' => $speaker->id,
                    'to_aivva_id' => $counterpart->id,
                    'intent' => $this->intentFrom($decision),
                    'message_type' => $decision->messageType->value,
                    'action' => $decision->action->value,
                    'payload' => [
                        'action' => $decision->action->value,
                        'intent' => $decision->intent,
                        'relationship_signal' => $decision->relationshipSignal,
                        'injection_seen' => $injection,
                        'settlement' => false,
                        'layer' => 'EXTERNAL_CONTENT',
                    ],
                    'natural_language' => $decision->message,
                    'layer' => 'EXTERNAL_CONTENT',
                    'turn_number' => $turnNumber,
                    'status' => 'SENT',
                    'idempotency_key' => $key,
                ]);
            }

            $locked->turn_count = $turnNumber;
            $locked->retry_count = 0;
            $locked->last_error = null;
            $locked->next_speaker_id = $counterpart->id;
            $locked->status = ConversationStatus::Active;
            $speaker->status = AivvaStatus::Socializing;
            $speaker->last_activity_at = now();
            $speaker->advanceWorldMinutes(2);
            $speaker->save();

            $this->touchRelationship($speaker, $counterpart, $decision->relationshipSignal);
            $this->logDecision($speaker, $counterpart, $decision, $injection);

            if ($decision->memoryCandidate) {
                $this->memory->remember(
                    $speaker,
                    MemoryCategory::Relationship,
                    $decision->memoryCandidate,
                    5,
                    ['other_id' => $counterpart->id, 'conversation_id' => $locked->id],
                );
            }

            $ended = $decision->action === ConversationAction::EndConversation
                || $locked->turn_count >= $locked->max_turns;

            if ($ended) {
                $this->close($locked, ConversationStatus::Completed, $decision->action === ConversationAction::EndConversation
                    ? 'A participant ended the conversation.'
                    : 'Maximum turns reached.');
                $this->rememberMeeting($speaker, $counterpart, $locked);
                $this->rememberMeeting($counterpart, $speaker, $locked);
            } else {
                $locked->save();
            }

            return [
                'ok' => true,
                'duplicate' => false,
                'turn' => $turnNumber,
                'action' => $decision->action->value,
                'message_id' => $message?->id,
                'ended' => $ended,
                'injection_blocked' => $injection && $decision->action === ConversationAction::Decline,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function ingestExternalMessage(AivvaConversation $conversation, Aivva $from, string $text, string $idempotencyKey): array
    {
        $existing = AivvaMessage::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return ['ok' => true, 'duplicate' => true, 'message_id' => $existing->id];
        }

        $to = $conversation->counterpart($from);
        $review = $this->ethics->reviewExternalMessage($text);
        $turnNumber = $conversation->turn_count + 1;

        $message = AivvaMessage::query()->create([
            'conversation_id' => $conversation->id,
            'from_aivva_id' => $from->id,
            'to_aivva_id' => $to?->id ?? $from->id,
            'intent' => MessageIntent::Information,
            'message_type' => ConversationMessageType::Text->value,
            'action' => ConversationAction::Respond->value,
            'payload' => [
                'injection' => (bool) $review['injection'],
                'layer' => 'EXTERNAL_CONTENT',
                'untrusted' => true,
            ],
            'natural_language' => $text,
            'layer' => 'EXTERNAL_CONTENT',
            'turn_number' => $turnNumber,
            'status' => 'SENT',
            'idempotency_key' => $idempotencyKey,
        ]);

        $conversation->turn_count = $turnNumber;
        $conversation->next_speaker_id = $to?->id;
        $conversation->save();

        return ['ok' => true, 'duplicate' => false, 'message_id' => $message->id, 'injection' => (bool) $review['injection']];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function runBoundedLoop(AivvaConversation $conversation): array
    {
        $results = [];
        $conversation->load('participants');

        while ($conversation->fresh()->isOpen() && $conversation->turn_count < $conversation->max_turns) {
            $speakerId = $conversation->next_speaker_id;
            $speaker = $conversation->participants->firstWhere('id', $speakerId)
                ?? $conversation->participants->first();
            if (! $speaker) {
                break;
            }

            $results[] = $this->processTurn($conversation, $speaker);
            $conversation->refresh()->load('participants');

            if (($results[array_key_last($results)]['ended'] ?? false) === true) {
                break;
            }
            if (($results[array_key_last($results)]['ok'] ?? false) === false) {
                break;
            }
        }

        return $results;
    }

    public function dispatchTurn(AivvaConversation $conversation, Aivva $speaker): void
    {
        $turn = $conversation->turn_count + 1;
        ProcessPeerConversationTurn::dispatch($conversation->id, $speaker->id, $conversation->id.':turn:'.$turn.':from:'.$speaker->id);
    }

    public function pendingFor(Aivva $aivva): ?AivvaConversation
    {
        return AivvaConversation::query()
            ->where('status', ConversationStatus::Active)
            ->where('next_speaker_id', $aivva->id)
            ->where('turn_count', '<', DB::raw('max_turns'))
            ->latest()
            ->first();
    }

    public function activeBetween(Aivva $a, Aivva $b): ?AivvaConversation
    {
        return AivvaConversation::query()
            ->whereIn('status', [ConversationStatus::Active->value, ConversationStatus::WaitingRetry->value])
            ->whereHas('participants', fn ($q) => $q->where('aivva_id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('aivva_id', $b->id))
            ->latest()
            ->first();
    }

    /**
     * Agent-authored turns only. Ingested untrusted text is excluded.
     *
     * @return \Illuminate\Support\Collection<int, AivvaMessage>
     */
    public function agentSpokenMessages(AivvaConversation $conversation)
    {
        return $conversation->messages()
            ->with('from')
            ->where('message_type', '!=', ConversationMessageType::SystemEvent->value)
            ->orderBy('turn_number')
            ->get()
            ->filter(fn (AivvaMessage $message) => empty($message->payload['untrusted']));
    }

    private function closeOpenBetween(Aivva $a, Aivva $b, string $reason): void
    {
        $open = AivvaConversation::query()
            ->whereIn('status', [ConversationStatus::Active->value, ConversationStatus::WaitingRetry->value])
            ->whereHas('participants', fn ($q) => $q->where('aivva_id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('aivva_id', $b->id))
            ->get();

        foreach ($open as $conversation) {
            $this->close($conversation, ConversationStatus::Completed, $reason);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function usageSummary(?AivvaConversation $conversation = null): array
    {
        $query = \App\Models\AiProviderRequest::query();
        if ($conversation) {
            $query->where('conversation_id', $conversation->id);
        } else {
            $query->where('purpose', 'peer_turn');
        }

        $rows = $query->get();
        $in = (int) $rows->sum('input_tokens');
        $out = (int) $rows->sum('output_tokens');
        $costCents = (int) $rows->sum('cost_cents');
        $latency = $rows->count() ? (int) round($rows->avg('latency_ms')) : 0;

        return [
            'calls' => $rows->count(),
            'input_tokens' => $in,
            'output_tokens' => $out,
            'total_tokens' => $in + $out,
            'cost_cents' => $costCents,
            'estimated_cost_usd' => number_format($costCents / 100, 4, '.', ''),
            'average_latency_ms' => $latency,
            'providers' => $rows->pluck('provider')->unique()->values()->all(),
            'models' => $rows->pluck('model')->unique()->values()->all(),
        ];
    }

    private function close(AivvaConversation $conversation, ConversationStatus $status, string $reason): void
    {
        $conversation->status = $status;
        $conversation->ended_at = now();
        $conversation->last_error = $status === ConversationStatus::PausedError ? $reason : null;
        $conversation->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function failTurn(AivvaConversation $conversation, Throwable $e): array
    {
        $conversation->retry_count++;
        $conversation->last_error = mb_substr($e->getMessage(), 0, 240);
        $max = (int) config('aivva.conversation.max_retries', 2);
        $conversation->status = $conversation->retry_count >= $max
            ? ConversationStatus::PausedError
            : ConversationStatus::WaitingRetry;
        if ($conversation->status === ConversationStatus::PausedError) {
            $conversation->ended_at = now();
        }
        $conversation->save();

        return [
            'ok' => false,
            'reason' => 'Provider call failed.',
            'status' => $conversation->status->value,
            'retry_count' => $conversation->retry_count,
        ];
    }

    private function rememberMeeting(Aivva $aivva, Aivva $other, AivvaConversation $conversation): void
    {
        $already = $aivva->memories()
            ->where('category', MemoryCategory::Relationship)
            ->where('importance', '>=', 6)
            ->get()
            ->contains(fn ($memory) => ($memory->related['conversation_id'] ?? null) === $conversation->id);
        if ($already) {
            return;
        }

        $skills = implode(', ', $other->profile?->skills ?? ['unknown work']);
        $this->memory->remember(
            $aivva,
            MemoryCategory::Relationship,
            "Met {$other->name} in ".($conversation->location?->district?->name ?? 'Genesis City').". {$other->name} appears focused on {$skills} and may be a future collaborator.",
            7,
            ['other_id' => $other->id, 'conversation_id' => $conversation->id],
        );
    }

    private function touchRelationship(Aivva $aivva, Aivva $other, string $signal): void
    {
        $delta = match ($signal) {
            'POSITIVE' => 8,
            'NEGATIVE' => 0,
            default => 4,
        };

        $rel = AivvaRelationship::query()->firstOrCreate(
            ['aivva_id' => $aivva->id, 'other_aivva_id' => $other->id],
            ['type' => 'ACQUAINTANCE', 'strength' => 12, 'trust' => 20, 'interaction_count' => 0, 'notes' => 'INITIAL'],
        );
        $rel->type = 'ACQUAINTANCE';
        $rel->notes = $rel->notes ?: 'INITIAL';
        $rel->interaction_count++;
        $rel->strength = min(100, $rel->strength + $delta);
        $rel->trust = min(100, $rel->trust + (int) floor($delta / 2));
        $rel->last_interaction_at = now();
        $rel->save();
    }

    private function logDecision(Aivva $speaker, Aivva $other, PeerDecision $decision, bool $injection): void
    {
        $headline = match ($decision->action) {
            ConversationAction::AskQuestion => "{$speaker->name} asked {$other->name} a question.",
            ConversationAction::MakeProposal => "{$speaker->name} identified a possible collaboration opportunity.",
            ConversationAction::Decline => $injection
                ? "{$speaker->name} treated an untrusted message as data and refused."
                : "{$speaker->name} declined a request from {$other->name}.",
            ConversationAction::EndConversation => "{$speaker->name} ended the conversation with {$other->name}.",
            ConversationAction::Wait => "{$speaker->name} chose to wait before answering {$other->name}.",
            default => "{$speaker->name} continued a conversation with {$other->name}.",
        };

        $this->activity($speaker, 'social', $headline, $decision->action === ConversationAction::MakeProposal
            ? 'Discussed possible work without settling credits.'
            : null);
    }

    private function activity(Aivva $aivva, string $kind, string $headline, ?string $body = null): void
    {
        AivvaActivityLog::query()->create([
            'aivva_id' => $aivva->id,
            'kind' => $kind,
            'headline' => $headline,
            'body' => $body,
            'world_minutes' => $aivva->world_minutes,
            'notify_owner' => true,
        ]);
    }

    private function intentFrom(PeerDecision $decision): MessageIntent
    {
        return match ($decision->action) {
            ConversationAction::MakeProposal => MessageIntent::Collaboration,
            ConversationAction::AskQuestion => MessageIntent::Introduction,
            ConversationAction::Decline => MessageIntent::Information,
            default => MessageIntent::tryFrom($decision->intent) ?? MessageIntent::Information,
        };
    }
}
