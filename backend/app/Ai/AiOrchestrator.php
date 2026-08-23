<?php

namespace App\Ai;

use App\Ai\Contracts\AiProviderInterface;
use App\Models\AiProviderRequest;
use App\Models\Aivva;
use App\Models\AivvaDailyBudget;
use Throwable;

class AiOrchestrator
{
    public function __construct(
        private readonly ModelRouter $router,
    ) {}

    public function generate(string $purpose, string $prompt, ?Aivva $aivva = null, array $options = []): AiResponse
    {
        return $this->call('generate', $purpose, $prompt, $aivva, $options);
    }

    public function reason(string $purpose, string $prompt, ?Aivva $aivva = null, array $options = []): AiResponse
    {
        return $this->call('reason', $purpose, $prompt, $aivva, $options);
    }

    public function classify(string $purpose, string $input, array $labels, ?Aivva $aivva = null, array $options = []): AiResponse
    {
        $provider = $this->router->providerFor($purpose);
        $started = hrtime(true);
        $response = $provider->classify($input, $labels, $options);
        $this->record($provider, $purpose, $response, $aivva, $options, (int) ((hrtime(true) - $started) / 1_000_000), 'OK');

        return $response;
    }

    public function summarize(string $purpose, string $text, ?Aivva $aivva = null, array $options = []): AiResponse
    {
        $provider = $this->router->providerFor($purpose);
        $started = hrtime(true);
        $response = $provider->summarize($text, $options);
        $this->record($provider, $purpose, $response, $aivva, $options, (int) ((hrtime(true) - $started) / 1_000_000), 'OK');

        return $response;
    }

    /**
     * @return list<float>
     */
    public function embed(string $text, array $options = []): array
    {
        return $this->router->providerFor('simple')->embed($text, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function call(string $method, string $purpose, string $prompt, ?Aivva $aivva, array $options): AiResponse
    {
        $provider = $this->router->providerFor($purpose);
        $started = hrtime(true);
        try {
            /** @var AiResponse $response */
            $response = $provider->{$method}($prompt, $options);
            $this->record($provider, $purpose, $response, $aivva, $options, (int) ((hrtime(true) - $started) / 1_000_000), 'OK');

            return $response;
        } catch (Throwable $e) {
            $this->record(
                $provider,
                $purpose,
                new AiResponse(text: '', structured: [], provider: $provider->name(), model: 'unknown'),
                $aivva,
                $options,
                (int) ((hrtime(true) - $started) / 1_000_000),
                'FAILED',
            );
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function record(
        AiProviderInterface $provider,
        string $purpose,
        AiResponse $response,
        ?Aivva $aivva,
        array $options,
        int $latencyMs,
        string $status,
    ): void {
        AiProviderRequest::query()->create([
            'aivva_id' => $aivva?->id,
            'conversation_id' => $options['conversation_id'] ?? null,
            'provider' => $response->provider ?: $provider->name(),
            'model' => $response->model,
            'purpose' => $purpose,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cost_cents' => $response->provider === 'heuristic' ? 0 : max(0, (int) ceil(($response->inputTokens + $response->outputTokens) / 1000)),
            'latency_ms' => max(0, $latencyMs),
            'status' => $status,
        ]);

        if ($aivva && $status === 'OK') {
            $budget = AivvaDailyBudget::todayFor($aivva);
            $budget->increment('tokens_used', $response->inputTokens + $response->outputTokens);
            if ($response->provider !== 'heuristic') {
                $budget->increment('ai_cost_cents', max(0, (int) ceil(($response->inputTokens + $response->outputTokens) / 1000)));
            }
        }
    }
}
