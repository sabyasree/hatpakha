<?php

namespace App\Http\Controllers;

use App\Models\HolidayList;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\BanglaDate;
use App\Support\Bn;
use App\Support\JanunPlan;
use App\Support\Url;
use Carbon\CarbonImmutable;

/** D314: /janun/holidays/{year}/ and /janun/holidays/{west-bengal|bangladesh}-{year}/, from checked dates only. */
class HolidayController extends Controller
{
    public function year(int $year)
    {
        $lists = HolidayList::where('year', $year)->get()->filter->isLive()->values();
        abort_if($lists->isEmpty(), 404);
        $url = Url::to('/janun/holidays/'.$year);
        $seo = new Seo(image: asset('images/og/card-calendar.jpg'), title: 'Holiday List '.$year.' · সরকারি ছুটির তালিকা '.Bn::num((string) $year).': পশ্চিমবঙ্গ ও বাংলাদেশ',
            description: JanunPlan::clip('সরকারি ছুটির তালিকা '.Bn::num((string) $year).': '.$lists->map->name()->implode(' ও ').', সরকারি প্রজ্ঞাপন থেকে, প্রতিটি তারিখ মিলিয়ে দেখা। Holiday list '.$year.'.', 158),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['সরকারি ছুটি '.Bn::num((string) $year), $url]])]);
        Ads::allow();

        return view('janun.holidays-year', ['year' => $year, 'lists' => $lists, 'seo' => $seo]);
    }

    public function show(string $list)
    {
        abort_unless(preg_match('/^(west-bengal|bangladesh)-(20\d\d)$/', $list, $m), 404);
        $l = HolidayList::where(['country' => $m[1], 'year' => (int) $m[2]])->first();
        abort_unless($l && $l->isLive(), 404);
        $days = $l->holidays()->where('status', 'verified')->get();
        $held = $l->holidays()->where('status', 'held')->count();
        [$bnName, $en] = HolidayList::COUNTRIES[$l->country];
        $y = Bn::num((string) $l->year);
        $title = $l->country === 'bangladesh' ? 'সরকারি ছুটির তালিকা '.$y.' (বাংলাদেশ)' : 'পশ্চিমবঙ্গ সরকারি ছুটির তালিকা '.$y;
        $next = $days->first(fn ($h) => $h->day->gte(BanglaCalendarController::today()));
        $seo = new Seo(image: asset('images/og/card-calendar.jpg'), title: $en.' Holiday List '.$l->year.' · '.$title,
            description: JanunPlan::clip($title.': '.Bn::num((string) $days->count()).'টি ছুটির দিন, তারিখ, বার ও বাংলা তারিখসহ, সরকারি প্রজ্ঞাপন থেকে মিলিয়ে দেখা।'.($next ? ' পরের ছুটি: '.$next->name.', '.\App\Support\FestivalPlan::show(CarbonImmutable::parse($next->day->toDateString()), false).'।' : ''), 158),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['সরকারি ছুটি '.$y, Url::to('/janun/holidays/'.$l->year)], [$title, $l->url()]]),
                JsonLd::festivalPage($title, $l->url(), $l->checked_at)]);
        Ads::allow();
        $other = HolidayList::where('year', $l->year)->where('country', '!=', $l->country)->get()->first->isLive();

        return view('janun.holidays', ['l' => $l, 'days' => $days, 'held' => $held, 'title' => $title, 'next' => $next, 'other' => $other, 'seo' => $seo]);
    }

    /** "লম্বা ছুটি": the day touches the weekend (Saturday-Sunday in West Bengal; Friday-Saturday in Bangladesh). */
    public static function longWeekend(string $country, CarbonImmutable $d): bool
    {
        $weekend = $country === 'bangladesh' ? [5, 6] : [6, 0];

        return in_array($d->subDay()->dayOfWeek, $weekend, true) || in_array($d->addDay()->dayOfWeek, $weekend, true);
    }
}
