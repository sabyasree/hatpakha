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

        return view('collection', compact('c', 'posts', 'others', 'seo', 'preview'));
    }
}
