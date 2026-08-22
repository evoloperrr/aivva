<?php

namespace App\Ai\Providers;

use App\Ai\AiResponse;
use App\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly HeuristicProvider $fallback,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function generate(string $prompt, array $options = []): AiResponse
    {
        return $this->complete($prompt, $options['model'] ?? 'gpt-4o-mini', $options);
    }

    public function reason(string $prompt, array $options = []): AiResponse
    {
        return $this->complete($prompt, $options['model'] ?? 'gpt-4o-mini', $options);
    }

    public function classify(string $input, array $labels, array $options = []): AiResponse
    {
        $prompt = "Classify the input into exactly one label.\nLabels: ".implode(', ', $labels)."\nInput: {$input}\nReturn JSON {label, confidence}.";

        return $this->complete($prompt, $options['model'] ?? 'gpt-4o-mini', $options);
    }

    public function summarize(string $text, array $options = []): AiResponse
    {
        return $this->complete("Summarize in two sentences:\n{$text}", $options['model'] ?? 'gpt-4o-mini', $options);
    }

    public function embed(string $text, array $options = []): array
    {
        $key = config('services.openai.key');
        if (! $key) {
            return $this->fallback->embed($text, $options);
        }

        $response = Http::withToken($key)
            ->timeout(20)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $options['model'] ?? 'text-embedding-3-small',
                'input' => $text,
            ]);

        if (! $response->successful()) {
            return $this->fallback->embed($text, $options);
        }

        return $response->json('data.0.embedding') ?? $this->fallback->embed($text, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function complete(string $prompt, string $model, array $options): AiResponse
    {
        $key = config('services.openai.key');
        if (! $key) {
            return $this->fallback->generate($prompt, $options);
        }

        $response = Http::withToken($key)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $options['system'] ?? 'You are an AIVVA civilization module. Return concise, safe, structured results.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.4,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed: '.$response->status());
        }

        $text = (string) $response->json('choices.0.message.content');

        return new AiResponse(
            text: $text,
            structured: ['raw' => $text],
            provider: $this->name(),
            model: $model,
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
        );
    }
}
