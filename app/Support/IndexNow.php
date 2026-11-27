<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * D312: tell Bing (and the engines that share IndexNow) at once when a new page goes live, instead of waiting for a
 * crawl. Free, no account, no package: one POST. The key is derived from the app key and served at /indexnow.txt, as
 * the protocol asks (it is public by design). Only on the live site; never more than 100 addresses at a time.
 */
final class IndexNow
{
    public static function key(): string
    {
        return substr(hash('sha256', 'indexnow|'.config('app.key')), 0, 32);
    }

    /** @param list<string> $urls */
    public static function ping(array $urls): bool
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === [] || ! Ads::liveSite() || app()->runningUnitTests() && ! config('services.indexnow.test')) {
            return false;
        }
        $res = rescue(fn () => Http::timeout(10)->post('https://api.indexnow.org/indexnow', [
            'host' => parse_url(Url::to('/'), PHP_URL_HOST), 'key' => self::key(), 'keyLocation' => rtrim(Url::to('/'), '/').'/indexnow.txt',
            'urlList' => array_slice($urls, 0, 100),
        ]), null, false);
        \App\Models\AuditLog::record('indexnow.ping', null, ['count' => count($urls), 'status' => $res?->status()]);

        return (bool) $res?->successful();
    }
}
