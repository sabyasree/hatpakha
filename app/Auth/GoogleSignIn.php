<?php

namespace App\Auth;

use App\Support\Url;
use Illuminate\Support\Facades\Http;

/**
 * D235 (owner: "Can I do Google login? ... build it"): "Google দিয়ে ঢুকুন" for readers and writers. The standard
 * OpenID Connect code flow, with no new package: the visitor is sent to Google with a one-time `state` and `nonce`
 * kept in their session; Google sends them back with a code; the site exchanges the code with Google directly (with
 * the client secret) and reads who it is from the ID token of that answer. Only name and e-mail are asked for.
 * Nothing is trusted from the browser except the code itself.
 */
final class GoogleSignIn
{
    public static function ready(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /** The one address Google may send people back to; it must be entered in Google's console exactly like this. */
    public static function callback(): string
    {
        return Url::to('/login/google/callback');
    }

    public static function url(string $state, string $nonce): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'), 'redirect_uri' => self::callback(), 'response_type' => 'code',
            'scope' => 'openid email profile', 'state' => $state, 'nonce' => $nonce, 'prompt' => 'select_account',
        ]);
    }

    /**
     * Who Google says this is, or null when anything at all is off.
     *
     * @return ?array{sub: string, email: string, name: string}
     */
    public static function person(string $code, string $nonce): ?array
    {
        $res = rescue(fn () => Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => self::callback(), 'grant_type' => 'authorization_code',
        ]), null, false);
        $parts = explode('.', (string) ($res?->successful() ? $res->json('id_token') : ''));
        if (count($parts) !== 3) {
            return null;
        }
        // The token came straight from Google over TLS in answer to our own request, so its signature adds nothing
        // here (OpenID Connect Core 3.1.3.7); every claim that matters is still checked.
        $c = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
        $ok = is_array($c)
            && in_array($c['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
            && hash_equals((string) config('services.google.client_id'), (string) ($c['aud'] ?? ''))
            && (int) ($c['exp'] ?? 0) > time()
            && hash_equals($nonce, (string) ($c['nonce'] ?? ''))
            && ($c['email_verified'] ?? false) === true
            && filled($c['sub'] ?? null) && filter_var($c['email'] ?? '', FILTER_VALIDATE_EMAIL);

        return $ok ? ['sub' => (string) $c['sub'], 'email' => strtolower((string) $c['email']),
            'name' => mb_substr(trim(strip_tags((string) ($c['name'] ?? ''))), 0, 80) ?: (string) strstr((string) $c['email'], '@', true)] : null;
    }
}
