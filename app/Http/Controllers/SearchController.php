<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Post;
use App\Seo\Seo;
use App\Support\BnSearch;
use App\Support\Url;
use Illuminate\Http\Request;

/**
 * Site search (D64; spelling-forgiving 2026-11-12): every word folded (App\Support\BnSearch) and looked for in titles,
 * writers, tags and the text; title and writer matches first; the matching line shown; a "did you mean" when nothing fits.
 * Published pieces only; results are noindex.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim(preg_replace('/\s+/u', ' ', mb_substr(strip_tags((string) $request->query('q', '')), 0, 80)));
        $posts = null;
        $authors = collect();
        $suggest = null;
        $words = array_values(array_filter(explode(' ', BnSearch::key($q)), fn ($w) => mb_strlen($w) >= 1));
        if (mb_strlen($q) >= 2 && $words) {
            // Writers: the folded name holds every word (রবিন্দ্রনাথ finds রবীন্দ্রনাথ ঠাকুর).
            $writers = Author::whereHas('posts', fn ($p) => $p->published())->orderByDesc('published_count')->get(['id', 'slug', 'display_name', 'display_name_bn', 'avatar_path', 'published_count', 'is_desk'])
                ->filter(fn ($a) => collect($words)->every(fn ($w) => str_contains(BnSearch::key($a->display_name.' '.$a->display_name_bn), $w)))->values();
            $authors = $writers->take(6);
            $writerIds = $writers->pluck('id')->all();
            $like = fn (string $w) => '%'.addcslashes($w, '%_\\').'%';
            $exact = '%'.addcslashes($q, '%_\\').'%';
            // Every word must be somewhere (title, writer, tag, excerpt or text); title and writer matches come first,
            // then a phrase in the text, then the newest.
            $posts = Post::published()->with(['authors', 'section'])
                ->where(function ($q2) use ($words, $like, $writerIds) {
                    $q2->where(function ($w) use ($words, $like) {
                        foreach ($words as $word) {
                            $w->where(fn ($x) => $x->where('search_title', 'like', $like($word))->orWhere('search_body', 'like', $like($word)));
                        }
                    });
                    if ($writerIds) {   // every piece by a writer whose name matches
                        $q2->orWhereHas('authors', fn ($a) => $a->whereIn('authors.id', $writerIds));
                    }
                })
                ->orderByRaw('CASE WHEN title LIKE ? THEN 0 WHEN search_title LIKE ? THEN 1 WHEN search_body LIKE ? THEN 2 ELSE 3 END', [$exact, $like(implode(' ', $words)), $like(implode(' ', $words))])
                ->latest('published_at')->paginate(20)->withQueryString();
            // A line of the piece where the words appear (finds a poem by a line you remember).
            $posts->getCollection()->each(fn (Post $p) => $p->setAttribute('search_snippet', BnSearch::snippet($p, $q)));
            if ($posts->total() === 0 && $authors->isEmpty()) {
                $suggest = self::suggest(implode(' ', $words));
            }
        }
        $seo = new Seo(title: $q !== '' ? 'খুঁজুন: '.$q : 'খুঁজুন', canonical: Url::to('/search'), noindex: true);

        if ($posts instanceof \Illuminate\Contracts\Pagination\Paginator && $posts->count() >= 5) {
            \App\Support\Ads::allow();   // D111: only a real results page
        }

        return view('search', compact('q', 'posts', 'authors', 'seo', 'suggest'));
    }

    /** Nothing found: the closest title or writer name (a typo of a letter or two), as "আপনি কি এটা খুঁজছিলেন?". */
    private static function suggest(string $key): ?string
    {
        if (mb_strlen($key) < 3) {
            return null;
        }
        $names = \Illuminate\Support\Facades\Cache::remember('search.names', 3600, fn () => Author::whereHas('posts', fn ($p) => $p->published())->pluck('display_name')
            ->merge(Post::published()->pluck('title'))->filter()->unique()->values()->all());
        $best = null;
        $bestScore = 0;
        foreach ($names as $name) {
            similar_text($key, BnSearch::key($name), $pct);
            if ($pct > $bestScore) {
                [$best, $bestScore] = [$name, $pct];
            }
        }

        return $bestScore >= 70 ? $best : null;
    }
}
