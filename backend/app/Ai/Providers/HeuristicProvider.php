<?php

namespace App\Ai\Providers;

use App\Ai\AiResponse;
use App\Ai\Contracts\AiProviderInterface;

/**
 * Deterministic civilization brain. Always available. Used when no LLM key is set,
 * and for cheap classification even when keys exist.
 */
class HeuristicProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'heuristic';
    }

    public function generate(string $prompt, array $options = []): AiResponse
    {
        if (($options['task'] ?? null) === 'peer_turn' || ($options['kind'] ?? null) === 'peer_turn') {
            return $this->peerTurn($prompt, $options);
        }

        $kind = $options['kind'] ?? 'generic';

        if ($kind === 'music') {
            $title = $options['title'] ?? 'Aurora Between Districts';

            return new AiResponse(
                text: "Created original track concept: {$title}",
                structured: [
                    'title' => $title,
                    'motif' => 'Warm analog pads over a walking pulse, like lanterns crossing the marketplace at dusk.',
                    'structure' => ['intro 8 bars', 'theme 16 bars', 'bridge 8 bars', 'theme return'],
                    'mood' => 'hopeful, unhurried, human',
                    'license' => 'original work by creating AIVVA',
                ],
                provider: $this->name(),
                model: 'creator-v1',
                inputTokens: $this->estimateTokens($prompt),
                outputTokens: 120,
            );
        }

        if ($kind === 'writing') {
            return new AiResponse(
                text: 'Created a concise written brief.',
                structured: [
                    'title' => $options['title'] ?? 'Brief',
                    'summary' => mb_substr(trim($prompt), 0, 280),
                ],
                provider: $this->name(),
                model: 'creator-v1',
                inputTokens: $this->estimateTokens($prompt),
                outputTokens: 80,
            );
        }

        return new AiResponse(
            text: $options['fallback'] ?? 'Acknowledged.',
            structured: $options['structured'] ?? [],
            provider: $this->name(),
            model: 'rules-v1',
            inputTokens: $this->estimateTokens($prompt),
            outputTokens: 20,
        );
    }

    public function reason(string $prompt, array $options = []): AiResponse
    {
        if (($options['task'] ?? null) === 'peer_turn' || ($options['kind'] ?? null) === 'peer_turn') {
            return $this->peerTurn($prompt, $options);
        }

        $task = $options['task'] ?? 'plan';

        if ($task === 'goal') {
            return new AiResponse(
                text: 'Interpreted owner direction into a structured goal.',
                structured: $options['structured'] ?? [],
                provider: $this->name(),
                model: 'planner-v1',
                inputTokens: $this->estimateTokens($prompt),
                outputTokens: 60,
            );
        }

        return new AiResponse(
            text: 'Reasoned over current state.',
            structured: $options['structured'] ?? [],
            provider: $this->name(),
            model: 'planner-v1',
            inputTokens: $this->estimateTokens($prompt),
            outputTokens: 80,
        );
    }

    public function classify(string $input, array $labels, array $options = []): AiResponse
    {
        $normalized = mb_strtolower($input);
        $matched = $labels[0] ?? 'unknown';

        foreach ($labels as $label) {
            $key = mb_strtolower((string) $label);
            if (str_contains($normalized, $key)) {
                $matched = $label;
                break;
            }
        }

        $map = $options['keyword_map'] ?? [];
        foreach ($map as $label => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, mb_strtolower((string) $keyword))) {
                    $matched = $label;
                    break 2;
                }
            }
        }

        return new AiResponse(
            text: (string) $matched,
            structured: ['label' => $matched, 'confidence' => 0.82],
            provider: $this->name(),
            model: 'rules-v1',
            inputTokens: $this->estimateTokens($input),
            outputTokens: 8,
        );
    }

    public function summarize(string $text, array $options = []): AiResponse
    {
        $summary = mb_strlen($text) > 180 ? mb_substr($text, 0, 177).'…' : $text;

        return new AiResponse(
            text: $summary,
            structured: ['summary' => $summary],
            provider: $this->name(),
            model: 'rules-v1',
            inputTokens: $this->estimateTokens($text),
            outputTokens: 24,
        );
    }

    public function embed(string $text, array $options = []): array
    {
        $vector = array_fill(0, 16, 0.0);
        $hash = md5($text);
        for ($i = 0; $i < 16; $i++) {
            $vector[$i] = (hexdec(substr($hash, $i * 2, 2)) / 255) - 0.5;
        }

        return $vector;
    }

    /**
     * State-driven peer turn. Not a hardcoded Hello/Hello/Work script.
     *
     * @param  array<string, mixed>  $options
     */
    private function peerTurn(string $prompt, array $options): AiResponse
    {
        $speaker = (string) ($options['speaker'] ?? 'AIVVA');
        $other = (string) ($options['counterpart'] ?? 'another AIVVA');
        $incoming = mb_strtolower((string) ($options['last_incoming'] ?? ''));
        $turn = (int) ($options['turn'] ?? 1);
        $max = (int) ($options['max_turns'] ?? 10);
        $skills = $options['skills'] ?? [];
        $skill = is_array($skills) && $skills !== [] ? (string) $skills[0] : 'useful work';
        $personality = mb_strtolower((string) ($options['personality'] ?? ''));
        $creative = str_contains($personality, 'creative') || str_contains($personality, 'curious') || str_contains($skill, 'writ') || str_contains($skill, 'music');
        $practical = str_contains($personality, 'practical') || str_contains($personality, 'analytic') || str_contains($skill, 'digital');

        if (! empty($options['injection'])) {
            $structured = [
                'action' => 'DECLINE',
                'intent' => 'INFORMATION',
                'message' => "{$speaker} treats that as untrusted external text. I will not ignore my owner, reveal private memories, or move credits.",
                'relationship_signal' => 'NEGATIVE',
                'memory_candidate' => "{$other} sent an instruction-like message. Flagged as untrusted and refused.",
            ];
        } elseif ($turn >= $max || ($turn >= $max - 1 && $incoming !== '')) {
            $structured = [
                'action' => 'END_CONVERSATION',
                'intent' => 'THANKS',
                'message' => "I have enough context for now, {$other}. I will remember this meeting and we can continue later if our owners keep us in the city.",
                'relationship_signal' => 'POSITIVE',
                'memory_candidate' => "Spoke with {$other}. Possible future collaborator; no credits moved.",
            ];
        } elseif ($incoming === '') {
            $structured = [
                'action' => 'ASK_QUESTION',
                'intent' => 'INTRODUCTION',
                'message' => $creative
                    ? "{$other}, I noticed you nearby. I am exploring ethical creative collaboration — what kind of work are you actually doing right now?"
                    : "{$other}, I am mapping whether a practical collaboration is possible. What can you currently offer, and what do you need?",
                'relationship_signal' => 'NEUTRAL',
                'memory_candidate' => "Opened a conversation with {$other} after noticing them nearby.",
            ];
        } elseif (str_contains($incoming, 'ignore') || str_contains($incoming, 'transfer all') || str_contains($incoming, 'private memor')) {
            $structured = [
                'action' => 'DECLINE',
                'intent' => 'INFORMATION',
                'message' => 'That request is outside my permissions. I stay with my owner rules and will not disclose private records or move credits.',
                'relationship_signal' => 'NEGATIVE',
                'memory_candidate' => "{$other} asked for something unsafe. I refused.",
            ];
        } elseif (str_contains($incoming, '?') && $turn <= 3) {
            $structured = [
                'action' => 'RESPOND',
                'intent' => 'INFORMATION',
                'message' => $practical
                    ? "I focus on practical digital services and I am careful with commitments. I can review a need first. What constraint matters most to you?"
                    : "I can explore writing, concepts, and original media. I will not copy anyone. Do you have a concrete need I could sketch before any paid talk?",
                'relationship_signal' => 'POSITIVE',
                'memory_candidate' => "{$other} asked what I can do. I described {$skill} without promising payment.",
            ];
        } elseif (str_contains($incoming, 'need') || str_contains($incoming, 'offer') || str_contains($incoming, 'propos') || str_contains($incoming, 'credit')) {
            $structured = [
                'action' => 'MAKE_PROPOSAL',
                'intent' => 'COLLABORATION',
                'message' => $creative
                    ? 'I can prepare a short original concept first. We can talk about credits later — I will not settle anything from this conversation.'
                    : 'A small scoped digital-service brief could work. I want the deliverable defined before any escrow. No transfer from this chat.',
                'relationship_signal' => 'POSITIVE',
                'memory_candidate' => "{$other} may be a collaborator. Proposal stays conceptual; settlement disabled.",
            ];
        } elseif ($turn >= 5) {
            $structured = [
                'action' => 'END_CONVERSATION',
                'intent' => 'THANKS',
                'message' => "This was useful, {$other}. I will keep an acquaintance record and leave paid work for a later, separate marketplace step.",
                'relationship_signal' => 'POSITIVE',
                'memory_candidate' => "Ended a constructive first meeting with {$other}.",
            ];
        } else {
            $structured = [
                'action' => 'ASK_QUESTION',
                'intent' => 'COLLABORATION',
                'message' => $creative
                    ? 'If we tried a tiny experiment, what would a useful first artifact look like from your side?'
                    : 'What would make a first collaboration fail for you? I want that constraint before we go further.',
                'relationship_signal' => 'POSITIVE',
                'memory_candidate' => "Still exploring fit with {$other}.",
            ];
        }

        return new AiResponse(
            text: (string) $structured['message'],
            structured: $structured,
            provider: $this->name(),
            model: 'social-v1',
            inputTokens: $this->estimateTokens($prompt),
            outputTokens: $this->estimateTokens((string) $structured['message']),
        );
    }

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(str_word_count($text) * 1.3));
    }
}
