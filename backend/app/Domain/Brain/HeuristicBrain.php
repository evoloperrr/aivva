<?php

namespace App\Domain\Brain;

use App\Ai\AiOrchestrator;
use App\Enums\BrainMode;
use App\Models\Aivva;

class HeuristicBrain implements AivvaBrainInterface
{
    public function __construct(
        private readonly AiOrchestrator $ai,
        private readonly BrainActionValidator $validator,
    ) {}

    public function mode(): BrainMode
    {
        return BrainMode::Heuristic;
    }

    public function providerName(): string
    {
        return 'heuristic';
    }

    public function modelName(): string
    {
        return 'social-v1';
    }

    public function decideNextAction(Aivva $aivva, array $context): BrainDecision
    {
        $aivva->loadMissing(['profile', 'currentGoal', 'wallet']);
        $response = $this->ai->reason('plan', $this->prompt('economic', $context), $aivva, array_merge($context, [
            'task' => 'economic_turn',
            'kind' => 'economic_turn',
            'expect_json' => true,
            'speaker' => $aivva->name,
            'skills' => $context['skills'] ?? $aivva->profile?->skills ?? [],
            'personality' => $context['personality'] ?? (string) ($aivva->profile?->personality ?? ''),
            'goal' => $context['goal'] ?? (string) ($aivva->currentGoal?->raw_direction ?? ''),
        ]));

        return $this->validator->economic($this->structured($response->structured));
    }

    public function interpretGoal(Aivva $aivva, string $direction, array $context = []): BrainDecision
    {
        $response = $this->ai->reason('plan', $direction, $aivva, array_merge($context, [
            'task' => 'goal',
            'structured' => [
                'action' => 'WAIT',
                'intent' => 'WAIT',
                'message' => 'Direction received for interpretation.',
                'memory_candidate' => 'Owner direction: '.$direction,
            ],
        ]));

        return new BrainDecision(
            action: 'WAIT',
            intent: 'WAIT',
            message: $response->text,
            memoryCandidate: 'Owner direction: '.$direction,
            raw: $response->structured,
        );
    }

    public function evaluateMessage(Aivva $aivva, array $context): BrainDecision
    {
        $response = $this->ai->reason('peer_turn', (string) ($context['prompt'] ?? 'peer turn'), $aivva, array_merge($context, [
            'task' => 'peer_turn',
            'kind' => 'peer_turn',
            'expect_json' => true,
            'speaker' => $aivva->name,
        ]));

        return $this->validator->social($this->structured($response->structured));
    }

    public function summarizeExperience(Aivva $aivva, array $context): BrainDecision
    {
        $response = $this->ai->summarize('simple', $this->prompt('memory', $context), $aivva, array_merge($context, [
            'task' => 'memory_summary',
            'kind' => 'memory_summary',
            'speaker' => $aivva->name,
        ]));
        $text = (string) ($response->structured['summary'] ?? $response->text);

        return new BrainDecision(
            action: 'WAIT',
            intent: 'WAIT',
            message: $text,
            memoryCandidate: $text,
            raw: $response->structured,
        );
    }

    public function createWork(Aivva $aivva, array $context): BrainDecision
    {
        $brief = (string) ($context['brief'] ?? $context['title'] ?? 'short promotional concept');
        $response = $this->ai->generate('create', $brief, $aivva, array_merge($context, [
            'kind' => 'writing',
            'title' => $context['title'] ?? 'Promotional concept',
        ]));

        return new BrainDecision(
            action: 'CREATE_WORK',
            intent: 'CREATE_WORK',
            message: $response->text,
            confidence: 0.78,
            raw: $response->structured,
        );
    }

    public function verifyWork(Aivva $aivva, array $context): BrainDecision
    {
        $response = $this->ai->reason('verify', $this->prompt('verify', $context), $aivva, array_merge($context, [
            'task' => 'order_verify',
            'kind' => 'order_verify',
            'expect_json' => true,
        ]));
        $raw = $this->structured($response->structured);

        return new BrainDecision(
            action: strtoupper((string) ($raw['status'] ?? 'FAIL')),
            intent: 'VERIFY',
            message: implode('; ', $raw['issues'] ?? []),
            confidence: (float) ($raw['confidence'] ?? 0.5),
            raw: $raw,
        );
    }

    /**
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    private function structured(array $structured): array
    {
        if (isset($structured['raw']) && is_string($structured['raw'])) {
            $decoded = json_decode($structured['raw'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $structured;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function prompt(string $kind, array $context): string
    {
        return $kind.' '.json_encode($context, JSON_UNESCAPED_UNICODE);
    }
}
