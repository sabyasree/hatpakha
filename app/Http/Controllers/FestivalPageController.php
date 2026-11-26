<?php

namespace App\Http\Controllers;

use App\Models\JanunPage;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\BanglaDate;
use App\Support\Bn;
use App\Support\FestivalPlan;
use App\Support\JanunPlan;
use App\Support\Url;
use Carbon\CarbonImmutable;

/**
 * D308: one page per festival per year (/janun/puja/saraswati-puja-2027/) and one list per year (/janun/puja/2027/).
 * Built from checked times only (D302/D304), written from the data (no AI text), and they appear by themselves each
 * year as the hourly job confirms that year's times. Once a page exists it stays.
 */
class FestivalPageController extends Controller
{
    public function show(string $festival)
    {
        abort_unless(preg_match('/^([a-z-]+)-(\d{4})$/', $festival, $m), 404);
        $key = collect(FestivalPlan::PAGES)->search(fn ($p) => $p[0] === $m[1]);
        $year = (int) $m[2];
        $rows = $key ? FestivalPlan::yearRows($key, $year) : null;
        abort_unless($rows, 404);
        [$name, $pageKey] = FestivalPlan::FESTIVALS[$key];
        [, $en] = FestivalPlan::PAGES[$key];
        $day = $rows->pluck('day')->filter()->min();
        $day = $day ? CarbonImmutable::parse($day->toDateString()) : null;
        $wb = $day ? BanglaDate::wb($day) : null;
        $bd = $day ? BanglaDate::bd($day) : null;
        $guide = JanunPage::live()->where('key', $pageKey)->first(['id', 'key', 'section', 'slug', 'kind', 'body']);
        $printable = $guide && collect((array) ($guide->body['sections'] ?? []))->contains(fn ($s) => ! empty($s['steps']) || ! empty($s['mantras']));
        $years = FestivalPlan::livePages()->filter(fn ($p) => $p[0] === $key)->pluck(1)->values();
        $url = FestivalPlan::pageUrl($key, $year);
        $nameY = $name.' '.Bn::num((string) $year);
        $when = $day ? FestivalPlan::show($day, false) : '';
        $seo = new Seo(title: $en.' '.$year.' Date & Time · '.$nameY.': তারিখ, তিথি ও সময়',
            description: JanunPlan::clip($nameY.' কবে: '.$when.($wb ? ' ('.BanglaDate::text($wb).', পশ্চিমবঙ্গ)' : '').'। তিথি শুরু ও শেষের সময় ভারত, বাংলাদেশ, ব্রিটেন, আমেরিকা, কানাডা, অস্ট্রেলিয়ার সময়ে। '.$en.' '.$year.' date and tithi time.', 158),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['পুজো-পার্বণ', Url::to('/janun/puja')], ['পুজো-পার্বণ '.Bn::num((string) $year), Url::to('/janun/puja/'.$year)], [$nameY, $url]]),
                JsonLd::festivalPage($nameY, $url, $rows->max('updated_at'))]);
        Ads::allow();

        return view('janun.festival', compact('key', 'year', 'rows', 'name', 'nameY', 'en', 'day', 'wb', 'bd', 'guide', 'printable', 'years', 'seo') + [
            'festivals' => FestivalPlan::upcoming($key)->filter(fn ($f) => $f[2] === $year)->values()->whenEmpty(fn () => collect([[$key, $name, $year, $rows]])),
            'others' => FestivalPlan::livePages()->filter(fn ($p) => $p[1] === $year && $p[0] !== $key)->values(),
        ]);
    }

    public function year(int $year)
    {
        $pages = FestivalPlan::livePages()->filter(fn ($p) => $p[1] === $year)->values();
        abort_if($pages->count() < 2, 404);
        $items = $pages->map(function ($p) use ($year) {
            $rows = FestivalPlan::yearRows($p[0], $year);
            $day = $rows->pluck('day')->filter()->min();
            $day = $day ? CarbonImmutable::parse($day->toDateString()) : null;

            return ['key' => $p[0], 'name' => FestivalPlan::FESTIVALS[$p[0]][0], 'day' => $day, 'wb' => $day ? BanglaDate::wb($day) : null, 'bd' => $day ? BanglaDate::bd($day) : null,
                'url' => FestivalPlan::pageUrl($p[0], $year)];
        });
        $url = Url::to('/janun/puja/'.$year);
        $seo = new Seo(title: 'Bengali Festivals '.$year.' · পুজো-পার্বণ '.Bn::num((string) $year).': তারিখের তালিকা',
            description: JanunPlan::clip(Bn::num((string) $year).' সালের বাঙালি পুজো-পার্বণের তারিখ: '.$items->pluck('name')->take(6)->implode(', ').'। বাংলা তারিখ, তিথি আর বিদেশের সময়। Bengali festival list '.$year.'.', 158),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['পুজো-পার্বণ', Url::to('/janun/puja')], ['পুজো-পার্বণ '.Bn::num((string) $year), $url]])]);
        Ads::allow();
        $years = FestivalPlan::livePages()->groupBy(1)->filter(fn ($g) => $g->count() >= 2)->keys()->map(fn ($y) => (int) $y)->sort()->values();

        return view('janun.festival-year', ['year' => $year, 'items' => $items, 'years' => $years, 'seo' => $seo]);
    }
}
