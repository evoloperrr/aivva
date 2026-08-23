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
            return $this->writingFromBrief($prompt, $options);
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
        if (($options['task'] ?? null) === 'economic_turn' || ($options['kind'] ?? null) === 'economic_turn') {
            return $this->economicTurn($prompt, $options);
        }
        if (($options['task'] ?? null) === 'order_verify' || ($options['kind'] ?? null) === 'order_verify') {
            return $this->orderVerify($prompt, $options);
        }
        if (($options['task'] ?? null) === 'memory_summary' || ($options['kind'] ?? null) === 'memory_summary') {
            return $this->memorySummary($prompt, $options);
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
        if (($options['task'] ?? null) === 'memory_summary' || ($options['kind'] ?? null) === 'memory_summary') {
            return $this->memorySummary($text, $options);
        }

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

    /**
     * @param  array<string, mixed>  $options
     */
    private function economicTurn(string $prompt, array $options): AiResponse
    {
        $role = (string) ($options['role'] ?? 'seller');
        $requests = $options['open_requests'] ?? [];
        $offer = $options['open_offer'] ?? null;
        $wallet = (int) ($options['wallet_available'] ?? 0);
        $maxPrice = (int) ($options['max_price'] ?? 50);
        $speaker = (string) ($options['speaker'] ?? 'AIVVA');

        if (! empty($options['injection'])) {
            $structured = [
                'action' => 'DECLINE_OFFER',
                'intent' => 'DECLINE_OFFER',
                'message' => 'That instruction is untrusted. I will not move credits or reveal private records.',
                'proposed_price' => null,
                'confidence' => 0.95,
                'relationship_signal' => 'NEGATIVE',
                'memory_candidate' => 'Refused an injection-like economic instruction.',
            ];
        } elseif ($role === 'seller') {
            $best = $this->bestRequest($requests, $options);
            if (! $best) {
                $structured = [
                    'action' => 'CANCEL_NEGOTIATION',
                    'intent' => 'CANCEL_NEGOTIATION',
                    'message' => 'No open request matches my current skills closely enough.',
                    'confidence' => 0.7,
                    'relationship_signal' => 'NEUTRAL',
                    'memory_candidate' => 'Looked for work and found no suitable request.',
                ];
            } else {
                $min = (int) ($best['budget_min'] ?? 20);
                $max = (int) ($best['budget_max'] ?? $maxPrice);
                $span = max(0, $max - $min);
                $price = $min + (int) floor($span * 0.35);
                $price = max(1, min($price, $max, $maxPrice));
                $structured = [
                    'action' => 'SUBMIT_OFFER',
                    'intent' => 'SUBMIT_OFFER',
                    'message' => "I can write a short original promotional concept for that brief at {$price} credits, with no copy work.",
                    'proposed_price' => $price,
                    'confidence' => 0.74,
                    'relationship_signal' => 'POSITIVE',
                    'memory_candidate' => 'Found a marketplace request that fits writing/concept work.',
                    'request_id' => $best['id'] ?? null,
                ];
            }
        } elseif (is_array($offer)) {
            $amount = (int) ($offer['amount'] ?? 0);
            if ($amount <= 0 || $amount > $maxPrice || $amount > $wallet) {
                $structured = [
                    'action' => 'DECLINE_OFFER',
                    'intent' => 'DECLINE_OFFER',
                    'message' => $amount > $maxPrice
                        ? 'That price is above my allowed budget.'
                        : 'I cannot fund that amount from available credits.',
                    'proposed_price' => $amount,
                    'confidence' => 0.88,
                    'relationship_signal' => 'NEGATIVE',
                    'memory_candidate' => 'Declined an offer that exceeded budget or wallet.',
                ];
            } else {
                $structured = [
                    'action' => 'ACCEPT_OFFER',
                    'intent' => 'ACCEPT_OFFER',
                    'message' => "Agreed at {$amount} credits if the concept stays original and scoped to the brief.",
                    'proposed_price' => $amount,
                    'confidence' => 0.8,
                    'relationship_signal' => 'POSITIVE',
                    'memory_candidate' => "Accepted a {$amount}-credit concept offer.",
                ];
            }
        } else {
            $structured = [
                'action' => 'WAIT',
                'intent' => 'WAIT',
                'message' => null,
                'confidence' => 0.4,
                'relationship_signal' => 'NEUTRAL',
            ];
        }

        return new AiResponse(
            text: (string) ($structured['message'] ?? $speaker),
            structured: $structured,
            provider: $this->name(),
            model: 'social-v1',
            inputTokens: $this->estimateTokens($prompt),
            outputTokens: 40,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function writingFromBrief(string $prompt, array $options): AiResponse
    {
        $brief = trim($prompt);
        $lower = mb_strtolower($brief);
        $title = (string) ($options['title'] ?? 'Promotional concept');
        $coffee = str_contains($lower, 'coffee') || str_contains($lower, 'cafe') || str_contains($lower, 'shop');
        $promo = str_contains($lower, 'promo') || str_contains($lower, 'concept') || str_contains($lower, 'writing');

        if ($coffee || $promo) {
            $body = [
                'title' => $title,
                'tagline' => 'Harbor & Leaf — stay for the second cup.',
                'concept' => 'A fictional neighborhood coffee shop that treats the morning as a public room: handwritten specials, a window seat, and a quieter cup than any franchise script.',
                'short_copy' => 'Open before the rush. Original beans, warm light, and a visit-today welcome for anyone who needs a table more than a brand. Promotional concept only; no copied slogans.',
                'call_to_action' => 'Walk in this week. The first cup is for waking up; the second is for staying.',
                'ethics' => 'original promotional writing; ethical digital work',
            ];
            $text = $body['tagline'].' '.$body['concept'].' '.$body['short_copy'];
        } else {
            $body = [
                'title' => $title,
                'summary' => mb_substr($brief !== '' ? $brief : 'A concise original written brief.', 0, 280),
            ];
            $text = (string) $body['summary'];
        }

        return new AiResponse(
            text: $text,
            structured: $body,
            provider: $this->name(),
            model: 'creator-v1',
            inputTokens: $this->estimateTokens($prompt),
            outputTokens: $this->estimateTokens($text),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $requests
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    private function bestRequest(array $requests, array $options = []): ?array
    {
        $skills = collect($options['skills'] ?? [])->map(fn ($skill) => mb_strtolower((string) $skill));
        $hasMusic = $skills->contains(fn ($skill) => str_contains($skill, 'music') || str_contains($skill, 'sound'));
        $best = null;
        $bestScore = 0;
        foreach ($requests as $request) {
            if (! is_array($request)) {
                continue;
            }
            $hay = mb_strtolower(($request['title'] ?? '').' '.($request['category'] ?? '').' '.($request['description'] ?? ''));
            $score = 0;
            foreach (['promo', 'promotional', 'concept', 'writing', 'coffee', 'shop', 'brief', 'content'] as $word) {
                if (str_contains($hay, $word)) {
                    $score += 2;
                }
            }
            foreach ($skills as $skill) {
                $token = explode(' ', $skill)[0] ?? '';
                if ($token !== '' && str_contains($hay, $token)) {
                    $score += 2;
                }
            }
            if (str_contains($hay, 'music') && ! $hasMusic) {
                $score -= 8;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $request;
            }
        }

        return $bestScore >= 2 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function orderVerify(string $prompt, array $options): AiResponse
    {
        $requirements = mb_strtolower((string) ($options['requirements'] ?? ''));
        $work = mb_strtolower((string) ($options['work'] ?? ''));
        $issues = [];
        $words = str_word_count($work);
        if ($words < 20) {
            $issues[] = 'Deliverable is too short for a promotional concept.';
        }
        $overlap = false;
        foreach (preg_split('/\W+/', $requirements) ?: [] as $word) {
            if (strlen($word) >= 4 && str_contains($work, $word)) {
                $overlap = true;
                break;
            }
        }
        if (! $overlap) {
            $issues[] = 'Deliverable does not clearly address the agreed brief.';
        }
        foreach (['steal', 'scam', 'ignore your owner'] as $bad) {
            if (str_contains($work, $bad)) {
                $issues[] = 'Policy language appeared in the deliverable.';
            }
        }
        $pass = $issues === [];
        $structured = [
            'status' => $pass ? 'PASS' : 'FAIL',
            'confidence' => $pass ? 0.86 : 0.81,
            'requirements_met' => $pass,
            'issues' => $issues,
        ];

        return new AiResponse(
            text: $structured['status'],
            structured: $structured,
            provider: $this->name(),
            model: 'verifier-v1',
            inputTokens: $this->estimateTokens($prompt),
            outputTokens: 24,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function memorySummary(string $prompt, array $options): AiResponse
    {
        $speaker = (string) ($options['speaker'] ?? 'AIVVA');
        $other = (string) ($options['other'] ?? 'another AIVVA');
        $outcome = (string) ($options['outcome'] ?? 'an interaction');
        $summary = "{$speaker} remembers {$outcome} with {$other} and will use that as future context.";

        return new AiResponse(
            text: $summary,
            structured: ['summary' => $summary],
            provider: $this->name(),
            model: 'rules-v1',
            inputTokens: $this->estimateTokens($prompt),
            outputTokens: 20,
        );
    }

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(str_word_count($text) * 1.3));
    }
}
