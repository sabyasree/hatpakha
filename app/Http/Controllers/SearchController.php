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
 * Published pieces only; results are noindex. Writers are matched in the database (A4); filters by section, length
 * and date order (C5).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim(preg_replace('/\s+/u', ' ', mb_substr(strip_tags((string) $request->query('q', '')), 0, 80)));
        // C5: narrow by section and length, sort by newest or oldest (default: the best match first). GET only; results stay noindex.
        $filters = self::filters($request);
        $posts = null;
        $authors = collect();
        $suggest = null;
        $words = array_values(array_filter(explode(' ', BnSearch::key($q)), fn ($w) => mb_strlen($w) >= 1));
        if (mb_strlen($q) >= 2 && $words) {
            $like = fn (string $w) => '%'.addcslashes($w, '%_\\').'%';
            $exact = '%'.addcslashes($q, '%_\\').'%';
            // A4: writers are matched in the database (the folded name holds every word: রবিন্দ্রনাথ finds রবীন্দ্রনাথ ঠাকুর;
            // or the name as typed), at most 8, never by loading every writer.
            $writers = Author::query()
                ->where(function ($a) use ($words, $like, $exact) {
                    $a->where(function ($w) use ($words, $like) {
                        foreach ($words as $word) {
                            $w->where('search_name', 'like', $like($word));
                        }
                    })->orWhere('display_name', 'like', $exact)->orWhere('display_name_bn', 'like', $exact)->orWhere('slug', 'like', $exact);
                })
                ->whereHas('posts', fn ($p) => $p->published())
                ->orderByDesc('published_count')->limit(8)->get(['id', 'slug', 'display_name', 'display_name_bn', 'avatar_path', 'published_count', 'is_desk']);
            $authors = $writers->take(6);
            $writerIds = $writers->pluck('id')->all();
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
                ->when($filters['section'], fn ($x, $slug) => $x->whereHas('section', fn ($s) => $s->where('slug', $slug)))
                ->when($filters['length'] === 'short', fn ($x) => $x->where('reading_minutes', '<=', 5))
                ->when($filters['length'] === 'long', fn ($x) => $x->where('reading_minutes', '>', 15))
                ->when($filters['sort'] === 'new', fn ($x) => $x->latest('published_at'))
                ->when($filters['sort'] === 'old', fn ($x) => $x->oldest('published_at'))
                ->when($filters['sort'] === null, fn ($x) => $x->orderByRaw('CASE WHEN title LIKE ? THEN 0 WHEN search_title LIKE ? THEN 1 WHEN search_body LIKE ? THEN 2 ELSE 3 END', [$exact, $like(implode(' ', $words)), $like(implode(' ', $words))])
                    ->latest('published_at'))
                ->paginate(20)->withQueryString();
            // A line of the piece where the words appear (finds a poem by a line you remember).
            $posts->getCollection()->each(fn (Post $p) => $p->setAttribute('search_snippet', BnSearch::snippet($p, $q)));
            if ($posts->total() === 0 && $authors->isEmpty() && ! array_filter($filters)) {
                $suggest = self::suggest(implode(' ', $words));
            }
        }
        $seo = new Seo(title: $q !== '' ? 'খুঁজুন: '.$q : 'খুঁজুন', canonical: Url::to('/search'), noindex: true);
        $sections = \App\Models\Section::searchChoices();

        if ($posts instanceof \Illuminate\Contracts\Pagination\Paginator && $posts->count() >= 5) {
            \App\Support\Ads::allow();   // D111: only a real results page
        }

        return view('search', compact('q', 'posts', 'authors', 'seo', 'suggest', 'filters', 'sections'));
    }

    /** C5: the search filters from the query string; anything unknown is ignored. */
    public const LENGTHS = ['short' => '৫ মিনিটের মধ্যে', 'long' => 'লম্বা লেখা'];

    public const SORTS = ['new' => 'নতুন', 'old' => 'পুরনো'];

    /** @return array{section: ?string, length: ?string, sort: ?string} */
    public static function filters(Request $request): array
    {
        $section = (string) $request->query('section', '');
        $length = (string) $request->query('length', '');
        $sort = (string) $request->query('sort', '');

        return [
            'section' => $section !== '' && array_key_exists($section, \App\Models\Section::searchChoices()) ? $section : null,
            'length' => isset(self::LENGTHS[$length]) ? $length : null,
            'sort' => isset(self::SORTS[$sort]) ? $sort : null,
        ];
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
