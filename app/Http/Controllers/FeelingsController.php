<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Feelings;
use App\Support\Url;

/** D232: "পাঠকের পছন্দ": each month's readers' choice, and the pieces readers answered most, by how the piece left them. */
class FeelingsController extends Controller
{
    public function index()
    {
        $winners = Post::published()->with('authors')->whereNotNull('readers_choice')->orderByDesc('readers_choice')->limit(12)->get();
        $lists = collect(array_keys(Feelings::LIST))->mapWithKeys(fn ($k) => [$k => Feelings::top($k)])->filter(fn ($l) => $l->isNotEmpty());
        $seo = new Seo(title: 'পাঠকের পছন্দ', description: 'হাতপাখার পাঠকেরা কোন লেখা পড়ে কী অনুভব করেছেন: যে লেখা মন ছুঁয়েছে, ভাবিয়েছে, হাসিয়েছে, চমকে দিয়েছে, মনে থেকে গেছে।',
            canonical: Url::to('/pathoker-pochhondo'), noindex: $winners->isEmpty() && $lists->isEmpty(),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['পাঠকের পছন্দ', Url::to('/pathoker-pochhondo')]])]);

        return view('feelings', compact('winners', 'lists', 'seo'));
    }
}
