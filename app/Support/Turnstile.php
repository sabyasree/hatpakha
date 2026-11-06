<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile (free captcha), used for guest comments (docs/SECURITY.md §3).
 * Off until TURNSTILE_SITE_KEY + TURNSTILE_SECRET are set; then guests must pass it.
 */
class Turnstile
{
    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret'));
    }

    public static function passes(?string $token, ?string $ip): bool
    {
        if (! self::enabled()) {
            return true;
        }
        if (blank($token)) {
            return false;
        }
        $r = rescue(fn () => Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('services.turnstile.secret'), 'response' => $token, 'remoteip' => $ip,
        ]), null, false);

        return (bool) ($r?->json('success') ?? false);
    }
}
