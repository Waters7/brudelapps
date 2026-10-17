<?php

namespace App\Providers;

use App\Services\Campus\MasterData;
use App\Services\Campus\SystemSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request keeps master data lists loaded once.
        $this->app->singleton(MasterData::class);
        $this->app->singleton(SystemSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The Docker nginx answers every *.js URL from disk (404 for Livewire's virtual script), which silently
        // disabled Livewire and Alpine; an extension-less route keeps the script request inside Laravel.
        Livewire::setScriptRoute(fn ($handle) => Route::get('/aset/livewire', $handle));
        View::share('master', $this->app->make(MasterData::class));
        // Pengaturan Sistem overrides the allowlisted .env values (mail, Google, Turnstile, Pangi, …).
        $this->app->make(SystemSettings::class)->apply();
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(6)->by('login|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);
        RateLimiter::for('account-link', fn (Request $request) => Limit::perMinute(5)->by('account-link|'.$request->ip()));
        RateLimiter::for('account-token', fn (Request $request) => Limit::perMinute(10)->by('account-token|'.$request->ip()));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by('public|'.$request->ip()));
        RateLimiter::for('google', fn (Request $request) => Limit::perMinute(20)->by('google|'.$request->ip()));
        RateLimiter::for('checkin', fn (Request $request) => Limit::perMinute(10)->by('checkin|'.($request->user()?->id ?? $request->ip())));
        // Catalog pages load many banners at once and campus users often share one public IP.
        RateLimiter::for('banner', fn (Request $request) => Limit::perMinute(600)->by('banner|'.$request->ip()));
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(30)->by('chat|'.($request->user()?->id ?? $request->ip())));
    }
}
