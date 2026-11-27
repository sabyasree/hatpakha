<?php

namespace App\Http\Controllers;

use App\Models\GreetingSet;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\GreetingPlan;
use App\Support\JanunPlan;
use App\Support\Url;

/** D315: /janun/greetings/ and /janun/greetings/{slug}/: our own wishes, checked, with share pictures drawn on the phone. */
class GreetingController extends Controller
{
    public function index()
    {
        $sets = self::live();
        abort_if($sets->isEmpty(), 404);
        $url = Url::to('/janun/greetings');
        $seo = new Seo(image: asset('images/og/card-janun.jpg'), title: 'শুভেচ্ছা বার্তা: উৎসব ও জন্মদিনের বাংলা শুভেচ্ছা · Bengali Wishes', noindex: $sets->count() < 3,
            description: JanunPlan::clip('বাংলায় শুভেচ্ছা বার্তা: '.$sets->take(5)->map->name()->implode(', ').'। নতুন লেখা বার্তা, ছবি বানিয়ে হোয়াটসঅ্যাপে পাঠান। Bengali wishes with pictures.', 155),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['শুভেচ্ছা বার্তা', $url]])]);
        Ads::allow();

        return view('janun.greetings', ['sets' => $sets, 'seo' => $seo]);
    }

    public function show(string $slug)
    {
        $s = GreetingSet::where('slug', $slug)->first();
        abort_unless($s && $s->isLive(), 404);
        [$bn, $en, $festival] = GreetingPlan::OCCASIONS[$slug];
        $h1 = GreetingPlan::heading($slug);
        $card = is_file(public_path('images/og/greetings/'.$slug.'.jpg')) ? asset('images/og/greetings/'.$slug.'.jpg') : asset('images/og/card-janun.jpg');   // D326: each occasion's own card
        $seo = new Seo(image: $card, title: $h1.' · '.preg_replace('/\s*\(.*?\)/', '', $en),
            description: $s->seo_description ?: JanunPlan::clip($h1.': '.\App\Support\Bn::num((string) count($s->lines())).'টি নিজেদের লেখা বাংলা বার্তা, পরিবার, বন্ধু ও অফিসের জন্য, স্ট্যাটাস বা ছবি করে পাঠান। '.$en.'.', 155),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['শুভেচ্ছা বার্তা', Url::to('/janun/greetings')], [$bn, $s->url()]]),
                JsonLd::festivalPage($h1, $s->url(), $s->checked_at, $s->published_at, $s->seo_description)]);
        Ads::allow();
        $years = $festival ? \App\Support\FestivalPlan::livePages()->where(0, $festival)->pluck(1) : collect();
        $year = $years->filter(fn ($y) => $y >= (int) now()->year)->min() ?? $years->max();
        $festivalUrl = $year ? \App\Support\FestivalPlan::pageUrl($festival, (int) $year) : null;

        return view('janun.greeting', ['h1' => $h1, 'tone' => GreetingPlan::tone($slug), 's' => $s, 'bn' => $bn, 'en' => $en, 'festivalUrl' => $festivalUrl, 'others' => self::live()->reject(fn ($o) => $o->is($s))->take(6), 'seo' => $seo]);
    }

    /** Live sets, the occasions coming soonest first. */
    public static function live()
    {
        $order = array_flip(GreetingPlan::order(\Carbon\CarbonImmutable::now()));

        return GreetingSet::where('status', 'live')->get()->filter->isLive()->sortBy(fn ($s) => $order[$s->slug] ?? 99)->values();
    }
}
