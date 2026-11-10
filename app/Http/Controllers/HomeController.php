<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Comment;
use App\Models\Post;
use App\Seo\JsonLd;
use App\Seo\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        // Old WordPress "?p=123" links → the post's permanent URL.
        if (ctype_digit((string) $request->query('p'))) {
            // Archived posts still redirect: their own URL then answers 410/301 from the redirects table.
            $post = Post::whereIn('status', ['published', 'archived'])->where('legacy_wp_id', (int) $request->query('p'))->firstOrFail();

            return redirect($post->url(), 301);
        }
        if (ctype_digit((string) $request->query('page_id'))) {
            $page = \App\Models\Page::published()->where('legacy_wp_id', (int) $request->query('page_id'))->firstOrFail();

            return redirect($page->url(), 301);
        }
        // ?author=N revealed user names on WordPress (user enumeration): never map it. Old ?cat=, ?s=, ?m= → home.
        if ($request->hasAny(['author', 'cat', 's', 'm', 'attachment_id', 'tag'])) {
            return redirect(url('/').'/', 301);
        }

        // Cache only IDs/scalars: Laravel refuses to unserialize models from the cache (a good default).
        $ids = Cache::remember('home:v2:'.now()->toDateString(), now()->addMinutes(15), function () {
            $base = fn () => Post::published()->latest('published_at');
            // Owner (2026-11-09): "it can be anything, why only poem?" The daily piece rotates: a poem, then a story,
            // then other prose (essay, travel, heritage…), each chosen afresh per date. Never children's, editorial,
            // English or a serial episode out of order.
            $kind = ['poem', 'story', 'prose'][(int) now('Asia/Kolkata')->dayOfYear % 3];
            $pool = fn (string $kind) => $base()->where('language', 'bn')->whereNull('serial_id')
                ->whereHas('section', fn ($q) => $q->whereNotIn('slug', ['chhotoder', 'editorial', 'english']))
                ->when($kind === 'poem', fn ($q) => $q->where('format', 'poem'))
                ->when($kind === 'story', fn ($q) => $q->where('format', '!=', 'poem')->whereHas('section', fn ($s) => $s->where('slug', 'short-stories')))
                ->when($kind === 'prose', fn ($q) => $q->where('format', '!=', 'poem')->whereHas('section', fn ($s) => $s->where('slug', '!=', 'short-stories')))
                ->limit(80)->pluck('id');
            $poemIds = $pool($kind);
            if ($poemIds->isEmpty()) {
                $poemIds = $pool('poem');
            }

            return [
                // Poem of the day: deterministic per date, so the cached page and share cards agree.
                'poemOfDay' => $poemIds->isEmpty() ? null : $poemIds[crc32(now()->toDateString()) % $poemIds->count()],
                // "এই দিনে": a piece published this calendar week in an earlier year.
                'onThisDay' => Post::published()->whereYear('published_at', '<', now()->year)
                    ->whereMonth('published_at', now()->month)->get(['id', 'slug', 'published_at'])
                    ->filter(fn (Post $p) => abs($p->published_at->day - now()->day) <= 3)
                    ->sortBy(fn (Post $p) => crc32($p->slug.now()->toDateString()))->first()?->id,
                // "পাঠকের চিঠি": real reader comments, opted-in only (docs/SECURITY.md §8).
                'letters' => Comment::where('status', 'approved')->where('feature_opt_out', false)
                    ->latest()->limit(200)->get(['id', 'body'])
                    ->filter(fn ($c) => mb_strlen($c->body) >= 40 && mb_strlen($c->body) <= 260 && preg_match('/[\x{0985}-\x{09B9}]/u', $c->body))
                    ->take(4)->pluck('id')->all(),
                'counts' => [
                    'years' => now()->year - 2012,
                    'writers' => Author::where('published_count', '>', 0)->count(),
                    'pieces' => Post::published()->count(),
                ],
            ];
        });

        $load = fn (array $idList) => Post::with(['authors', 'section'])->whereKey($idList)->get()
            ->sortBy(fn ($p) => array_search($p->id, $idList))->values();

        // The owner-approved weekly picks (D48) take the place of the automatic ones.
        $pick = json_decode((string) \App\Models\Setting::get('home.pick'), true) ?: [];
        $otd = json_decode((string) \App\Models\Setting::get('home.on_this_day'), true) ?: [];
        if (($pickPost = ! empty($pick['post_id']) ? Post::published()->with('authors')->find($pick['post_id']) : null)) {
            $ids['poemOfDay'] = $pickPost->id;
        }
        if (! empty($otd[now('Asia/Kolkata')->toDateString()])) {
            $ids['onThisDay'] = Post::published()->whereKey($otd[now('Asia/Kolkata')->toDateString()])->value('id') ?? $ids['onThisDay'];
        }

        $data = [
            'pickNote' => $pickPost ? ($pick['note'] ?? null) : null,
            'poemOfDay' => $ids['poemOfDay'] ? Post::with('authors', 'section')->find($ids['poemOfDay']) : null,
            // Owner (2026-11-08): new pieces first, then archive pieces that change daily (+ spares for read ones).
            'stories' => $load(($rot = \App\Support\HomeRotation::today())['stories']),
            'travel' => $load($rot['travel']),
            'freshIds' => $rot['fresh'],
            'onThisDay' => $ids['onThisDay'] ? Post::with('authors')->find($ids['onThisDay']) : null,
            'letters' => Comment::with('post')->whereKey($ids['letters'])->get(),
            'counts' => $ids['counts'],
            // D66: writers honoured in the last 60 days.
            'honoured' => $honoured = Author::whereNotNull('honour_label')->where('honoured_at', '>=', now()->subDays(60))->latest('honoured_at')->limit(4)->get(),
            // D78: the spotlight near the top: this month's writer (else the latest honour, 45 days), with a piece and an exact line.
            'spotlight' => $spot = $honoured->where('honoured_at', '>=', now()->subDays(45))->sortByDesc(fn ($a) => [str_starts_with((string) $a->honour_label, 'মাসের লেখক') ? 1 : 0, $a->honoured_at])->first(),
            'spotlightPost' => $spot ? (Post::published()->find($spot->honour_post_id) ?? $spot->posts()->published()->latest('published_at')->first()) : null,
            'milestones' => \App\Support\Milestones::forHome(),
            // D97: the collection in season (else the newest)
            'collection' => $col = \App\Models\Collection::featured(),
            'collectionPosts' => $col ? $col->posts()->take(3) : collect(),
            'isPick' => (bool) $pickPost,
            // D108: running serials, the one with the newest episode first
            'serials' => \App\Models\Serial::live()->limit(3)->get(),
            // D101: ছোটদের হাতপাখা — the alphabet and the newest children's piece
            'kids' => $kids = \App\Models\Section::where('slug', \App\Models\Section::KIDS)->where('is_active', true)->first(),
            'kidsPosts' => $kids ? $kids->posts()->published()->with('authors')->latest('published_at')->limit(2)->get() : collect(),
        ];

        $seo = new Seo(
            title: 'হাতপাখা — বাংলা সাহিত্যের অনলাইন পত্রিকা',
            description: 'হাতপাখা: ২০১২ থেকে বাংলা কবিতা, গল্প, প্রবন্ধ ও ভ্রমণকথার অনলাইন পত্রিকা। ISSN 2321-3035। লেখা পাঠান বিনামূল্যে।',
            jsonLd: [JsonLd::organization(), JsonLd::website(), JsonLd::periodical()],
        );

        \App\Support\Ads::allow();   // D111

        return view('home', $data + ['seo' => $seo]);
    }
}
