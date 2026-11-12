<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Collection;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Url;
use Illuminate\Http\Request;

/** D97: themed collections (সংকলন), each with its own page. Editors may preview a proposal (noindex). */
class CollectionController extends Controller
{
    public function index()
    {
        $collections = Collection::seasonOrder(Collection::where('status', 'approved')->whereNotNull('slug')->get());   // D100: in season first
        $seo = new Seo(title: 'সংকলন', description: 'হাতপাখার চোদ্দো বছরের লেখা থেকে বিষয় ধরে বাছাই করা সংকলন: ঋতু, উৎসব, মা-বাবা, প্রেম, ছেলেবেলা।',
            canonical: Url::to('/sonkolon'), noindex: $collections->isEmpty(),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['সংকলন', Url::to('/sonkolon')]])]);

        return view('collections', compact('collections', 'seo'));
    }

    /**
     * A collection read as a magazine (owner, 2026-11-12: বর্ষসেরা): the same page designer as the issues, the collection's
     * intro as its opening, its pieces by section, its writers, and the designer's cover. Same as an issue: noindex.
     */
    public function book(string $slug)
    {
        $c = Collection::where('slug', $slug)->where('status', 'approved')->firstOrFail();

        return $this->magazine($c, false);
    }

    /** Editors see a proposed collection's magazine before it is published. */
    public function bookPreview(Request $request, Collection $collection)
    {
        abort_unless($request->user()?->hasRole(Role::Editor), 403);

        return $this->magazine($collection, true)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function magazine(Collection $c, bool $preview)
    {
        $rest = $c->posts();
        abort_if($rest->count() < 1, 404);

        return response()->view('magazine', [
            'issue' => \App\Support\MagazineBook::fromCollection($c), 'pieces' => $rest, 'editorial' => null, 'rest' => $rest,
            'writers' => $rest->flatMap(fn ($p) => $p->authors->where('is_desk', false))->unique('id')->values(),
            'words' => \App\Support\MagazineBook::WORDS, 'preview' => $preview,
            'backUrl' => \App\Filament\Resources\Collections\CollectionResource::getUrl('edit', ['record' => $c], panel: 'desk'),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $c = Collection::where('slug', $slug)->firstOrFail();
        $preview = $c->status !== 'approved';
        abort_if($preview && ! ($request->user()?->hasRole(Role::Editor) ?? false), 404);
        $posts = $c->posts();
        $others = Collection::where('status', 'approved')->whereNotNull('slug')->whereKeyNot($c->id)->latest('approved_at')->limit(6)->get();
        $seo = new Seo(
            title: $c->title.' — হাতপাখার সংকলন',
            description: $c->intro ?: "হাতপাখার সংকলন: {$c->title}।",
            canonical: $c->url(),
            noindex: $preview,
            image: asset('images/og/card-collection.jpg'),
            jsonLd: [JsonLd::collection($c, $posts), JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['সংকলন', Url::to('/sonkolon')], [$c->title, $c->url()]])],
            shareTitle: $c->title.' | হাতপাখার সংকলন',
        );

        if (! $preview) {
            \App\Support\Ads::allow();   // D111
        }

        return view('collection', compact('c', 'posts', 'others', 'seo', 'preview'));
    }
}
