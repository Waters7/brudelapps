<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(6)->by('login|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);
        RateLimiter::for('account-link', fn (Request $request) => Limit::perMinute(5)->by('account-link|'.$request->ip()));
        RateLimiter::for('account-token', fn (Request $request) => Limit::perMinute(10)->by('account-token|'.$request->ip()));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by('public|'.$request->ip()));
    }
}
