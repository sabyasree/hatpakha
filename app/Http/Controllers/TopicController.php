<?php

namespace App\Http\Controllers;

use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Topics;
use App\Support\Url;
use Illuminate\Http\Request;

/** D293: /bishoy/ (all topics) and /bishoy/{slug}/ (one topic's pieces). */
class TopicController extends Controller
{
    public const PER_PAGE = 24;

    public function index()
    {
        $live = Topics::live();
        $seo = new Seo(
            title: 'বিষয় ধরে পড়ুন: ভূতের গল্প, প্রেমের কবিতা আর আরও',
            description: 'হাতপাখার লেখা বিষয় ধরে: ভূতের গল্প, প্রেমের গল্প, রহস্য গল্প, প্রেমের কবিতা, মা-কে নিয়ে লেখা, পুজোর লেখা, পাহাড়ের ভ্রমণ আর আরও অনেক কিছু।',
            noindex: $live === [],
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['বিষয়', Url::to('/bishoy')]])],
        );

        return view('topics', ['live' => $live, 'seo' => $seo]);
    }

    public function show(Request $request, string $slug)
    {
        [$name, , $title, $intro] = Topics::LIST[$slug];
        $posts = Topics::query($slug)->with(['authors', 'section'])->paginate(self::PER_PAGE)->withPath(Topics::url($slug));
        abort_if($posts->currentPage() > 1 && $posts->isEmpty(), 404);

        $seo = new Seo(
            title: $title.($posts->currentPage() > 1 ? " — পাতা {$posts->currentPage()}" : ''),
            description: $intro,
            canonical: $posts->currentPage() > 1 ? $request->fullUrl() : null,
            // A topic with too few pieces stays out of Google (thin page) until the archivist or the desk fills it.
            noindex: $posts->total() < Topics::MIN || $posts->currentPage() > 1,
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['বিষয়', Url::to('/bishoy')], [$name, Topics::url($slug)]])],
        );
        if ($posts->total() > 0) {
            \App\Support\Ads::allow();   // D111: lists with pieces carry ads
        }

        return view('archive', ['title' => $name, 'posts' => $posts, 'seo' => $seo, 'kicker' => 'বিষয়', 'intro' => $intro, 'topic' => $slug]);
    }
}
