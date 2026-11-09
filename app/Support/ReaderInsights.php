<?php

namespace App\Support;

use App\Models\Setting;

/**
 * D119: once a day, a compact snapshot of what readers did (Google Analytics 4) and how people found us on
 * Google (Search Console), saved in the database. The AI team reads a short text version of it (brief()) in
 * their shared knowledge, so plans, posts, letters and SEO follow what readers actually do. Numbers only;
 * no person or IP address is stored here (Google never gives those through these reports).
 */
final class ReaderInsights
{
    /** Every morning from the scheduler (no AI involved). Returns the errors, empty when all went well. */
    public static function refresh(): array
    {
        if (! GoogleData::configured()) {
            return ['not set up'];
        }
        $errors = [];
        $get = function (array $dims, array $mets, int $limit = 10, string $from = '28daysAgo', string $to = 'yesterday') use (&$errors) {
            [$rows, $err] = GoogleData::ga($dims, $mets, $limit, $from, $to);
            $err && $errors[] = $err;

            return $rows;
        };
        $ga = null;
        if (filled(config('services.google.ga_property'))) {
            $m = ['activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'engagementRate', 'averageSessionDuration'];
            $ga = [
                'now' => $get([], $m, 1)[0] ?? null,
                'before' => $get([], $m, 1, '56daysAgo', '29daysAgo')[0] ?? null,
                'channels' => $get(['sessionDefaultChannelGroup'], ['sessions'], 8),
                'sources' => $get(['sessionSource'], ['sessions'], 10),
                'pages' => $get(['pagePath', 'pageTitle'], ['screenPageViews', 'activeUsers'], 15),
                'countries' => $get(['country'], ['activeUsers'], 6),
                'cities' => $get(['city'], ['activeUsers'], 8),
                'devices' => $get(['deviceCategory'], ['activeUsers'], 3),
                'returning' => $get(['newVsReturning'], ['activeUsers'], 3),
                'hours' => $get(['hour'], ['sessions'], 24),
                'weekdays' => $get(['dayOfWeekName'], ['sessions'], 7),
            ];
        }
        $sc = null;
        if (filled(config('services.google.search_console_site'))) {
            [$queries, $e1] = GoogleData::search('query', 25);
            [$pages, $e2] = GoogleData::search('page', 50);
            array_push($errors, ...array_filter([$e1, $e2]));
            // Pages Google already shows often but people rarely click: better titles/descriptions could help (SEO expert).
            $chances = array_values(array_filter($pages, fn ($p) => $p['impressions'] >= 100 && $p['position'] <= 15 && $p['ctr'] < 2));
            $sc = ['queries' => $queries, 'pages' => array_slice($pages, 0, 15), 'chances' => array_slice($chances, 0, 10)];
        }
        $snap = ['at' => now('Asia/Kolkata')->toDateString(), 'ga' => $ga, 'sc' => $sc, 'errors' => array_values(array_unique($errors))];
        // Keep the last good numbers if Google failed completely today.
        if (($ga['now'] ?? null) || ($sc['queries'] ?? null)) {
            Setting::put('analytics.snapshot', json_encode($snap, JSON_UNESCAPED_UNICODE));
        }
        Setting::put('analytics.status', json_encode(['at' => now()->toIso8601String(), 'errors' => $snap['errors']], JSON_UNESCAPED_UNICODE));

        return $snap['errors'];
    }

    public static function snapshot(): ?array
    {
        $s = json_decode((string) Setting::get('analytics.snapshot'), true);

        return is_array($s) ? $s : null;
    }

    /** @return array{at: ?string, errors: list<string>}|null */
    public static function status(): ?array
    {
        $s = json_decode((string) Setting::get('analytics.status'), true);

        return is_array($s) ? $s : null;
    }

    /** The short text the AI team reads (English labels; titles and queries as they are). Null when there is no data. */
    public static function brief(bool $search = true): ?string
    {
        $s = self::snapshot();
        if (! $s || (! ($s['ga']['now'] ?? null) && ! ($s['sc']['queries'] ?? null))) {
            return null;
        }
        $l = [];
        $pairs = fn (array $rows, string $dim, string $met, int $n = 8) => collect($rows)->take($n)->map(fn ($r) => $r[$dim].' '.(int) $r[$met])->implode(', ');
        if ($now = $s['ga']['now'] ?? null) {
            $was = $s['ga']['before'] ?? null;
            $chg = fn (string $k) => $was && ($was[$k] ?? 0) > 0 ? ' ('.(($d = round(100 * ($now[$k] - $was[$k]) / $was[$k])) >= 0 ? '+' : '').$d.'% vs the 28 days before)' : '';
            $l[] = 'Last 28 days: '.(int) $now['activeUsers'].' readers'.$chg('activeUsers').', '.(int) $now['sessions'].' visits, '.(int) $now['screenPageViews'].' page views'
                .$chg('screenPageViews').', '.round(100 * $now['engagementRate']).'% engaged visits, '.round($now['averageSessionDuration'] / 60, 1).' min per visit.';
            $l[] = 'How readers come: '.$pairs($s['ga']['channels'], 'sessionDefaultChannelGroup', 'sessions').'.';
            $l[] = 'Top sources: '.$pairs($s['ga']['sources'], 'sessionSource', 'sessions').'.';
            $l[] = 'Most read pages: '.collect($s['ga']['pages'])->take(10)->map(fn ($r) => '"'.trim(explode(' — ', (string) $r['pageTitle'])[0]).'" '.(int) $r['screenPageViews'])->implode('; ').'.';
            $l[] = 'Where: '.$pairs($s['ga']['countries'], 'country', 'activeUsers', 5).'. Cities: '.$pairs($s['ga']['cities'], 'city', 'activeUsers', 6).'.';
            $l[] = 'Devices: '.$pairs($s['ga']['devices'], 'deviceCategory', 'activeUsers', 3).'. '.$pairs($s['ga']['returning'], 'newVsReturning', 'activeUsers', 3).'.';
            $hours = collect($s['ga']['hours'])->sortByDesc('sessions')->take(4)->map(fn ($r) => sprintf('%02d:00', (int) $r['hour']))->implode(', ');
            $days = collect($s['ga']['weekdays'])->take(3)->pluck('dayOfWeekName')->implode(', ');
            $l[] = 'Busiest hours (site time zone): '.$hours.'. Busiest days: '.$days.'.';
        }
        if ($search && ($q = $s['sc']['queries'] ?? null)) {
            $l[] = 'Google searches that brought readers (clicks/impressions): '.collect($q)->take(15)->map(fn ($r) => '"'.$r['query'].'" '.$r['clicks'].'/'.$r['impressions'])->implode('; ').'.';
            if ($c = $s['sc']['chances'] ?? []) {
                $l[] = 'Seen often on Google but rarely clicked (better title/description could help): '.collect($c)->take(6)->map(fn ($r) => $r['page'].' ('.$r['impressions'].' views, '.$r['ctr'].'% clicked, position '.$r['position'].')')->implode('; ').'.';
            }
        }

        return "### What readers did (Google Analytics and Search Console, ".$s['at']."; real numbers: use them to decide, never invent others)\n- ".implode("\n- ", $l);
    }
}
