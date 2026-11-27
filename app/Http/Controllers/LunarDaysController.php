<?php

namespace App\Http\Controllers;

use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\BanglaDate;
use App\Support\Bn;
use App\Support\JanunPlan;
use App\Support\LunarDays;
use App\Support\Url;

/** D313: পূর্ণিমা / অমাবস্যা / একাদশী of a year, from the checked tithi days; live once six are known. */
class LunarDaysController extends Controller
{
    public function show(string $page)
    {
        abort_unless(preg_match('/^(purnima|amavasya|ekadashi)-(20\d\d)$/', $page, $m), 404);
        [$type, $year] = [$m[1], (int) $m[2]];
        $items = LunarDays::year($type, $year);
        abort_if(count($items) < 6, 404);
        [$name, , $en] = LunarDays::TYPES[$type];
        $url = LunarDays::url($type, $year);
        $nameY = $name.' '.Bn::num((string) $year);
        $next = collect($items)->first(fn ($e) => $e['end']->isFuture());
        $seo = new Seo(image: asset('images/og/card-calendar.jpg'), title: $en.' '.$year.' Dates & Time · '.$nameY.': তারিখ ও তিথির সময়',
            description: JanunPlan::clip($nameY.': সারা বছরের '.$name.'র তারিখ, তিথি শুরু ও শেষের সময় (ভারতের সময়), বাংলা মাসসহ।'.($next ? ' পরের '.$name.' '.\App\Support\FestivalPlan::show($next['day'], false).'।' : '').' '.$en.' '.$year.' list.', 158),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['পুজো-পার্বণ', Url::to('/janun/puja')], [$nameY, $url]]),
                JsonLd::festivalPage($nameY, $url, \App\Models\TithiDay::max('updated_at'))]);
        Ads::allow();
        $others = collect(LunarDays::TYPES)->keys()->reject(fn ($t) => $t === $type)->filter(fn ($t) => count(LunarDays::year($t, $year)) >= 6)->values();

        return view('janun.lunar', compact('type', 'year', 'items', 'name', 'nameY', 'en', 'next', 'others', 'seo'));
    }
}
