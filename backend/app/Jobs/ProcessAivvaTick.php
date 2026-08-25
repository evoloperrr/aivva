<?php

namespace App\Jobs;

use App\Domain\Agent\AgentRuntime;
use App\Models\Aivva;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Unique per AIVVA: with multiple queue workers, two ticks for the same
 * AIVVA could otherwise run concurrently and race on the same plan/goal
 * state (double-advancing a step, etc).
 */
class ProcessAivvaTick implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 30;

    public function __construct(public string $aivvaId) {}

    public function uniqueId(): string
    {
        return $this->aivvaId;
    }

    public function handle(AgentRuntime $runtime): void
    {
        $aivva = Aivva::query()->find($this->aivvaId);
        if (! $aivva) {
            return;
        }
        $runtime->tick($aivva);
    }
}
