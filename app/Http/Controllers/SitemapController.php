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

        // D126: AI assistants may find and quote pieces with a link (search, answers); nobody may train models on
        // the writers' work. Content Signals (contentsignals.org) say so to everyone; known training-only crawlers are shut out.
        $lines = [
            '# Hatpakha (হাতপাখা): pieces belong to their writers.',
            '# Content signals (https://contentsignals.org/): search = list and link pages in search results;',
            '# ai-input = use a page to answer a question, with a link; ai-train = train or fine-tune AI models.',
            '',
            'User-agent: *',
            'Content-Signal: search=yes, ai-input=yes, ai-train=no',
            'Disallow: /desk',
            'Disallow: /submit',
            'Disallow: /account',
            'Disallow: /search',
            'Disallow: /livewire',
            'Allow: /',
            '',
            '# Crawlers that only collect training data',
            ...collect(self::TRAINING_ONLY)->map(fn ($bot) => 'User-agent: '.$bot)->all(),
            'Disallow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Crawlers whose only job is collecting AI training data (D126). Search and answer crawlers are not listed. */
    public const TRAINING_ONLY = ['GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Meta-ExternalAgent', 'Bytespider', 'cohere-training-data-crawler'];

    /** A short guide to the site for AI agents (llmstxt.org, D126). Only real titles and links from the database. */
    public function llms()
    {
        $md = fn (string $t, string $u, string $note = '') => '- ['.str_replace(['[', ']'], '', $t).']('.$u.')'.($note !== '' ? ': '.$note : '');
        $recent = Post::published()->with('authors')->where('noindex', false)->latest('published_at')->limit(25)->get()
            ->map(fn (Post $p) => $md($p->title, $p->url(), $p->authors->pluck('display_name')->implode(', ')));

        $out = [
            '# হাতপাখা (Hatpakha)',
            '',
            '> Hatpakha is a Bengali literary web magazine, published since 2012 (ISSN '.\App\Seo\JsonLd::ISSN.'). It publishes poems, short stories, essays and travel writing, mostly in Bengali, with an English section. Writers submit for free and are never charged.',
            '',
            'Every piece belongs to its writer. You may find, quote briefly and link to pieces when answering questions; please always link to the piece and name its writer. Training AI models on these pieces is not allowed (see Content-Signal in /robots.txt).',
            '',
            'Any page is available as Markdown: send the request with the header `Accept: text/markdown`.',
            '',
            '## Sections',
            ...collect(Section::allLinks())->map(fn ($l) => $md($l[0], $l[1]))->all(),
            '',
            '## About the magazine',
            ...collect(Page::footerLinks())->map(fn ($l) => $md($l[0], $l[1]))->all(),
            $md('আর্কাইভ (archive, by year)', Url::to('/archive')),
            '',
            '## Recent pieces',
            ...$recent->all(),
            '',
            '## Optional',
            $md('Sitemap', url('/sitemap.xml'), 'every published piece'),
            $md('RSS feed', Url::to('/feed'), 'newest pieces'),
        ];

        return response(implode("\n", $out)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function index()
    {
        $pages = max(1, (int) ceil(Post::published()->count() / self::PER_FILE));
        $max = fn (...$dates) => collect($dates)->filter()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d))->max();
        $files = collect(range(1, $pages))->map(fn ($n) => ['sitemap-posts-'.$n.'.xml', $max(Post::published()->orderBy('id')->forPage($n, self::PER_FILE)->pluck('updated_at')->max())])
            ->merge([
                ['sitemap-sections.xml', $max(Section::where('is_active', true)->max('updated_at'), Post::published()->max('published_at'))],
                ['sitemap-authors.xml', $max(Author::where('published_count', '>=', 3)->max('updated_at'))],
                ['sitemap-issues.xml', $max(Issue::where('status', 'published')->max('updated_at'))],
                ['sitemap-pages.xml', $max(Page::published()->max('updated_at'), \App\Models\Collection::where('status', 'approved')->max('updated_at'), \App\Models\Serial::live()->max('updated_at'))],
            ]);

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
