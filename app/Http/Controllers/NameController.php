<?php

namespace App\Http\Controllers;

use App\Models\NamePage;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\Bn;
use App\Support\JanunPlan;
use App\Support\NamePlan;
use App\Support\Url;

/** D316: /janun/names/ and /janun/names/{boy|girl}-names-{letter}/: names whose meaning two dictionaries give. */
class NameController extends Controller
{
    public function index()
    {
        $live = NamePage::where('status', 'live')->get()->filter->isLive()->keyBy(fn ($p) => $p->gender.'|'.$p->letter);
        abort_if($live->isEmpty(), 404);
        $url = Url::to('/janun/names');
        $seo = new Seo(image: asset('images/og/card-janun.jpg'), title: 'বাংলা নাম ও অর্থ: ছেলে ও মেয়েদের নাম · Bengali Baby Names',
            description: JanunPlan::clip('ছেলে ও মেয়েদের বাংলা নাম, অর্থসহ, অক্ষর ধরে সাজানো। প্রতিটি নামের অর্থ দুটি অভিধানে মিলিয়ে দেখা। Bengali baby names with meanings.', 155),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['নাম ও অর্থ', $url]])]);
        Ads::allow();

        return view('janun.names', ['live' => $live, 'seo' => $seo]);
    }

    public function show(string $page)
    {
        abort_unless(preg_match('/^(boy|girl)-names-([a-z]+)$/', $page, $m) && isset(NamePlan::LETTERS[$m[2]]), 404);
        $p = NamePage::where(['gender' => $m[1], 'letter' => $m[2]])->first();
        abort_unless($p && $p->isLive(), 404);
        $names = $p->checked();
        $letter = NamePlan::LETTERS[$p->letter];
        [$gBn, $gEn] = NamePlan::GENDERS[$p->gender];
        $title = $p->title().', অর্থসহ';
        $en = NamePlan::EN_LETTER[$p->letter];
        $seo = new Seo(image: asset('images/og/card-janun.jpg'), title: $letter.' দিয়ে '.$gBn.' নাম ও অর্থ · Bengali '.$gEn.' Names with '.$en.' ('.$letter.')',
            description: JanunPlan::clip($letter.' দিয়ে '.$gBn.' '.Bn::num((string) count($names)).'টি নাম, অর্থ, ইংরেজি বানান ও উৎসসহ: '.collect($names)->take(5)->pluck('bn')->implode(', ').'। প্রতিটি অর্থ দুটি অভিধানে মিলিয়ে দেখা।', 155),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['নাম ও অর্থ', Url::to('/janun/names')], [$p->title(), $p->url()]]),
                JsonLd::festivalPage($title, $p->url(), $p->checked_at, $p->publish_at), JsonLd::nameList($title, $p->url(), $names)]);
        Ads::allow();
        $same = NamePage::where('status', 'live')->where('gender', $p->gender)->get()->filter->isLive()
            ->sortBy(fn ($o) => array_search($o->letter, array_keys(NamePlan::LETTERS), true))->values();
        $other = NamePage::where(['gender' => $p->gender === 'boy' ? 'girl' : 'boy', 'letter' => $p->letter])->first();

        return view('janun.name', ['en' => $en, 'p' => $p, 'names' => $names, 'title' => $title, 'same' => $same, 'other' => $other?->isLive() ? $other : null, 'seo' => $seo]);
    }
}
