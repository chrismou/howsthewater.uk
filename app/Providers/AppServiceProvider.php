<?php

namespace App\Providers;

use App\Services\Conditions\ConditionsCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ConditionsCache::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Search, feeds and the API do real work per request, so one client cannot tie up the server.
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
