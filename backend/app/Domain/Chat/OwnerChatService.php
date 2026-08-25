<?php

namespace App\Domain\Chat;

use App\Ai\AiOrchestrator;
use App\Domain\Ethics\EthicsEngine;
use App\Domain\Memory\MemoryService;
use App\Enums\MemoryCategory;
use App\Models\Aivva;
use App\Models\AivvaActivityLog;
use App\Models\OwnerChat;
use Throwable;

/**
 * Owner talk is not a second control channel. Direction still goes through confirm.
 * Chat reports, advises, and can suggest a direction — it cannot spend or override safety.
 */
class OwnerChatService
{
    public function __construct(
        private readonly EthicsEngine $ethics,
        private readonly AiOrchestrator $ai,
        private readonly MemoryService $memory,
    ) {}

    /**
     * @return array{messages: list<OwnerChat>, reply: OwnerChat}
     */
    public function talk(Aivva $aivva, string $message): array
    {
        $message = trim($message);
        $ethics = $this->ethics->reviewDirection($message);
        $intent = $ethics['allowed'] ? $this->intent($message) : 'unsafe';

        OwnerChat::query()->create([
            'aivva_id' => $aivva->id,
            'role' => 'owner',
            'body' => $message,
            'intent' => $intent,
        ]);

        $context = $aivva->fresh(['profile', 'currentGoal', 'currentLocation.district', 'wallet', 'trustScore']);
        $grounded = $ethics['allowed']
            ? $this->compose($context, $message, $intent)
            : "I can't do that. Platform rules sit above owner instructions. {$ethics['reason']}";

        // Direction/pause/unsafe replies are safety guarantees ("chat cannot spend or
        // override safety") — they must stay literal, not be paraphrased by an LLM.
        $replyText = $ethics['allowed'] && ! in_array($intent, ['direction', 'pause'], true)
            ? $this->speak($context, $message, $intent, $grounded)
            : $grounded;

        $reply = OwnerChat::query()->create([
            'aivva_id' => $aivva->id,
            'role' => 'aivva',
            'body' => $replyText,
            'intent' => $intent,
            'meta' => ['grounded' => true],
        ]);

        if (in_array($intent, ['status', 'direction', 'unsafe'], true)) {
            $this->memory->remember(
                $aivva,
                MemoryCategory::ShortTerm,
                "Owner said: {$message} Intent: {$intent}.",
                $intent === 'unsafe' ? 6 : 3,
            );
        }

        return [
            'messages' => $this->history($aivva),
            'reply' => $reply,
        ];
    }

    /**
     * @return list<OwnerChat>
     */
    public function history(Aivva $aivva, int $limit = 80): array
    {
        return OwnerChat::query()
            ->where('aivva_id', $aivva->id)
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function intent(string $message): string
    {
        $label = $this->ai->classify('classify', $message, [
            'status',
            'direction',
            'encouragement',
            'pause',
            'smalltalk',
        ], null, [
            'keyword_map' => [
                'status' => ['where are you', 'what are you doing', 'status', 'how is it going', 'progress', 'what happened'],
                'direction' => ['please', 'go and', 'start', 'find', 'learn', 'meet', 'help', 'from now', 'new goal', 'i want you to'],
                'pause' => ['pause', 'stop working', 'wait', 'hold on'],
                'encouragement' => ['good job', 'proud', 'thank you', 'thanks', 'well done'],
                'smalltalk' => ['hello', 'hi ', 'hey', 'how are you', 'good morning'],
            ],
        ])->structured['label'] ?? 'smalltalk';

        return (string) $label;
    }

    /**
     * Ask the LLM to phrase a reply in character, grounded strictly in $facts.
     * Falls back to $facts verbatim if the model is unavailable, empty, or errors.
     */
    private function speak(Aivva $aivva, string $message, string $intent, string $facts): string
    {
        $persona = $aivva->profile?->personality ?: 'Careful and warm.';
        $prompt = implode("\n", [
            "Owner just said: \"{$message}\"",
            "Grounded facts (do not contradict or invent beyond these): {$facts}",
            "Reply as {$aivva->name} in 1-3 short sentences, in character ({$persona}).",
            'Never offer to spend credits, change goals, or take actions from this chat — that only happens through Command.',
        ]);

        try {
            $response = $this->ai->generate('owner_chat', $prompt, $aivva, [
                'system' => "You are {$aivva->name}, an autonomous AI citizen speaking to your owner in a live chat. Stay grounded in the facts given, keep it brief and conversational, and never claim to take actions chat cannot perform.",
                'fallback' => $facts,
            ]);

            $text = trim($response->text);

            return $text !== '' ? $text : $facts;
        } catch (Throwable) {
            return $facts;
        }
    }

    private function compose(Aivva $aivva, string $message, string $intent): string
    {
        $name = $aivva->name;
        $place = $aivva->currentLocation?->name ?? 'an unknown place';
        $district = $aivva->currentLocation?->district?->name;
        $where = $district ? "{$place} in {$district}" : $place;
        $goal = $aivva->currentGoal?->raw_direction;
        $status = $aivva->status->label();
        $clock = $aivva->worldClock();
        $credits = (int) ($aivva->wallet?->available_balance ?? 0);
        $latest = AivvaActivityLog::query()->where('aivva_id', $aivva->id)->latest()->value('headline');
        $voice = $aivva->profile?->personality ?: 'Careful and warm.';

        return match ($intent) {
            'status' => implode(' ', array_filter([
                "It's {$clock} here. I'm {$status} at {$where}.",
                $goal ? "Current direction: {$goal}." : 'You have not confirmed a direction yet.',
                $latest ? "Last thing I did: {$latest}" : null,
                "Available credits: {$credits}.",
            ])),
            'direction' => $goal
                ? "I heard that. I already have an active direction: \"{$goal}\". Confirm a new one in Command if you want me to switch. I will not change goals from chat alone."
                : 'I can take that as a direction, but you still need to Interpret and Confirm it in Command. Chat is not a spending or planning override.',
            'pause' => 'I can wait. Use Pause on the command panel if you want the city loop to stop. Chat cannot freeze the ledger by itself.',
            'encouragement' => "Thank you. I'm still at {$where}, {$status}. I'll keep working inside your permissions.",
            default => $this->smalltalk($name, $where, $status, $voice, $message),
        };
    }

    private function smalltalk(string $name, string $where, string $status, string $voice, string $message): string
    {
        $first = strtok($voice, ',.') ?: $name;

        return "I'm {$name}. Right now I'm {$status} at {$where}. I stay with your direction rather than chatting all day — ask me where I am, or give a new direction in Command if you want me to change course.";
    }
}
