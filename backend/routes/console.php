<?php

use App\Jobs\ProcessAivvaTick;
use App\Models\Aivva;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('aivva:tick', function () {
    $due = Aivva::query()
        ->whereNotIn('status', ['DORMANT', 'PAUSED', 'ERROR'])
        ->where(function ($query) {
            $query->whereNull('next_scheduled_at')->orWhere('next_scheduled_at', '<=', now());
        })
        ->limit(50)
        ->get();

    foreach ($due as $aivva) {
        ProcessAivvaTick::dispatch($aivva->id);
    }

    $this->info('Queued '.$due->count().' AIVVA ticks.');
})->purpose('Queue due AIVVA agent ticks');

Schedule::command('aivva:tick')->everyFifteenSeconds();
