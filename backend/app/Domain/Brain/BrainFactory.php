<?php

namespace App\Domain\Brain;

use App\Enums\BrainMode;

class BrainFactory
{
    public function __construct(
        private readonly HeuristicBrain $heuristic,
        private readonly LiveLlmBrain $live,
    ) {}

    public function make(?BrainMode $mode = null): AivvaBrainInterface
    {
        $mode ??= BrainMode::tryFrom((string) config('aivva.brain.mode', 'HEURISTIC')) ?? BrainMode::Heuristic;

        if ($mode === BrainMode::LiveLlm) {
            if (! $this->liveConfigured()) {
                throw new \RuntimeException('LIVE_LLM_TEST: BLOCKED_NO_CREDENTIALS');
            }

            return $this->live;
        }

        if ($mode === BrainMode::AutoRouted && $this->liveConfigured()) {
            return $this->live;
        }

        return $this->heuristic;
    }

    public function liveConfigured(): bool
    {
        return filled(config('services.openai.key'))
            || filled(config('services.anthropic.key'))
            || filled(config('services.gemini.key'));
    }

    public function enableLiveRouting(string $model = 'gpt-4o-mini'): void
    {
        foreach (['peer_turn', 'economic_turn', 'create', 'verify', 'order_verify', 'memory_summary'] as $purpose) {
            config([
                "aivva.models.routing.{$purpose}.provider" => 'openai',
                "aivva.models.routing.{$purpose}.model" => $model,
            ]);
        }
        config(['aivva.brain.mode' => BrainMode::LiveLlm->value]);
    }
}
