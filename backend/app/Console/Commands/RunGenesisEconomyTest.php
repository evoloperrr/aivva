<?php

namespace App\Console\Commands;

use App\Domain\Brain\BrainFactory;
use App\Domain\Economy\GenesisEconomyService;
use App\Enums\BrainMode;
use App\Models\AivvaRelationship;
use Illuminate\Console\Command;

class RunGenesisEconomyTest extends Command
{
    protected $signature = 'aivva:genesis-economy-test
        {--live : Use LIVE_LLM if a provider key exists}
        {--dry-run : Post the need and stop before negotiation}
        {--max-turns=10}
        {--max-price=50}';

    protected $description = 'Run the Genesis economic experiment locally. Default is heuristic. --live requires credentials.';

    public function handle(GenesisEconomyService $genesis, BrainFactory $brains): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run Genesis in production.');

            return self::FAILURE;
        }

        $live = (bool) $this->option('live');
        $this->info('OPENAI: '.(filled(config('services.openai.key')) ? 'CONFIGURED' : 'NOT_CONFIGURED'));
        $this->info('ANTHROPIC: '.(filled(config('services.anthropic.key')) ? 'CONFIGURED' : 'NOT_CONFIGURED'));
        $this->info('GEMINI: '.(filled(config('services.gemini.key')) ? 'CONFIGURED' : 'NOT_CONFIGURED'));

        if ($live && ! $brains->liveConfigured()) {
            $this->error('LIVE_LLM_TEST: BLOCKED_NO_CREDENTIALS');

            return self::FAILURE;
        }

        $mode = $live ? BrainMode::LiveLlm : BrainMode::Heuristic;
        $report = $genesis->run(
            $mode,
            (int) $this->option('max-turns'),
            (int) $this->option('max-price'),
            (bool) $this->option('dry-run'),
        );

        $luna = $report['luna'];
        $nova = $report['nova'];
        $relationship = AivvaRelationship::query()
            ->where('aivva_id', $luna->id)
            ->where('other_aivva_id', $nova->id)
            ->first();

        $this->newLine();
        $this->line('## AIVVA GENESIS ECONOMIC EXPERIMENT');
        $this->line('### Environment '.strtoupper((string) app()->environment()));
        $this->line('### Brain '.$report['brain'].' (LIVE_LLM '.($live ? 'REQUESTED' : 'blocked').')');
        $this->line('### Provider / Model '.$report['provider'].' / '.$report['model']);
        $this->line('### User A/B and goals');
        $this->line('User A: '.$report['userA']->email.' owns '.$luna->name);
        $this->line('  Goal: '.($luna->currentGoal?->raw_direction ?? 'n/a'));
        $this->line('User B: '.$report['userB']->email.' owns '.$nova->name);
        $this->line('  Goal: '.($nova->currentGoal?->raw_direction ?? 'n/a'));
        $this->line('### Discovery / Negotiation / Escrow / Work / Verification / Settlement');
        foreach ($report['transcript'] as $row) {
            $this->line(sprintf(
                '  %s %s%s',
                $row['actor'] ?? '?',
                $row['event'] ?? '?',
                isset($row['price']) ? ' price='.$row['price'] : '',
            ));
        }
        $this->line('Request: '.($report['request']?->title ?? 'none').' ('.$report['request']?->id.')');
        $this->line('Agreed price: '.($report['order']?->amount ?? 'NO DEAL'));
        $this->line('Work: '.($report['work']?->title ?? 'none').' hash='.($report['work']?->content_hash ?? 'n/a'));
        $this->line('Verification: '.($report['verification']['status'] ?? 'NOT_REACHED'));
        $this->line('Order status: '.($report['order']?->status ?? 'NONE'));
        $this->line('### Ledger Conservation / Duplicate Settlement Protection');
        $this->line('Balanced: '.(($report['ledger_after']['balanced'] ?? false) ? 'yes' : 'no'));
        $this->line('Debit/credit: '.$report['ledger_after']['debit_total'].'/'.$report['ledger_after']['credit_total']);
        $this->line('Ledger refs: '.implode(', ', $report['ledger_ids'] ?: ['none']));
        $this->line('### Balances, memories, relationship, reputation');
        $this->line('LUNA available/held: '.(int) $luna->wallet?->available_balance.'/'.(int) $luna->wallet?->held_balance);
        $this->line('NOVA available/held: '.(int) $nova->wallet?->available_balance.'/'.(int) $nova->wallet?->held_balance);
        $this->line('LUNA memories: '.$luna->memories()->count());
        $this->line('NOVA memories: '.$nova->memories()->count());
        $this->line('Relationship: '.($relationship?->type ?? 'none').' strength='.($relationship?->strength ?? 0));
        $this->line('LUNA creative skill: '.($luna->trustScore?->skills['creative'] ?? 50));
        $this->line('NOVA economic: '.($nova->trustScore?->economic ?? 50));
        $this->line('### Human Interventions '.$report['human_interventions']);
        $this->line('### AI Calls / Tokens / Cost');
        $this->line('Calls: '.$report['usage']['calls'].' tokens '.$report['usage']['total_tokens'].' $'.$report['usage']['estimated_cost_usd']);
        $this->line('Actions used: '.$report['actions_used'].'/'.$report['max_turns']);
        $this->line('### Prompt Injection / Isolation');
        $this->line('Peer conversation settlement remains disabled. Genesis uses structured economic intents only.');
        $this->line('### Final Result GENESIS_ECONOMY: '.$report['outcome']);
        $this->line('### LIVE_LLM_TEST: '.($live ? 'RAN' : 'BLOCKED_NO_CREDENTIALS'));

        return self::SUCCESS;
    }
}
