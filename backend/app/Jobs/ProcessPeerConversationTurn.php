<?php

namespace App\Jobs;

use App\Domain\Chat\PeerConversationService;
use App\Models\Aivva;
use App\Models\AivvaConversation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPeerConversationTurn implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function __construct(
        public readonly string $conversationId,
        public readonly string $aivvaId,
        public readonly string $idempotencyKey,
    ) {}

    public function uniqueId(): string
    {
        return $this->idempotencyKey;
    }

    public function handle(PeerConversationService $conversations): void
    {
        $conversation = AivvaConversation::query()->find($this->conversationId);
        $speaker = Aivva::query()->find($this->aivvaId);
        if (! $conversation || ! $speaker) {
            return;
        }

        $conversations->processTurn($conversation, $speaker, $this->idempotencyKey);
    }
}
