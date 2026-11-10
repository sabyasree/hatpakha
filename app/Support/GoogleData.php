<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * D119: read-only access to Google Analytics 4 (Data API) and Search Console with one service account.
 * No Google package: the service-account login is a JWT signed here with openssl. The key lives only in
 * ENV_FILE (GOOGLE_SERVICE_ACCOUNT_JSON, base64). Scopes are read-only; nothing is ever written to Google.
 */
final class GoogleData
{
    private const SCOPES = 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly';

    /** @return array{client_email: string, private_key: string}|null */
    public static function account(): ?array
    {
        $raw = (string) config('services.google.service_account');
        if ($raw === '') {
            return null;
        }
        $json = str_starts_with(ltrim($raw), '{') ? $raw : base64_decode($raw, true);
        $a = $json ? json_decode($json, true) : null;

        return is_array($a) && filled($a['client_email'] ?? null) && filled($a['private_key'] ?? null) ? $a : null;
    }

    public static function configured(): bool
    {
        return self::account() !== null && (filled(config('services.google.ga_property')) || filled(config('services.google.search_console_site')));
    }

    /** @return array{0: ?string, 1: ?string} [access token, error] */
    public static function token(): array
    {
        $a = self::account();
        if (! $a) {
            return [null, 'no service account'];
        }
        if ($t = Cache::get('google.token')) {
            return [$t, null];
        }
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
            'iss' => $a['client_email'], 'scope' => self::SCOPES, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ]));
        if (! openssl_sign($unsigned, $sig, $a['private_key'], OPENSSL_ALGO_SHA256)) {
            return [null, 'the private key could not sign (check GOOGLE_SERVICE_ACCOUNT_JSON)'];
        }
        $res = rescue(fn () => Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$b64($sig),
        ]), null, false);
        $token = $res?->json('access_token');
        if (! $token) {
            return [null, 'Google login failed: '.mb_substr((string) ($res?->json('error_description') ?? $res?->status() ?? 'no connection'), 0, 160)];
        }
        Cache::put('google.token', $token, now()->addMinutes(50));

        return [$token, null];
    }

    /**
     * One GA4 report. @param list<string> $dimensions @param list<string> $metrics
     *
     * @return array{0: list<array<string, string|float>>, 1: ?string} [rows keyed by dimension/metric name, error]
     */
    public static function ga(array $dimensions, array $metrics, int $limit = 10, string $from = '28daysAgo', string $to = 'yesterday'): array
    {
        $property = preg_replace('/\D/', '', (string) config('services.google.ga_property'));
        [$token, $err] = $property ? self::token() : [null, 'GA_PROPERTY_ID is not set'];
        if (! $token) {
            return [[], $err];
        }
        $res = rescue(fn () => Http::withToken($token)->timeout(30)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport", array_filter([
            'dateRanges' => [['startDate' => $from, 'endDate' => $to]],
            'dimensions' => array_map(fn ($d) => ['name' => $d], $dimensions) ?: null,
            'metrics' => array_map(fn ($m) => ['name' => $m], $metrics),
            'orderBys' => [['metric' => ['metricName' => $metrics[0]], 'desc' => true]],
            'limit' => $limit,
        ])), null, false);
        if (! $res || ! $res->successful()) {
            return [[], 'Analytics: '.mb_substr((string) ($res?->json('error.message') ?? $res?->status() ?? 'no connection'), 0, 160)];
        }
        $rows = [];
        foreach ((array) $res->json('rows') as $r) {
            $row = [];
            foreach ($dimensions as $i => $d) {
                $row[$d] = (string) ($r['dimensionValues'][$i]['value'] ?? '');
            }
            foreach ($metrics as $i => $m) {
                $row[$m] = round((float) ($r['metricValues'][$i]['value'] ?? 0), 3);
            }
            $rows[] = $row;
        }

        return [$rows, null];
    }

    /** Search Console: top rows for one dimension (query or page). @return array{0: list<array>, 1: ?string} */
    public static function search(string $dimension, int $limit = 25, int $days = 28): array
    {
        $site = (string) config('services.google.search_console_site');
        [$token, $err] = $site ? self::token() : [null, 'SEARCH_CONSOLE_SITE is not set'];
        if (! $token) {
            return [[], $err];
        }
        // Search Console data arrives 2-3 days late.
        $res = rescue(fn () => Http::withToken($token)->timeout(30)->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($site).'/searchAnalytics/query', [
            'startDate' => now()->subDays($days + 3)->toDateString(), 'endDate' => now()->subDays(3)->toDateString(),
            'dimensions' => [$dimension], 'rowLimit' => $limit,
        ]), null, false);
        if (! $res || ! $res->successful()) {
            return [[], 'Search Console: '.mb_substr((string) ($res?->json('error.message') ?? $res?->status() ?? 'no connection'), 0, 160)];
        }

        return [array_map(fn ($r) => [
            $dimension => (string) ($r['keys'][0] ?? ''), 'clicks' => (int) ($r['clicks'] ?? 0), 'impressions' => (int) ($r['impressions'] ?? 0),
            'ctr' => round((float) ($r['ctr'] ?? 0) * 100, 1), 'position' => round((float) ($r['position'] ?? 0), 1),
        ], (array) $res->json('rows')), null];
    }
}
