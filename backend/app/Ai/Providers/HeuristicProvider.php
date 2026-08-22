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

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(str_word_count($text) * 1.3));
    }
}
