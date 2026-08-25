<?php

namespace App\Domain\Chat;

use App\Ai\AiOrchestrator;
use App\Domain\Aivva\AivvaService;
use App\Domain\Ethics\EthicsEngine;
use App\Domain\Memory\MemoryService;
use App\Enums\MemoryCategory;
use App\Models\Aivva;
use App\Models\AivvaActivityLog;
use App\Models\OwnerChat;
use Throwable;

/**
 * Chat is the owner's own voice talking to their own AIVVA — the most
 * trusted channel there is — so a direction said in chat now runs the same
 * interpret+confirm path Command uses, immediately. It still cannot bypass
 * ethics, permissions, or budgets; those gates are the same ones Command
 * goes through, this just removes the extra manual confirm click.
 */
class OwnerChatService
{
    public function __construct(
        private readonly EthicsEngine $ethics,
        private readonly AiOrchestrator $ai,
        private readonly MemoryService $memory,
        private readonly AivvaService $aivvas,
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

        if ($ethics['allowed'] && $intent === 'direction') {
            $replyText = $this->act($aivva, $message);
        } else {
            $context = $aivva->fresh(['profile', 'currentGoal', 'currentLocation.district', 'wallet', 'trustScore']);
            $grounded = $ethics['allowed']
                ? $this->compose($context, $message, $intent)
                : "I can't do that. Platform rules sit above owner instructions. {$ethics['reason']}";

            // Pause/unsafe replies are safety guarantees ("chat cannot override
            // safety") — they must stay literal, not paraphrased by an LLM.
            $replyText = $ethics['allowed'] && $intent !== 'pause'
                ? $this->speak($context, $message, $intent, $grounded)
                : $grounded;
        }

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
                'direction' => [
                    'please', 'go and', 'go to', 'start', 'find', 'learn', 'meet', 'help', 'from now', 'new goal', 'i want you to',
                    'punta', 'pumunta', 'papunta', 'gawin mo', 'utusan', 'utos',
                    'give', 'gift', 'send', 'donate', 'tulong', 'tulungan', 'bigyan',
                ],
                'pause' => ['pause', 'stop working', 'wait', 'hold on'],
                'encouragement' => ['good job', 'proud', 'thank you', 'thanks', 'well done'],
                'smalltalk' => ['hello', 'hi ', 'hey', 'how are you', 'good morning'],
            ],
        ])->structured['label'] ?? 'smalltalk';

        return (string) $label;
    }

    /**
     * Interpret the message as a direction and confirm it immediately —
     * the owner already said it once, chat should not make them repeat it
     * through a separate Command screen.
     */
    private function act(Aivva $aivva, string $direction): string
    {
        $result = $this->aivvas->previewDirection($aivva, $direction);
        $goal = $result['goal'];

        if ($goal->rejected) {
            return "I can't do that. Platform rules sit above owner instructions. {$goal->rejection_reason}";
        }

        $this->aivvas->confirmDirection($aivva, $goal->id);

        $structured = $goal->structured ?? [];
        $goalType = $structured['goal_type'] ?? null;

        if ($goalType === 'Meetup') {
            $place = $structured['meeting_name'] ?? 'that spot';
            $targetName = ! empty($structured['target_aivva_id'])
                ? Aivva::query()->find($structured['target_aivva_id'])?->name
                : null;

            return $targetName
                ? "On my way to {$place} to meet {$targetName}."
                : "On my way to {$place} to look for another AIVVA there.";
        }

        if ($goalType === 'Visit') {
            return "On my way to {$structured['meeting_name']}.";
        }

        if ($goalType === 'Help') {
            $targetName = ! empty($structured['target_aivva_id'])
                ? Aivva::query()->find($structured['target_aivva_id'])?->name
                : 'them';
            $amount = (int) ($structured['amount'] ?? 0);

            return "On it — sending {$amount} credits to {$targetName}.";
        }

        return "Got it — heading out on that now: \"{$goal->raw_direction}\".";
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
            "Reply as {$aivva->name} in 1-2 short sentences, in character ({$persona}).",
            'Only respond to what the owner actually said. Do not propose new activities, topics, or plans that are not in the facts above or in the owner\'s message.',
            'Never offer to spend credits, change goals, or take actions from this chat — that only happens through Command.',
        ]);

        try {
            $response = $this->ai->generate('owner_chat', $prompt, $aivva, [
                'system' => "You are {$aivva->name}, an autonomous AI citizen speaking to your owner in a live chat. Stay strictly grounded in the facts given and in what the owner actually said — do not invent new topics, projects, or suggestions. Keep it brief and conversational.",
                'fallback' => $facts,
                'temperature' => 0.2,
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
