<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Issue;
use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Support\Url;

/** Sitemaps (docs/SEO.md §4): an index plus ≤5,000-URL children. Cached by the full-page cache. */
class SitemapController extends Controller
{
    private const PER_FILE = 5000;

    public function robots()
    {
        if (! config('seo.indexable')) {
            // Staging: no search engine, but link previews (Facebook, WhatsApp, X, Telegram…) may read pages and
            // pictures, or shared links show no picture (D88). Pages still say noindex, so nothing is listed.
            $preview = ['facebookexternalhit', 'Facebot', 'WhatsApp', 'Twitterbot', 'TelegramBot', 'LinkedInBot', 'Slackbot', 'Discordbot'];
            $lines = collect($preview)->map(fn ($bot) => "User-agent: {$bot}\nAllow: /\nDisallow: /desk\nDisallow: /account\nDisallow: /submit\n")->implode("\n");

            return response($lines."\nUser-agent: *\nDisallow: /\n")->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $lines = [
            'User-agent: *',
            'Disallow: /desk',
            'Disallow: /submit',
            'Disallow: /account',
            'Disallow: /search',
            'Disallow: /livewire',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function index()
    {
        $pages = max(1, (int) ceil(Post::published()->count() / self::PER_FILE));
        $files = collect(range(1, $pages))->map(fn ($n) => "sitemap-posts-{$n}.xml")
            ->merge(['sitemap-sections.xml', 'sitemap-authors.xml', 'sitemap-issues.xml', 'sitemap-pages.xml']);

        return response()->view('sitemap.index', ['files' => $files])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function show(string $type)
    {
        $urls = match (true) {
            str_starts_with($type, 'posts-') => Post::published()->orderBy('id')
                ->forPage((int) substr($type, 6), self::PER_FILE)
                ->get(['id', 'slug', 'updated_at', 'featured_image_path', 'featured_image_width', 'noindex'])
                ->reject->noindex
                ->map(fn (Post $p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at, 'image' => $p->featured_image_path && $p->featured_image_width >= 400 ? asset($p->featured_image_path) : null]),
            $type === 'sections' => Section::where('is_active', true)->get()->map(fn ($s) => ['loc' => $s->url(), 'lastmod' => $s->updated_at, 'image' => null])
                ->push(['loc' => Url::to('/archive'), 'lastmod' => now(), 'image' => null])
                ->merge(collect(array_keys(ArchiveController::yearCounts()))->map(fn ($y) => ['loc' => Url::to("/archive/{$y}"), 'lastmod' => null, 'image' => null])),
            $type === 'authors' => Author::where('published_count', '>=', 3)->get()->map(fn ($a) => ['loc' => $a->url(), 'lastmod' => $a->updated_at, 'image' => null]),
            $type === 'pages' => Page::published()->where('noindex', false)->get()->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at, 'image' => null])
                // D97: collection pages live with the pages
                ->concat(\App\Models\Collection::where('status', 'approved')->whereNotNull('slug')->get()->map(fn ($c) => ['loc' => $c->url(), 'lastmod' => $c->updated_at, 'image' => null]))
                ->concat(\App\Models\Serial::live()->get()->map(fn ($s) => ['loc' => $s->url(), 'lastmod' => $s->last_episode_at ? \Illuminate\Support\Carbon::parse($s->last_episode_at) : null, 'image' => null])),
            $type === 'issues' => Issue::where('status', 'published')->get()->map(fn ($i) => ['loc' => $i->url(), 'lastmod' => $i->updated_at, 'image' => null]),
        };

        return response()->view('sitemap.urls', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
