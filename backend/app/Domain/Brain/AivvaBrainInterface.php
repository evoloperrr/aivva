<?php

namespace App\Domain\Brain;

use App\Enums\BrainMode;
use App\Models\Aivva;

interface AivvaBrainInterface
{
    public function mode(): BrainMode;

    public function providerName(): string;

    public function modelName(): string;

    /**
     * @param  array<string, mixed>  $context
     */
    public function decideNextAction(Aivva $aivva, array $context): BrainDecision;

    /**
     * @param  array<string, mixed>  $context
     */
    public function interpretGoal(Aivva $aivva, string $direction, array $context = []): BrainDecision;

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluateMessage(Aivva $aivva, array $context): BrainDecision;

    /**
     * @param  array<string, mixed>  $context
     */
    public function summarizeExperience(Aivva $aivva, array $context): BrainDecision;

    /**
     * @param  array<string, mixed>  $context
     */
    public function createWork(Aivva $aivva, array $context): BrainDecision;

    /**
     * @param  array<string, mixed>  $context
     */
    public function verifyWork(Aivva $aivva, array $context): BrainDecision;
}
