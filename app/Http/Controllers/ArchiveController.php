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

        return $this->list($request, $section->name_bn, "হাতপাখার {$section->name_bn} — সব লেখা।", $posts, [
            JsonLd::breadcrumbs([['হাতপাখা', url('/').'/'], [$section->name_bn, $section->url()]]),
        ], kicker: 'বিভাগ');
    }

    public function tag(Request $request, string $slug)
    {
        $tag = Tag::where('slug', Slug::normalize($slug))->firstOrFail();
        $posts = $tag->posts()->published()->with('authors')->latest('published_at')->paginate(24);

        // Only curated tags with enough posts are indexed (docs/SEO.md §4).
        return $this->list($request, '#'.$tag->name, "“{$tag->name}” বিষয়ে হাতপাখার লেখা।", $posts, [],
            noindex: ! $tag->is_controlled || $posts->total() < 5, kicker: 'বিষয়');
    }

    public function author(Request $request, string $slug)
    {
        $author = Author::where('slug', Slug::normalize($slug))->firstOrFail();
        $posts = $author->posts()->published()->with(['authors', 'section'])->latest('published_at')->paginate(24);

        $seo = new Seo(
            title: $author->publicName(),
            description: $author->bio ?: "হাতপাখায় {$author->publicName()}-এর লেখা।",
            canonical: $request->query('page') > 1 ? $request->fullUrl() : $author->url(),
            noindex: ! $author->isIndexable() || $request->query('page') > 1,
            type: 'profile',
            jsonLd: [['@type' => 'ProfilePage', 'mainEntity' => JsonLd::person($author)]],
        );

        return view('author', compact('author', 'posts', 'seo'));
    }

    public function issue(Request $request, string $slug)
    {
        $issue = Issue::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $posts = $issue->posts()->published()->with(['authors', 'section'])->orderBy('section_id')->get();
        $seo = new Seo(title: $issue->title, description: $issue->editor_note ?? $issue->title, canonical: $issue->url(), jsonLd: [JsonLd::issue($issue)]);

        return view('issue', compact('issue', 'posts', 'seo'));
    }

    private function list(Request $request, string $title, string $description, $posts, array $jsonLd, bool $noindex = false, string $kicker = '')
    {
        $seo = new Seo(
            title: $title.($posts->currentPage() > 1 ? " — পাতা {$posts->currentPage()}" : ''),
            description: $description,
            canonical: $posts->currentPage() > 1 ? $request->fullUrl() : null,
            noindex: $noindex || $posts->currentPage() > 1,
            jsonLd: $jsonLd,
        );

        return view('archive', compact('title', 'posts', 'seo', 'kicker'));
    }
}
