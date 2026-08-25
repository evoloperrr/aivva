<?php

namespace App\Providers;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Providers\HeuristicProvider;
use App\Domain\Brain\AivvaBrainInterface;
use App\Domain\Brain\BrainFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderInterface::class, HeuristicProvider::class);
        $this->app->singleton(AivvaBrainInterface::class, fn ($app) => $app->make(BrainFactory::class)->make());
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
