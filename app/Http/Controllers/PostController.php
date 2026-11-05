<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Slug;

class PostController extends Controller
{
    public function __invoke(string $slug)
    {
        $post = Post::published()->with(['authors', 'section', 'issue', 'tags'])
            ->where('slug', Slug::normalize($slug))->first();

        if (! $post) {
            return app(LegacyController::class)->fallback(request());
        }

        $related = Post::published()->with('authors')
            ->where('section_id', $post->section_id)->whereKeyNot($post->id)
            ->latest('published_at')->limit(3)->get();

        $comments = $post->comments()->where('status', 'approved')->oldest()->limit(50)->get();

        $crumbs = [['হাতপাখা', url('/').'/']];
        if ($post->section) {
            $crumbs[] = [$post->section->name_bn, $post->section->url()];
        }
        $crumbs[] = [$post->title, $post->url()];

        $seo = new Seo(
            title: $post->metaTitle(),
            description: $post->metaDescription(),
            canonical: $post->url(),
            noindex: $post->noindex,
            image: $post->hasLargeImage() ? asset($post->featured_image_path) : null,
            type: 'article',
            lang: $post->language,
            jsonLd: [JsonLd::post($post), JsonLd::breadcrumbs($crumbs)],
            publishedAt: $post->published_at?->toAtomString(),
        );

        return view('post', compact('post', 'related', 'comments', 'seo', 'crumbs'));
    }
}
