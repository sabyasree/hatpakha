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
        $posts = $section->posts()->published()->with('authors')->latest('published_at')->paginate(24);

        // D96: an empty new section still has a page: its introduction and an invitation to write (kept out of Google until it has a piece).
        return $this->list($request, $section->name_bn, $section->about ?: "হাতপাখার {$section->name_bn} — সব লেখা।", $posts, [
            JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], [$section->name_bn, $section->url()]]),
        ], noindex: $posts->total() === 0, kicker: 'বিভাগ', section: $section);
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

        return view('author', compact('author', 'posts', 'seo', 'sections', 'since', 'isOwner'));
    }

    /**
     * Reader archive (owner request): every year with its count, plus the issues.
     * Years/counts are cached as scalars only (D18).
     */
    public function archiveIndex()
    {
        $years = self::yearCounts();
        $issues = Issue::where('status', 'published')->orderByDesc('slug')->get();
        $sections = \App\Models\Section::where('is_active', true)->orderBy('sort')->get();
        $total = array_sum($years);
        $seo = new Seo(
            title: 'আর্কাইভ',
            description: 'হাতপাখার '.\App\Support\Bn::num($total).'টি লেখা, ২০১২ থেকে আজ পর্যন্ত — বছর, সংখ্যা আর বিভাগ ধরে খুঁজুন।',
            canonical: \App\Support\Url::to('/archive'),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], ['আর্কাইভ', \App\Support\Url::to('/archive')]])],
        );

        // The archivist's approved themed collections (D55), newest first.
        $collections = \App\Models\Collection::where('kind', 'theme')->where('status', 'approved')->whereNotNull('slug')->latest('approved_at')->limit(6)->get();

        return view('archive-index', compact('years', 'issues', 'sections', 'total', 'seo', 'collections'));
    }

    public function archiveYear(int $year)
    {
        $years = self::yearCounts();
        abort_unless(isset($years[$year]), 404);
        $posts = Post::published()->with(['authors', 'section'])
            ->whereBetween('published_at', ["{$year}-01-01 00:00:00", "{$year}-12-31 23:59:59"])
            ->orderBy('published_at')->get();
        $months = $posts->groupBy(fn ($p) => (int) $p->published_at->format('n'));
        $y = \App\Support\Bn::year($year);
        $seo = new Seo(
            title: "{$y} সালের লেখা — আর্কাইভ",
            description: "{$y} সালে হাতপাখায় প্রকাশিত ".\App\Support\Bn::num($posts->count()).'টি কবিতা, গল্প ও প্রবন্ধ, মাস ধরে।',
            canonical: \App\Support\Url::to("/archive/{$year}"),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], ['আর্কাইভ', \App\Support\Url::to('/archive')], [$y, \App\Support\Url::to("/archive/{$year}")]])],
        );

        return view('archive-year', compact('year', 'years', 'months', 'posts', 'seo'));
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

        return view('issue', compact('issue', 'posts', 'seo'));
    }

    private function list(Request $request, string $title, string $description, $posts, array $jsonLd, bool $noindex = false, string $kicker = '', ?Section $section = null)
    {
        $seo = new Seo(
            title: $title.($posts->currentPage() > 1 ? " — পাতা {$posts->currentPage()}" : ''),
            description: $description,
            canonical: $posts->currentPage() > 1 ? $request->fullUrl() : null,
            noindex: $noindex || $posts->currentPage() > 1,
            jsonLd: $jsonLd,
        );

        return view('archive', compact('title', 'posts', 'seo', 'kicker', 'section'));
    }

    /** The issue as a book (D68). Same text as the pieces' own pages, so noindex with the issue as canonical. */
    public function issueBook(string $slug)
    {
        $issue = Issue::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $pieces = \App\Support\IssueBook::pieces($issue);
        abort_if($pieces->isEmpty(), 404);
        $seo = new Seo(title: $issue->title.' (বই)', canonical: $issue->url(), noindex: true);

        return view('issue-book', compact('issue', 'pieces', 'seo'));
    }
}
