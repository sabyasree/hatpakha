<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Post;
use App\Models\Section;
use App\Support\Slug;
use App\Support\Url;

class FeedController extends Controller
{
    public function site() { return $this->rss('হাতপাখা', Url::to('/'), Post::published()); }

    public function section(string $slug)
    {
        $s = Section::where('slug', Slug::normalize($slug))->firstOrFail();

        return $this->rss('হাতপাখা · '.$s->name_bn, $s->url(), $s->posts()->published());
    }

    public function author(string $slug)
    {
        $a = Author::where('slug', Slug::normalize($slug))->firstOrFail();

        return $this->rss('হাতপাখা · '.$a->publicName(), $a->url(), $a->posts()->published());
    }

    private function rss(string $title, string $link, $query)
    {
        $posts = $query->with('authors')->latest('published_at')->limit(20)->get();

        return response()->view('feed', compact('title', 'link', 'posts'))->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
