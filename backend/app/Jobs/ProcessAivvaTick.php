<?php

namespace App\Jobs;

use App\Domain\Agent\AgentRuntime;
use App\Models\Aivva;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAivvaTick implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $aivvaId) {}

    public function handle(AgentRuntime $runtime): void
    {
        $aivva = Aivva::query()->find($this->aivvaId);
        if (! $aivva) {
            return;
        }
        $runtime->tick($aivva);
    }
}
