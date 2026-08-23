<?php

namespace App\Domain\Brain;

use InvalidArgumentException;

class BrainActionValidator
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function social(array $raw): BrainDecision
    {
        return BrainDecision::social($raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function economic(array $raw): BrainDecision
    {
        return BrainDecision::economic($raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function rejectUnknownSocial(array $raw): never
    {
        throw new InvalidArgumentException('Unknown social action: '.strtoupper((string) ($raw['action'] ?? '')));
    }
}
