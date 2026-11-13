<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SocialPost;

/**
 * The marketing analyst (D182): reads back what readers did with each post (reach, comments, shares, saves), and once
 * a week they go into the analyst's review (MarketingAnalyst, D183), which changes the plan and writes the lessons.
 */
final class MarketingStats
{
    public const LESSONS = 'marketing.lessons';

    /** Daily: fresh numbers for the last ten days' posts. */
    public static function collect(): int
    {
        if (! FacebookPage::ready() && ! InstagramPage::ready()) {
            return 0;
        }
        $n = 0;
        foreach (SocialPost::where(fn ($q) => $q->whereNotNull('fb_post_id')->orWhereNotNull('ig_media_id'))->where('updated_at', '>=', now()->subDays(10))
            ->where(fn ($q) => $q->whereNull('metrics_at')->orWhere('metrics_at', '<', now()->subHours(20)))->limit(40)->get() as $s) {
            $m = array_filter(['fb' => rescue(fn () => FacebookPage::numbers($s), [], false), 'ig' => rescue(fn () => InstagramPage::numbers($s), [], false)]);
            $s->timestamps = false;   // reading numbers is not a change to the post
            $s->forceFill(['metrics' => $m ?: $s->metrics, 'metrics_at' => now()])->save();
            $n += $m ? 1 : 0;
        }

        return $n;
    }

    /** One post's numbers added up over both networks. @return array{reach: int, talk: int} */
    public static function sum(SocialPost $s): array
    {
        $reach = $talk = 0;
        foreach (array_diff_key((array) $s->metrics, ['site' => 1]) as $net) {
            $reach += (int) ($net['reach'] ?? 0);
            $talk += (int) ($net['comments'] ?? 0) + (int) ($net['shares'] ?? 0) + (int) ($net['saves'] ?? 0);
        }

        return ['reach' => $reach, 'talk' => $talk];
    }

    /** The last four weeks by kind of post. @return list<array{what: string, posts: int, reach: int, talk: float}> */
    public static function table(int $days = 28): array
    {
        $rows = [];
        foreach (SocialPost::whereNotNull('metrics')->where('created_at', '>=', now()->subDays($days))->get() as $s) {
            $k = $s->kind.' / '.$s->format;
            $sum = self::sum($s);
            $rows[$k] = ['posts' => ($rows[$k]['posts'] ?? 0) + 1, 'reach' => ($rows[$k]['reach'] ?? 0) + $sum['reach'], 'talk' => ($rows[$k]['talk'] ?? 0) + $sum['talk']];
        }
        $out = [];
        foreach ($rows as $k => $r) {
            $out[] = ['what' => $k, 'posts' => $r['posts'], 'reach' => (int) round($r['reach'] / $r['posts']), 'talk' => round($r['talk'] / $r['posts'], 1)];
        }
        usort($out, fn ($a, $b) => $b['talk'] <=> $a['talk'] ?: $b['reach'] <=> $a['reach']);

        return $out;
    }

    /** @return array{at?: string, table?: array, lessons?: list<string>} */
    public static function saved(): array
    {
        return (array) json_decode((string) Setting::get(self::LESSONS, '{}'), true);
    }
}
