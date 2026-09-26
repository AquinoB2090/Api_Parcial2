<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('bids', fn ($request) => Limit::perMinute(30)->by($request->user()->IdUsuario));
        RateLimiter::for('streams', fn ($request) => Limit::perMinute(30)->by($request->user()->IdUsuario));
    }
}
