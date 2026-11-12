<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Issue;
use App\Models\Post;
use App\Models\Section;
use App\Models\Tag;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Slug;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function section(Request $request, string $slug)
    {
        $section = Section::where('slug', Slug::normalize($slug))->where('is_active', true)->first();
        if (! $section) {
            return app(LegacyController::class)->fallback($request);  // merged old categories → 301
        }
        if ($section->slug === 'dharabahik') {
            return redirect()->to(\App\Support\Url::to('/dharabahik'), 301);   // D108: serials have their own page
        }
        // D101: the children's section can be narrowed by age (?age=6-8); filtered lists stay out of Google.
        $age = $section->slug === Section::KIDS && array_key_exists((string) $request->query('age'), \App\Ai\KidsTeacher::AGES) ? (string) $request->query('age') : null;
        $posts = $section->posts()->published()->with('authors')->when($age, fn ($q) => $q->where('age_band', $age))
            ->latest('published_at')->paginate(24)->withQueryString();

        // D96: an empty new section still has a page: its introduction and an invitation to write (kept out of Google until it has a piece).
        return $this->list($request, $section->name_bn, $section->about ?: "হাতপাখার {$section->name_bn} — সব লেখা।", $posts, [
            JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], [$section->name_bn, $section->url()]]),
        ], noindex: $posts->total() === 0 || $age, kicker: 'বিভাগ', section: $section, age: $age);
    }

    public function tag(Request $request, string $slug)
    {
        $tag = Tag::where('slug', Slug::normalize($slug))->first();
        if (! $tag) {
            return app(LegacyController::class)->fallback($request);
        }
        $posts = $tag->posts()->published()->with('authors')->latest('published_at')->paginate(24);

        // Only curated tags with enough posts are indexed (docs/SEO.md §4).
        return $this->list($request, '#'.$tag->name, "“{$tag->name}” বিষয়ে হাতপাখার লেখা।", $posts, [],
            noindex: ! $tag->is_controlled || $posts->total() < 5, kicker: 'বিষয়');
    }

    public function author(Request $request, string $slug)
    {
        $author = Author::where('slug', Slug::normalize($slug))->first();
        if (! $author) {
            return app(LegacyController::class)->fallback($request);  // e.g. old house-account author URLs → 301
        }
        $posts = $author->posts()->published()->with(['authors', 'section'])->latest('published_at')->paginate(24);

        $seo = new Seo(
            title: $author->publicName(),
            description: $author->bio ?: "হাতপাখায় {$author->publicName()}-এর লেখা।",
            canonical: $request->query('page') > 1 ? $request->fullUrl() : $author->url(),
            noindex: ! $author->isIndexable() || $request->query('page') > 1,
            type: 'profile',
            jsonLd: [['@type' => 'ProfilePage', 'mainEntity' => JsonLd::person($author)]],
        );

        // D59: what the header shows: sections they write in, writing since, links.
        // Plain post query (the relation would add pivot columns, which strict MySQL refuses in GROUP BY).
        $all = fn () => \App\Models\Post::published()->whereHas('authors', fn ($q) => $q->whereKey($author->id));
        $sections = $all()->join('sections', 'sections.id', '=', 'posts.section_id')
            ->selectRaw('sections.name_bn as name, sections.slug as slug, count(*) as n')->groupBy('sections.name_bn', 'sections.slug')->orderByDesc('n')->limit(5)->get();
        $since = $all()->min('posts.published_at');
        $isOwner = $author->user_id && $request->user()?->id === $author->user_id;

        if ($posts->total() > 0) {
            \App\Support\Ads::allow();   // D111
        }

        return view('author', compact('author', 'posts', 'seo', 'sections', 'since', 'isOwner'));
    }

    /**
     * Reader archive (owner request): every year with its count, plus the issues.
     * Years/counts are cached as scalars only (D18).
     */
    public function archiveIndex()
    {
        $years = self::yearCounts();
        $total = array_sum($years);
        // Owner-approved redesign (2026-11-12): issues first (this month's large, the five before it small), then
        // collections, then a timeline of years (only years with published pieces), then sections.
        $issues = self::liveIssues();
        $latestIssue = $issues->first();
        $olderIssues = $issues->slice(1, 5)->values();
        $sections = \App\Models\Section::where('is_active', true)->orderBy('sort')->get();
        $seo = new Seo(
            title: 'আর্কাইভ',
            description: 'হাতপাখার '.\App\Support\Bn::num($total).'টি লেখা, ২০১২ থেকে আজ পর্যন্ত — সংখ্যা, সংকলন, বছর আর বিভাগ ধরে খুঁজুন।',
            canonical: \App\Support\Url::to('/archive'),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], ['আর্কাইভ', \App\Support\Url::to('/archive')]])],
        );

        // The archivist's approved themed collections (D55), in season first (D100).
        $collections = \App\Models\Collection::seasonOrder(\App\Models\Collection::where('kind', 'theme')->where('status', 'approved')->whereNotNull('slug')->get())->take(4);

        return view('archive-index', compact('years', 'issues', 'latestIssue', 'olderIssues', 'sections', 'total', 'seo', 'collections'));
    }

    /** /sonkha/: every issue, newest first, grouped by year (owner, 2026-11-12). */
    public function issues()
    {
        $issues = self::liveIssues();
        $byYear = $issues->groupBy(fn (Issue $i) => (int) ($i->published_at ?? $i->created_at)->format('Y'));
        $seo = new Seo(
            title: 'সব সংখ্যা',
            description: 'হাতপাখার সব সংখ্যা, নতুন থেকে পুরনো: প্রতিটি সংখ্যা পত্রিকার মতো পড়া যায়।',
            canonical: \App\Support\Url::to('/sonkha'),
            noindex: $issues->isEmpty(),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], ['আর্কাইভ', \App\Support\Url::to('/archive')], ['সব সংখ্যা', \App\Support\Url::to('/sonkha')]])],
        );

        return view('issues', compact('issues', 'byYear', 'seo'));
    }

    /** Published issues that are out, newest first, with the number of their live pieces. */
    public static function liveIssues(): \Illuminate\Support\Collection
    {
        return Issue::where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->withCount(['posts' => fn ($q) => $q->published()])->get()
            ->sortByDesc(fn (Issue $i) => $i->published_at?->timestamp ?? 0)->values();
    }

    public function archiveYear(Request $request, int $year)
    {
        $years = self::yearCounts();
        abort_unless(isset($years[$year]), 404);
        $all = Post::published()->with(['authors', 'section'])
            ->whereBetween('published_at', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"])
            ->orderBy('published_at')->get();

        // Owner-approved redesign: narrow the year to one section (?section=poetry). A filtered list stays out of
        // Google and points to the whole year (docs/SEO.md: filtered lists).
        $sectionCounts = $all->filter(fn ($p) => $p->section)->groupBy(fn ($p) => $p->section->slug)
            ->map(fn ($g) => ['section' => $g->first()->section, 'n' => $g->count()])->sortByDesc('n');
        $filter = $sectionCounts->has((string) $request->query('section')) ? (string) $request->query('section') : null;
        $posts = $filter ? $all->filter(fn ($p) => $p->section?->slug === $filter)->values() : $all;
        $months = $posts->groupBy(fn ($p) => (int) $p->published_at->format('n'));

        // "এ বছরের বাছাই": the year's four most-read pieces with a cover (only the ids are cached, D18).
        $pickIds = \Illuminate\Support\Facades\Cache::remember("archive:picks:{$year}:v1", 86400, fn () => \Illuminate\Support\Facades\DB::table('post_views')
            ->whereIn('post_id', $all->whereNotNull('featured_image_path')->pluck('id'))
            ->selectRaw('post_id, sum(views) as n')->groupBy('post_id')->orderByDesc('n')->limit(4)->pluck('post_id')->all());
        $picks = $filter ? collect() : $all->whereIn('id', $pickIds)->sortBy(fn ($p) => array_search($p->id, $pickIds))->values();

        $keys = array_keys($years);   // newest first
        $i = array_search($year, $keys, true);
        $older = $keys[$i + 1] ?? null;
        $newer = $i > 0 ? $keys[$i - 1] : null;

        $y = \App\Support\Bn::year($year);
        $seo = new Seo(
            title: "{$y} সালের লেখা — আর্কাইভ",
            description: "{$y} সালে হাতপাখায় প্রকাশিত ".\App\Support\Bn::num($all->count()).'টি কবিতা, গল্প ও প্রবন্ধ, মাস ধরে।',
            canonical: \App\Support\Url::to("/archive/{$year}"),
            noindex: $filter !== null,
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], ['আর্কাইভ', \App\Support\Url::to('/archive')], [$y, \App\Support\Url::to("/archive/{$year}")]])],
        );

        return view('archive-year', compact('year', 'years', 'months', 'posts', 'all', 'seo', 'sectionCounts', 'filter', 'picks', 'older', 'newer'));
    }

    /** @return array<int,int> year → published post count, newest first */
    public static function yearCounts(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('archive:years:v1', 3600, function () {
            $counts = [];
            foreach (Post::published()->pluck('published_at') as $d) {
                $y = (int) \Illuminate\Support\Carbon::parse($d)->format('Y');
                $counts[$y] = ($counts[$y] ?? 0) + 1;
            }
            krsort($counts);

            return $counts;
        });
    }

    public function issue(Request $request, string $slug)
    {
        $issue = Issue::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $posts = $issue->posts()->published()->with(['authors', 'section'])->orderBy('section_id')->get();
        $seo = new Seo(title: $issue->title, description: $issue->editor_note ?? $issue->title, canonical: $issue->url(), jsonLd: [JsonLd::issue($issue)]);

        \App\Support\Ads::allow();   // D111

        return view('issue', compact('issue', 'posts', 'seo'));
    }

    private function list(Request $request, string $title, string $description, $posts, array $jsonLd, bool $noindex = false, string $kicker = '', ?Section $section = null, ?string $age = null)
    {
        $seo = new Seo(
            title: $title.($posts->currentPage() > 1 ? " — পাতা {$posts->currentPage()}" : ''),
            description: $description,
            canonical: $age ? $section?->url() : ($posts->currentPage() > 1 ? $request->fullUrl() : null),
            noindex: $noindex || $posts->currentPage() > 1,
            jsonLd: $jsonLd,
        );

        // D111: lists with pieces carry ads; never the children's section or an empty page
        if ($posts->total() > 0 && $section?->slug !== Section::KIDS) {
            \App\Support\Ads::allow();
        }

        return view('archive', compact('title', 'posts', 'seo', 'kicker', 'section', 'age'));
    }

    /**
     * Desk preview of an issue's magazine before it is published (owner, 2026-11-12): the pieces chosen for it (attached
     * or in the AI editor's proposal, whatever their state), the editorial even as a draft, the designer's proposed cover.
     * Editors only, never cached, never indexed.
     */
    public function issuePreview(Request $request, Issue $issue)
    {
        abort_unless($request->user()?->hasRole(\App\Enums\Role::Editor), 403);
        $ids = (array) ($issue->ai_plan['post_ids'] ?? []);
        $editorial = $issue->editorialPost()?->loadMissing('authors', 'section');
        $rest = Post::with(['authors', 'section'])->where(fn ($q) => $q->where('issue_id', $issue->id)->orWhereIn('id', $ids))
            ->whereNotIn('status', ['rejected', 'archived'])
            ->when($editorial, fn ($q) => $q->whereKeyNot($editorial->id))
            ->orderByRaw('published_at is null')->orderBy('published_at')->get();
        $pieces = ($editorial ? collect([$editorial]) : collect())->concat($rest)->values();
        $writers = $rest->flatMap(fn ($p) => $p->authors->where('is_desk', false))->unique('id')->values();

        return response()->view('magazine', compact('issue', 'pieces', 'editorial', 'rest', 'writers') + ['preview' => true])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** The issue as a book (D68). Same text as the pieces' own pages, so noindex with the issue as canonical. */
    public function issueBook(string $slug)
    {
        $issue = Issue::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $pieces = \App\Support\IssueBook::pieces($issue);
        abort_if($pieces->isEmpty(), 404);
        // Owner (2026-11-12): the issue as a real magazine, full screen, pages that turn in 3D.
        $editorial = $issue->liveEditorial();
        $rest = $pieces->reject(fn ($p) => $editorial && $p->id === $editorial->id)->values();
        $writers = $rest->flatMap(fn ($p) => $p->authors->where('is_desk', false))->unique('id')->values();

        return view('magazine', compact('issue', 'pieces', 'editorial', 'rest', 'writers'));
    }
}
