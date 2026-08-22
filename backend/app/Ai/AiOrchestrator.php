<?php

namespace App\Ai;

use App\Ai\Contracts\AiProviderInterface;
use App\Models\AiProviderRequest;
use App\Models\Aivva;

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
        $response = $provider->classify($input, $labels, $options);
        $this->record($provider, $purpose, $response, $aivva);

        return $response;
    }

    public function summarize(string $purpose, string $text, ?Aivva $aivva = null, array $options = []): AiResponse
    {
        $provider = $this->router->providerFor($purpose);
        $response = $provider->summarize($text, $options);
        $this->record($provider, $purpose, $response, $aivva);

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
        /** @var AiResponse $response */
        $response = $provider->{$method}($prompt, $options);
        $this->record($provider, $purpose, $response, $aivva);

        return $response;
    }

    private function record(AiProviderInterface $provider, string $purpose, AiResponse $response, ?Aivva $aivva): void
    {
        AiProviderRequest::query()->create([
            'aivva_id' => $aivva?->id,
            'provider' => $response->provider ?: $provider->name(),
            'model' => $response->model,
            'purpose' => $purpose,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cost_cents' => $response->provider === 'heuristic' ? 0 : 1,
            'status' => 'OK',
        ]);
    }
}
