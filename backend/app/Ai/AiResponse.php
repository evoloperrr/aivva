<?php

namespace App\Ai;

class AiResponse
{
    /**
     * @param  array<string, mixed>  $structured
     */
    public function __construct(
        public readonly string $text,
        public readonly array $structured = [],
        public readonly string $provider = 'heuristic',
        public readonly string $model = 'rules-v1',
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}
}
