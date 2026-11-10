<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Setting;

/**
 * Owner (2026-11-08): গল্পের পাতা and ভ্রমণের ডাকটিকিট showed the same pieces until something new was published.
 * Now each shelf shows the newest pieces of the last three weeks (at most two, marked নতুন), then archive pieces
 * that change every day (marked আর্কাইভ থেকে): pieces with a cover first, and nothing shown again within 14 days.
 * The day's choice is made once and kept (so every cached copy of the home page agrees), with a few spare pieces
 * the browser can swap in for ones this reader has already read.
 */
final class HomeRotation
{
    public const FRESH_DAYS = 21;

    public const REST_DAYS = 14;

    /** @var array<string, array{0:string, 1:int}> shelf => [section slug, how many to show] */
    public const SHELVES = ['stories' => ['short-stories', 4], 'travel' => ['travel', 5]];

    public const SPARE = 3;

    /** @return array{stories: list<int>, travel: list<int>, fresh: list<int>} */
    public static function today(?\DateTimeInterface $now = null): array
    {
        $date = ($now ? \Illuminate\Support\Carbon::instance($now) : now('Asia/Kolkata'))->toDateString();
        $saved = json_decode((string) Setting::get('home.rotation'), true);
        if (($saved['date'] ?? null) === $date) {
            return $saved;
        }
        $shown = collect(json_decode((string) Setting::get('home.shown'), true) ?: [])
            ->filter(fn ($d) => $d >= now()->subDays(self::REST_DAYS)->toDateString());
        $out = ['date' => $date, 'fresh' => []];
        foreach (self::SHELVES as $shelf => [$slug, $show]) {
            $base = fn () => Post::published()->whereHas('section', fn ($q) => $q->where('slug', $slug));
            $fresh = $base()->where('published_at', '>=', now()->subDays(self::FRESH_DAYS))->latest('published_at')->limit(2)->pluck('id')->all();
            $pool = $base()->whereNotIn('id', $fresh)->get(['id', 'featured_image_path', 'featured_image_width'])
                ->sortBy(fn (Post $p) => [$p->hasCardImage() ? 0 : 1, crc32($date.'|'.$p->id)])->pluck('id');
            $rested = $pool->reject(fn ($id) => $shown->has((string) $id));
            $pick = $rested->count() >= $show + self::SPARE ? $rested : $pool;   // a small section may repeat sooner
            $ids = array_values(array_merge($fresh, $pick->take($show + self::SPARE - count($fresh))->all()));
            $out[$shelf] = $ids;
            $out['fresh'] = array_merge($out['fresh'], $fresh);
            foreach (array_slice($ids, 0, $show) as $id) {
                $shown[(string) $id] = $date;
            }
        }
        Setting::put('home.shown', json_encode($shown->all()));
        Setting::put('home.rotation', json_encode($out));

        return $out;
    }
}
