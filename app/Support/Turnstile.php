<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile (docs/SECURITY.md §2, D60). Off until TURNSTILE_SITE_KEY + TURNSTILE_SECRET are set.
 * When on, a request passes only if Cloudflare's siteverify says success AND the token was issued for
 * this form's action AND on one of our own hostnames. Anything unexpected (network error, non-2xx,
 * unreadable reply, missing allowlist) fails closed. Tokens are single-use on Cloudflare's side.
 */
class Turnstile
{
    /** One stable action per protected form (also sent as data-action by the widget). */
    public const ACTIONS = ['login', 'register', 'forgot', 'comment', 'newsletter'];

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret'));
    }

    /** Hostnames our pages are served from (TURNSTILE_HOSTNAMES, comma-separated; default: APP_URL's host). */
    public static function hostnames(): array
    {
        $list = array_values(array_filter(array_map(fn ($h) => strtolower(trim($h)), explode(',', (string) config('services.turnstile.hostnames')))));
        if (app()->isProduction()) {
            $list = array_values(array_diff($list, ['localhost', '127.0.0.1']));
        }

        return $list;
    }

    public static function passes(?string $token, ?string $ip, string $action): bool
    {
        if (! self::enabled()) {
            return true;
        }
        if (! is_string($token) || $token === '' || strlen($token) > 2048 || ! in_array($action, self::ACTIONS, true)) {
            return false;
        }
        $allowed = self::hostnames();
        if ($allowed === []) {
            Log::warning('Turnstile: no allowed hostnames configured; refusing.');

            return false;
        }
        try {
            $r = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret'), 'response' => $token, 'remoteip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turnstile: siteverify unreachable', ['error' => mb_substr($e->getMessage(), 0, 160)]);

            return false;
        }
        $d = $r->successful() ? $r->json() : null;
        if (! is_array($d)) {
            return false;
        }
        $ok = ($d['success'] ?? null) === true
            && ($d['action'] ?? null) === $action
            && in_array(strtolower((string) ($d['hostname'] ?? '')), $allowed, true);
        if (! $ok && ($d['success'] ?? null) === true) {
            Log::notice('Turnstile: token for another action or host', ['action' => $d['action'] ?? null, 'hostname' => $d['hostname'] ?? null, 'expected' => $action]);
        }

        return $ok;
    }
}
