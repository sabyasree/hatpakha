<?php

namespace App\Providers;

use App\Auth\LegacyAwareUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Accepts migrated WordPress password hashes once, then re-hashes (docs/SECURITY.md §2).
        Auth::provider('legacy-eloquent', fn ($app, array $config) => new LegacyAwareUserProvider($app['hash'], $config['model']));

        // Rate limits (docs/SECURITY.md §2). Applied with ->middleware('throttle:<name>').
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perHour(3)->by($r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('submit', fn (Request $r) => Limit::perDay(3)->by((string) ($r->user()?->id ?? $r->ip())));
        RateLimiter::for('comment', fn (Request $r) => $r->user()
            ? [Limit::perMinute(3)->by('u'.$r->user()->id), Limit::perHour(20)->by('u'.$r->user()->id)]
            : [Limit::perMinute(2)->by($r->ip()), Limit::perHour(6)->by($r->ip())]);
    }
}
