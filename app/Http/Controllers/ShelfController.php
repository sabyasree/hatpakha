<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * C4 (research 2026-11, owner approved): shelves across the sections, at /tak/{slug}/ (তাক = shelf).
 * "৫ মিনিটের লেখা": prose that takes five minutes or less (poems are short anyway and have their own section).
 * "পাঠকের মন ছুঁয়েছে": the pieces readers answered most in the last 90 days (D231 feelings, members' and guests'),
 * real answers only; empty until someone has answered. Moods (App\Models\Mood) are not shelves: no piece has one yet.
 */
class ShelfController extends Controller
{
    /** slug => [title, kicker, description] */
    public const SHELVES = [
        'panch-minute' => ['৫ মিনিটের লেখা', 'অল্প সময়ে', 'পাঁচ মিনিটে পড়া যায় এমন গল্প, প্রবন্ধ আর ভ্রমণের লেখা: চায়ের কাপ শেষ হওয়ার আগেই একটা পুরো লেখা।'],
        'mon-chhuyeche' => ['পাঠকের মন ছুঁয়েছে', 'পাঠকের সাড়া', 'গত ৯০ দিনে যে লেখাগুলো পড়ে পাঠকেরা সবচেয়ে বেশি সাড়া দিয়েছেন: মন ছুঁয়েছে, ভাবিয়েছে, হাসিয়েছে, মনে থেকে গেছে।'],
    ];

    public const PER_PAGE = 24;

    public const DAYS = 90;

    public function show(Request $request, string $slug)
    {
        [$title, $kicker, $intro] = self::SHELVES[$slug];
        $page = max(1, (int) $request->query('page', 1));
        if ($slug === 'panch-minute') {
            $posts = self::shortQuery()->with(['authors', 'section'])->latest('published_at')->paginate(self::PER_PAGE);
        } else {
            $ids = self::touchedIds();
            $found = Post::published()->with(['authors', 'section'])->whereKey(array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE))->get()
                ->sortBy(fn ($p) => array_search($p->id, $ids, true))->values();
            $posts = new LengthAwarePaginator($found, count($ids), self::PER_PAGE, $page);
        }
        $posts->withPath(Url::to('/tak/'.$slug));
        abort_if($page > 1 && $posts->isEmpty(), 404);

        $seo = new Seo(
            title: $title.($page > 1 ? ' — পাতা '.\App\Support\Bn::num((string) $page) : ''),
            description: $intro,
            canonical: $page > 1 ? $request->fullUrl() : Url::to('/tak/'.$slug),
            noindex: $page > 1 || $posts->total() === 0,
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], ['আর্কাইভ', Url::to('/archive')], [$title, Url::to('/tak/'.$slug)]])],
        );
        if ($posts->total() > 0) {
            \App\Support\Ads::allow();   // D111: a list with pieces
        }

        return view('archive', ['title' => $title, 'posts' => $posts, 'seo' => $seo, 'kicker' => $kicker, 'section' => null, 'age' => null, 'intro' => $intro, 'shelf' => $slug]);
    }

    /** Published prose of five minutes or less, worth showing (not the desk's notes, not set aside by the audit). */
    public static function shortQuery()
    {
        return Post::published()->where('noindex', false)->where('reading_minutes', '<=', 5)->where('format', '!=', 'poem')
            ->whereDoesntHave('section', fn ($q) => $q->whereIn('slug', ['editorial', 'english']));
    }

    /**
     * Pieces by the number of readers' answers in the last 90 days, most first (ids only, cached an hour, D18).
     *
     * @return list<int>
     */
    public static function touchedIds(): array
    {
        return Cache::remember('shelf:touched:v1', 3600, function () {
            $since = now()->subDays(self::DAYS);
            $n = [];
            foreach (['post_likes', 'guest_feelings'] as $table) {
                foreach (DB::table($table)->where('created_at', '>=', $since)->groupBy('post_id')->selectRaw('post_id, count(*) as n')->pluck('n', 'post_id') as $id => $c) {
                    $n[(int) $id] = ($n[(int) $id] ?? 0) + (int) $c;
                }
            }
            if (! $n) {
                return [];
            }
            $live = Post::published()->where('noindex', false)->whereKey(array_keys($n))->pluck('id')->all();
            $n = array_intersect_key($n, array_flip($live));
            uksort($n, fn ($a, $b) => [$n[$b], $b] <=> [$n[$a], $a]);

            return array_slice(array_keys($n), 0, 96);
        });
    }

    /** Shelves with something on them, for the archive, the আরও sheet and the sitemap: slug => title. */
    public static function live(): array
    {
        return Cache::remember('shelf:live:v1', 3600, fn () => collect(self::SHELVES)
            ->filter(fn ($s, $slug) => $slug === 'panch-minute' ? self::shortQuery()->exists() : self::touchedIds() !== [])
            ->map(fn ($s) => $s[0])->all());
    }
}
