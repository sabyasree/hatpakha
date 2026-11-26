<?php

namespace App\Http\Controllers;

use App\Models\JanunPage;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\JanunPlan;
use App\Support\Url;

/** D301: /janun/ (all), /janun/{section}/ and /janun/{section}/{slug}/. Only published pages exist for readers. */
class JanunController extends Controller
{
    /** @return array<string, \Illuminate\Support\Collection> section => its live pages */
    public static function live(): array
    {
        $pages = JanunPage::live()->orderBy('publish_at')->get(['id', 'section', 'slug', 'title', 'kind', 'seo_description', 'publish_at', 'checked_at', 'body']);

        return collect(JanunPlan::SECTIONS)->keys()->mapWithKeys(fn ($s) => [$s => $pages->where('section', $s)->values()])->all();
    }

    public function index()
    {
        $live = self::live();
        abort_if(collect($live)->flatten()->isEmpty(), 404);
        $seo = new Seo(title: 'জেনে রাখুন: প্রবাদ, বাগধারা, পুজোর মন্ত্র, ভাবসম্প্রসারণ',
            description: 'হাতপাখার জেনে রাখুন: বাংলা প্রবাদ-প্রবচন ও বাগধারার অর্থ, শুদ্ধ বানান, পুজোর নিয়ম ও মন্ত্র অর্থসহ, আর ছাত্রছাত্রীদের জন্য ভাবসম্প্রসারণ ও রচনা।',
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')]])]);
        Ads::allow();

        return view('janun.index', ['live' => $live, 'seo' => $seo]);
    }

    public function section(string $section)
    {
        $pages = self::live()[$section] ?? collect();
        abort_if($pages->isEmpty(), 404);
        [$name, $kicker, $intro] = JanunPlan::SECTIONS[$section];
        $seo = new Seo(title: $name.': '.$kicker, description: $intro,
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], [$name, Url::to('/janun/'.$section)]])]);
        Ads::allow();

        return view('janun.section', ['section' => $section, 'name' => $name, 'intro' => $intro, 'pages' => $pages, 'live' => self::live(), 'seo' => $seo]);
    }

    public function page(string $section, string $slug)
    {
        $p = JanunPage::live()->where('section', $section)->where('slug', $slug)->firstOrFail();
        $seo = new Seo(title: $p->seo_title ?: $p->title, description: (string) $p->seo_description, type: 'article',
            jsonLd: array_merge([JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], [$p->sectionName(), Url::to('/janun/'.$section)], [$p->title, $p->url()]])], JsonLd::janunPage($p)),
            publishedAt: $p->publish_at?->toIso8601String());
        Ads::allow();
        $siblings = JanunPage::live()->where('section', $section)->whereKeyNot($p->id)->orderByDesc('publish_at')->limit(4)->get(['id', 'section', 'slug', 'title']);

        return view('janun.page', ['p' => $p, 'b' => (array) $p->body, 'siblings' => $siblings, 'seo' => $seo]);
    }
}
