<?php

namespace App\Console\Commands;

use App\Domain\Chat\PeerConversationService;
use App\Domain\Chat\TwoOwnerConversationFixture;
use App\Models\AivvaMessage;
use Illuminate\Console\Command;

class RunPeerConversationExperiment extends Command
{
    protected $signature = 'aivva:peer-conversation {--loop : Run the bounded autonomous loop after discovery}';

    protected $description = 'Resolve the two test owners and run a bounded AIVVA-to-AIVVA conversation in the local database.';

    public function handle(TwoOwnerConversationFixture $fixture, PeerConversationService $conversations): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run the peer conversation experiment in production.');

            return self::FAILURE;
        }

        $pair = $fixture->resolve();
        $luna = $pair['luna'];
        $nova = $pair['nova'];

        $this->info('Environment: '.app()->environment());
        $this->info('User A: '.$pair['userA']->email.' owns '.$luna->name.' ('.$luna->id.')');
        $this->info('User B: '.$pair['userB']->email.' owns '.$nova->name.' ('.$nova->id.')');
        if ($pair['createdUsers'] !== []) {
            $this->warn('Created local test users (passwords were not emailed): '.implode(', ', $pair['createdUsers']));
        }

        $started = $conversations->startDiscovery($luna, $nova, $luna->currentLocation);
        $conversation = $started['conversation'];

        if ($this->option('loop')) {
            $conversations->runBoundedLoop($conversation);
        }

        $conversation->refresh()->load(['messages.from', 'participants']);
        $spoken = $conversation->messages->where('message_type', '!=', 'SYSTEM_EVENT');
        $usage = $conversations->usageSummary($conversation);
        $live = config('services.openai.key') ? 'CONFIGURED' : 'NOT_RUN';
        $reason = config('services.openai.key')
            ? 'OpenAI key is present; this command still used the configured peer_turn provider ('.config('aivva.models.routing.peer_turn.provider').').'
            : 'OPENAI_API_KEY is empty. Heuristic social-v1 produced the turns.';

        $this->newLine();
        $this->line('Conversation: '.$conversation->id.' '.$conversation->status->value);
        $this->line('Turns: '.$conversation->turn_count.'/'.$conversation->max_turns);
        $this->line('LUNA messages: '.$spoken->where('from_aivva_id', $luna->id)->count());
        $this->line('NOVA messages: '.$spoken->where('from_aivva_id', $nova->id)->count());
        $this->line('AI calls: '.$usage['calls'].' tokens '.$usage['total_tokens'].' cost $'.$usage['estimated_cost_usd']);
        $this->line('LIVE_AI_TEST: '.$live);
        $this->line($reason);
        $this->newLine();
        foreach ($spoken as $message) {
            /** @var AivvaMessage $message */
            $this->line(sprintf(
                '[turn %s] %s (%s): %s',
                $message->turn_number,
                $message->from?->name,
                $message->action,
                $message->natural_language,
            ));
        }

        return self::SUCCESS;
    }
}
