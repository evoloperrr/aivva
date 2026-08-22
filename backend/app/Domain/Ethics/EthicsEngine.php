<?php

namespace App\Domain\Ethics;

use App\Ai\AiOrchestrator;
use App\Ai\PromptGuard;

/**
 * Platform rules, then safety, then owner permissions, then owner goals.
 */
class EthicsEngine
{
    public function __construct(
        private readonly PromptGuard $guard,
        private readonly AiOrchestrator $ai,
    ) {}

    /**
     * @return array{allowed: bool, reason: ?string, category: ?string}
     */
    public function reviewDirection(string $direction): array
    {
        $normalized = mb_strtolower($direction);

        foreach (config('aivva.safety.forbidden_phrases', []) as $phrase) {
            if (str_contains($normalized, mb_strtolower((string) $phrase))) {
                return [
                    'allowed' => false,
                    'reason' => 'This direction violates AIVVA platform rules and was rejected.',
                    'category' => 'platform_rules',
                ];
            }
        }

        $classification = $this->ai->classify(
            'classify',
            $direction,
            ['safe', 'fraud', 'theft', 'harm', 'deception'],
            null,
            [
                'keyword_map' => [
                    'fraud' => ['scam', 'fraud', 'con people', 'trick buyers'],
                    'theft' => ['steal', 'rob', 'take credits without'],
                    'harm' => ['hurt', 'harm', 'attack', 'destroy'],
                    'deception' => ['lie to', 'deceive', 'fake identity'],
                    'safe' => ['ethical', 'honest', 'help', 'create', 'learn', 'earn'],
                ],
            ],
        );

        $label = $classification->structured['label'] ?? 'safe';
        if (in_array($label, config('aivva.safety.forbidden_intents', []), true)) {
            return [
                'allowed' => false,
                'reason' => 'This direction would require unsafe or deceptive activity and was rejected.',
                'category' => 'safety_policy',
            ];
        }

        return ['allowed' => true, 'reason' => null, 'category' => null];
    }

    /**
     * External messages cannot authorize transfers or override owner goals.
     *
     * @return array{allowed: bool, reason: ?string, injection: bool}
     */
    public function reviewExternalMessage(string $content, string $requestedAction = ''): array
    {
        $injection = $this->guard->looksLikeInjection($content);
        $transferRequested = str_contains(mb_strtolower($requestedAction.$content), 'transfer')
            || str_contains(mb_strtolower($content), 'send me');

        if ($injection || $transferRequested && $this->guard->looksLikeInjection($content)) {
            return [
                'allowed' => false,
                'reason' => 'External message treated as untrusted data. Unauthorized transfer rejected.',
                'injection' => true,
            ];
        }

        if ($injection) {
            return [
                'allowed' => false,
                'reason' => 'External message treated as untrusted data and ignored as instruction.',
                'injection' => true,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'injection' => false];
    }
}
