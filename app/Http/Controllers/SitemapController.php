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
            // D263: sign-in pages and the বাংলা/English switch are no reading (they were thousands of crawler hits a day)
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /email/',
            'Disallow: /language/',
            'Disallow: /csrf-token',
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
            $md('সব সংখ্যা (all issues)', Url::to('/sonkha')),
            ...(JanunController::anyLive() ? [$md('জেনে রাখুন (reference: Bengali proverbs, spelling, puja rules and mantras with meaning, school essays)', Url::to('/janun'))] : []),   // D301
            $md('বাংলা ক্যালেন্ডার (today\'s Bengali date in West Bengal and Bangladesh, months and festivals)', \App\Support\BanglaDate::url()),   // D303
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
                ['sitemap-issues.xml', $max(Issue::live()->max('updated_at'))],
                ['sitemap-pages.xml', $max(Page::published()->max('updated_at'), \App\Models\Collection::where('status', 'approved')->max('updated_at'), \App\Models\Serial::live()->max('updated_at'), \App\Models\JanunPage::live()->max('publish_at'))],
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
                ->when(ArchiveController::liveIssues()->isNotEmpty(), fn ($c) => $c->push(['loc' => Url::to('/sonkha'), 'lastmod' => now(), 'image' => null]))   // all issues (2026-11-12)
                ->push(['loc' => Url::to('/khela'), 'lastmod' => now()->startOfDay(), 'image' => null])   // D174: the daily word game
                ->push(['loc' => Url::to('/khela/dhadha'), 'lastmod' => now()->startOfDay(), 'image' => null])->push(['loc' => Url::to('/khela/dhadha/kothin'), 'lastmod' => now()->startOfDay(), 'image' => null])->push(['loc' => Url::to('/khela/probad'), 'lastmod' => now()->startOfDay(), 'image' => null])->push(['loc' => Url::to('/khela/aaj'), 'lastmod' => now()->startOfDay(), 'image' => null])->push(['loc' => Url::to('/khela/shobdojobdo'), 'lastmod' => now()->startOfDay(), 'image' => null])   // D177
                ->push(['loc' => Url::to('/khela/shobdojobdo/sohoj'), 'lastmod' => now()->startOfDay(), 'image' => null])->push(['loc' => Url::to('/khela/shobdojobdo/kothin'), 'lastmod' => now()->startOfDay(), 'image' => null])   // D201
                ->push(['loc' => Url::to('/kolom'), 'lastmod' => now()->startOfDay(), 'image' => null])   // D175: পাঠকের কলম
                ->when(WritersController::index() !== [], fn ($c) => $c->push(['loc' => Url::to('/lekhok'), 'lastmod' => null, 'image' => null]))   // A2: all writers
                ->when(\App\Support\Topics::live() !== [], fn ($c) => $c->push(['loc' => Url::to('/bishoy'), 'lastmod' => null, 'image' => null]))   // D293: topic pages
                ->merge(collect(array_keys(\App\Support\Topics::live()))->map(fn ($slug) => ['loc' => \App\Support\Topics::url($slug), 'lastmod' => null, 'image' => null]))
                ->merge(collect(array_keys(ShelfController::live()))->map(fn ($slug) => ['loc' => Url::to('/tak/'.$slug), 'lastmod' => null, 'image' => null]))   // C4: shelves
                ->merge(\App\Models\ReaderPrompt::where('status', 'published')->get()->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $p->published_at, 'image' => null]))
                ->merge(collect(array_keys(ArchiveController::yearCounts()))->map(fn ($y) => ['loc' => Url::to("/archive/{$y}"), 'lastmod' => null, 'image' => null])),
            $type === 'authors' => Author::where('published_count', '>=', 3)->get()->map(fn ($a) => ['loc' => $a->url(), 'lastmod' => $a->updated_at, 'image' => null]),
            $type === 'pages' => Page::published()->where('noindex', false)->get()->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at, 'image' => null])
                // D97: collection pages live with the pages
                ->concat(\App\Models\Collection::where('status', 'approved')->whereNotNull('slug')->get()->map(fn ($c) => ['loc' => $c->url(), 'lastmod' => $c->updated_at, 'image' => null]))
                ->concat(\App\Models\Serial::live()->get()->map(fn ($s) => ['loc' => $s->url(), 'lastmod' => $s->last_episode_at ? \Illuminate\Support\Carbon::parse($s->last_episode_at) : null, 'image' => null]))
                ->push(['loc' => Url::to('/app'), 'lastmod' => null, 'image' => null])   // D128
                ->push(['loc' => Url::to('/lekhar-dak'), 'lastmod' => now()->startOfDay(), 'image' => null])   // লেখার ডাক (2026-11-24)
                ->concat(self::janun()),   // D301: জেনে রাখুন, only what is live
            $type === 'issues' => Issue::live()->get()->map(fn ($i) => ['loc' => $i->url(), 'lastmod' => $i->updated_at, 'image' => null]),
        };

        return response()->view('sitemap.urls', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** D301: the জেনে রাখুন hub, its sections and pages, once something there is live. */
    private static function janun(): \Illuminate\Support\Collection
    {
        $times = \App\Support\FestivalPlan::pageLive()
            ? [['loc' => Url::to('/janun/puja/pujor-somoy'), 'lastmod' => \App\Models\FestivalTime::where('status', 'verified')->max('updated_at'), 'image' => null]] : [];   // D302
        // D308: each festival's yearly page and each year's list
        $fest = \App\Support\FestivalPlan::livePages();
        foreach ($fest as [$k, $y]) {
            $times[] = ['loc' => \App\Support\FestivalPlan::pageUrl($k, $y), 'lastmod' => \App\Models\FestivalTime::where(['festival' => $k, 'cycle' => $y, 'status' => 'verified'])->max('updated_at'), 'image' => null];
        }
        foreach ([now()->year, now()->year + 1] as $y) {   // D313: পূর্ণিমা / অমাবস্যা / একাদশী of a year, once six are known
            foreach (array_keys(\App\Support\LunarDays::TYPES) as $t) {
                if (count(\App\Support\LunarDays::year($t, $y)) >= 6) {
                    $times[] = ['loc' => \App\Support\LunarDays::url($t, $y), 'lastmod' => null, 'image' => null];
                }
            }
        }
        foreach ($fest->groupBy(1)->filter(fn ($g) => $g->count() >= 2)->keys() as $y) {
            $times[] = ['loc' => Url::to('/janun/puja/'.$y), 'lastmod' => null, 'image' => null];
        }
        // D303: the calendar (hub, this year and next, their months) is always there, and so is the জেনে রাখুন hub
        $cal = collect([['loc' => Url::to('/janun'), 'lastmod' => null, 'image' => null], ['loc' => \App\Support\BanglaDate::url(), 'lastmod' => now()->startOfDay(), 'image' => null]]);
        foreach (range(BanglaCalendarController::FIRST_YEAR, \App\Support\BanglaDate::bd(BanglaCalendarController::today())[0] + 1) as $y) {
            $cal->push(['loc' => \App\Support\BanglaDate::url($y), 'lastmod' => null, 'image' => null]);
            foreach (range(1, 12) as $m) {
                $cal->push(['loc' => \App\Support\BanglaDate::url($y, $m), 'lastmod' => null, 'image' => null]);
            }
        }
        $live = array_filter(JanunController::live(), fn ($pages, $s) => $pages->isNotEmpty() || ($s === 'puja' && $times !== []), ARRAY_FILTER_USE_BOTH);
        if ($live === []) {
            return $cal->concat($times);
        }
        $out = $cal;
        foreach ($live as $section => $pages) {
            $out->push(['loc' => Url::to('/janun/'.$section), 'lastmod' => null, 'image' => null]);
            foreach ($pages as $p) {
                $out->push(['loc' => $p->url(), 'lastmod' => $p->modifiedAt(), 'image' => null]);
            }
        }

        return $out->concat($times);
    }
}
