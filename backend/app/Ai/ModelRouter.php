<?php

namespace App\Ai;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Providers\HeuristicProvider;
use App\Ai\Providers\OpenAiProvider;

class ModelRouter
{
    public function __construct(
        private readonly HeuristicProvider $heuristic,
        private readonly OpenAiProvider $openAi,
    ) {}

    public function providerFor(string $purpose): AiProviderInterface
    {
        $routing = config('aivva.models.routing.'.$purpose, ['provider' => 'heuristic']);
        $requested = $routing['provider'] ?? 'heuristic';

        if ($requested === 'openai' && config('services.openai.key')) {
            return $this->openAi;
        }

        return $this->heuristic;
    }

    public function modelFor(string $purpose): string
    {
        return (string) (config('aivva.models.routing.'.$purpose.'.model') ?? 'rules-v1');
    }
}
