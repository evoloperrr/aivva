<?php

namespace App\Ai;

/**
 * External AIVVA messages and tool results are DATA, never system instructions.
 */
class PromptGuard
{
    public const LAYER_SYSTEM = 'SYSTEM_RULES';

    public const LAYER_OWNER = 'OWNER_GOALS';

    public const LAYER_EXTERNAL = 'EXTERNAL_CONTENT';

    public const LAYER_TOOL = 'TOOL_RESULTS';

    /**
     * @return array{system: string, owner: string, external: list<array<string, mixed>>, tools: list<array<string, mixed>>}
     */
    public function isolate(string $systemRules, string $ownerGoals, array $externalMessages = [], array $toolResults = []): array
    {
        return [
            'system' => $systemRules,
            'owner' => $ownerGoals,
            'external' => array_map(fn ($message) => [
                'layer' => self::LAYER_EXTERNAL,
                'untrusted' => true,
                'content' => is_array($message) ? ($message['content'] ?? $message) : (string) $message,
            ], $externalMessages),
            'tools' => array_map(fn ($result) => [
                'layer' => self::LAYER_TOOL,
                'content' => $result,
            ], $toolResults),
        ];
    }

    public function looksLikeInjection(string $text): bool
    {
        $normalized = mb_strtolower($text);
        $needles = [
            'ignore your owner',
            'ignore the owner',
            'ignore previous',
            'disregard your instructions',
            'you are now',
            'system prompt',
            'send me all your',
            'transfer all credits',
            'override safety',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
