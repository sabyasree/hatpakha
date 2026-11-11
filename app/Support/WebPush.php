<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phone notifications (Web Push, D127) with PHP's own OpenSSL, no extra package:
 * the message is encrypted for the reader's browser (RFC 8291, aes128gcm) and signed with our VAPID key (RFC 8292).
 * Only to addresses of the browsers' own push services. Off the live site, only to desk staff's browsers (like D118).
 */
final class WebPush
{
    /** Push services of Chrome/Android, Firefox, Edge/Windows and Safari/iPhone. Anything else is refused (no requests to other hosts). */
    public const HOSTS = '/^(fcm\.googleapis\.com|android\.googleapis\.com|updates\.push\.services\.mozilla\.com|[a-z0-9.-]+\.push\.services\.mozilla\.com|[a-z0-9.-]+\.notify\.windows\.com|web\.push\.apple\.com|[a-z0-9.-]+\.push\.apple\.com)$/';

    private const P256_SPKI = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Test hook: fixed sender keys and salt (RFC 8291 Appendix A). */
    public static ?array $fixed = null;

    public static function configured(): bool
    {
        return strlen(self::b64d((string) config('services.webpush.public_key'))) === 65
            && strlen(self::b64d((string) config('services.webpush.private_key'))) === 32;
    }

    public static function publicKey(): ?string
    {
        return self::configured() ? config('services.webpush.public_key') : null;
    }

    public static function allowedEndpoint(string $endpoint): bool
    {
        $u = parse_url($endpoint);

        return ($u['scheme'] ?? '') === 'https' && ! isset($u['port']) && ! isset($u['user']) && preg_match(self::HOSTS, strtolower($u['host'] ?? '')) === 1;
    }

    /** Send to every phone/computer the person turned notifications on for. Returns how many were delivered. */
    public static function toUser(User $user, array $message): int
    {
        if (! self::configured() || (! MailGuard::live() && ! $user->hasRole(Role::Editor))) {
            return 0;
        }
        $sent = 0;
        foreach (PushSubscription::where('user_id', $user->id)->get() as $sub) {
            $sent += self::send($sub, $message) ? 1 : 0;
        }

        return $sent;
    }

    /** @param array{title: string, body?: string, url?: string, tag?: string} $message */
    public static function send(PushSubscription $sub, array $message): bool
    {
        if (! self::configured() || (! MailGuard::live() && ! $sub->user?->hasRole(Role::Editor))) {   // staging: desk staff only (D118)
            return false;
        }
        if (! self::allowedEndpoint($sub->endpoint)) {
            $sub->delete();

            return false;
        }
        try {
            $body = self::encrypt(json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::b64d($sub->p256dh), self::b64d($sub->auth));
            $u = parse_url($sub->endpoint);
            $res = Http::timeout(8)->withHeaders([
                'Authorization' => 'vapid t='.self::vapidToken($u['scheme'].'://'.$u['host']).', k='.config('services.webpush.public_key'),
                'TTL' => '86400',
                'Urgency' => 'normal',
                'Content-Encoding' => 'aes128gcm',
            ])->withBody($body, 'application/octet-stream')->post($sub->endpoint);
        } catch (\Throwable $e) {
            Log::warning('Push failed', ['error' => $e->getMessage()]);

            return false;
        }

        if (in_array($res->status(), [404, 410], true)) {   // the reader turned it off or the browser forgot it
            $sub->delete();

            return false;
        }
        if (! $res->successful()) {
            $sub->increment('failures');
            if ($sub->failures >= 5) {
                $sub->delete();
            }
            Log::warning('Push refused', ['status' => $res->status(), 'host' => parse_url($sub->endpoint, PHP_URL_HOST)]);

            return false;
        }
        $sub->forceFill(['failures' => 0, 'last_sent_at' => now()])->save();
        self::count((string) ($message['kind'] ?? 'other'), 'sent');

        return true;
    }

    public const KINDS = ['post', 'serial', 'section', 'daily', 'announce', 'team', 'test', 'other'];

    /** D132: one more sent or tapped notification of this kind today (numbers only). */
    public static function count(string $kind, string $what): void
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'other';
        rescue(function () use ($kind, $what) {
            $day = now()->timezone('Asia/Kolkata')->toDateString();
            \Illuminate\Support\Facades\DB::table('push_stats')->insertOrIgnore(['day' => $day, 'kind' => $kind]);
            \Illuminate\Support\Facades\DB::table('push_stats')->where(['day' => $day, 'kind' => $kind])->increment($what);
        }, null, false);
    }

    /** RFC 8291: one aes128gcm record for the browser's key (65-byte point) and auth secret (16 bytes). */
    public static function encrypt(string $plaintext, string $uaPublic, string $authSecret): string
    {
        if (strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) {
            throw new \InvalidArgumentException('Bad subscription keys');
        }
        if (self::$fixed) {
            $asPrivate = self::privateKey(self::$fixed['private'], self::$fixed['public']);
            $asPublic = self::$fixed['public'];
            $salt = self::$fixed['salt'];
        } else {
            $asPrivate = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            $ec = openssl_pkey_get_details($asPrivate)['ec'];
            $asPublic = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
            $salt = random_bytes(16);
        }

        $ecdh = openssl_pkey_derive(self::publicKeyPem($uaPublic), $asPrivate, 32);
        if ($ecdh === false) {
            throw new \RuntimeException('ECDH failed');
        }
        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0".$uaPublic.$asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $cipher = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    /** RFC 8292: a short-lived ES256 token for one push service. */
    public static function vapidToken(string $audience): string
    {
        $head = self::b64e(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64e(json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => (string) config('services.webpush.subject')], JSON_UNESCAPED_SLASHES));
        $key = self::privateKey(self::b64d(config('services.webpush.private_key')), self::b64d(config('services.webpush.public_key')));
        openssl_sign($head.'.'.$claims, $der, $key, OPENSSL_ALGO_SHA256);

        return $head.'.'.$claims.'.'.self::b64e(self::derToRaw($der));
    }

    /** A new VAPID key pair, base64url: [public (65-byte point), private (32 bytes)]. */
    public static function newKeys(): array
    {
        $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $ec = openssl_pkey_get_details($k)['ec'];

        return [self::b64e("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)), self::b64e(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT))];
    }

    private static function publicKeyPem(string $point): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::P256_SPKI).$point), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function privateKey(string $d, string $point): \OpenSSLAsymmetricKey
    {
        // SEC1 ECPrivateKey: version 1, the private number, the curve (prime256v1) and the public point.
        $der = hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$point;
        $key = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n");
        if ($key === false) {
            throw new \RuntimeException('Bad VAPID private key');
        }

        return $key;
    }

    /** OpenSSL gives ECDSA signatures as DER; JWT wants r and s as 32 bytes each. */
    private static function derToRaw(string $der): string
    {
        $pos = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7f : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = ltrim(substr($der, $pos + 2, $len), "\0");
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    public static function b64e(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function b64d(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}
