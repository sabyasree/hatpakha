<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Post;
use App\Seo\Seo;
use App\Support\Url;
use Illuminate\Http\Request;

/**
 * Site search (D64): titles, writers and tags first, then the text itself if few titles match.
 * Published pieces only; results are noindex. Simple LIKE matching works for Bengali (no stemming needed).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim(preg_replace('/\s+/u', ' ', mb_substr(strip_tags((string) $request->query('q', '')), 0, 80)));
        $posts = null;
        $authors = collect();
        if (mb_strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $authors = Author::where('published_count', '>', 0)
                ->where(fn ($w) => $w->where('display_name', 'like', $like)->orWhere('display_name_bn', 'like', $like))
                ->orderByDesc('published_count')->limit(6)->get();
            $base = fn () => Post::published()->with(['authors', 'section']);
            $posts = $base()->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)
                ->orWhereHas('authors', fn ($a) => $a->where('display_name', 'like', $like)->orWhere('display_name_bn', 'like', $like))
                ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $like)))
                ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', [$like])->latest('published_at')
                ->paginate(20)->withQueryString();
            if ($posts->total() < 10 && mb_strlen($q) >= 3) {
                // Few title matches: look inside the text as well.
                $posts = $base()->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)->orWhere('body_html', 'like', $like)
                    ->orWhereHas('authors', fn ($a) => $a->where('display_name', 'like', $like)->orWhere('display_name_bn', 'like', $like)))
                    ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', [$like])->latest('published_at')
                    ->paginate(20)->withQueryString();
            }
        }
        $seo = new Seo(title: $q !== '' ? 'খুঁজুন: '.$q : 'খুঁজুন', canonical: Url::to('/search'), noindex: true);

        if ($posts instanceof \Illuminate\Contracts\Pagination\Paginator && $posts->count() >= 5) {
            \App\Support\Ads::allow();   // D111: only a real results page
        }

        return view('search', compact('q', 'posts', 'authors', 'seo'));
    }
}
