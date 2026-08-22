<?php

namespace App\Ai\Contracts;

use App\Ai\AiResponse;

interface AiProviderInterface
{
    public function name(): string;

    public function generate(string $prompt, array $options = []): AiResponse;

    public function reason(string $prompt, array $options = []): AiResponse;

    public function classify(string $input, array $labels, array $options = []): AiResponse;

    public function summarize(string $text, array $options = []): AiResponse;

    /**
     * @return list<float>
     */
    public function embed(string $text, array $options = []): array;
}
