<?php

namespace App\Http\Controllers;

use App\Models\BanglaMonth;
use App\Models\FestivalTime;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Ads;
use App\Support\BanglaDate;
use App\Support\Bn;
use App\Support\FestivalPlan;
use App\Support\Url;
use Carbon\CarbonImmutable;

/** D303: বাংলা ক্যালেন্ডার: today, a month, a year; West Bengal (checked months only) and Bangladesh side by side. */
class BanglaCalendarController extends Controller
{
    public const FIRST_YEAR = 1433;   // the first year with pages (launch); earlier years are not offered

    private const EN_MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    public static function today(): CarbonImmutable
    {
        $t = CarbonImmutable::now('Asia/Kolkata');

        return CarbonImmutable::create($t->year, $t->month, $t->day);
    }

    /** "আজ ১০ অগ্রহায়ণ ১৪৩৩" for the home page (cached for the day), or null. */
    public static function todayLine(): ?string
    {
        $d = self::today();

        return cache()->remember('bangla:today:'.$d->toDateString(), 3600, function () use ($d) {
            $wb = BanglaDate::wb($d);
            $bd = BanglaDate::bd($d);

            return $wb ? 'আজ '.BanglaDate::text($wb).($wb !== $bd ? ' (বাংলাদেশে '.BanglaDate::text($bd).')' : '') : 'আজ '.BanglaDate::text($bd).' (বাংলাদেশ)';
        });
    }

    public function index()
    {
        $d = self::today();
        $wb = BanglaDate::wb($d);
        $bd = BanglaDate::bd($d);
        [$y, $m] = $wb ? [$wb[0], $wb[1]] : [$bd[0], $bd[1]];
        $title = 'বাংলা ক্যালেন্ডার '.Bn::num((string) $bd[0]).': আজ বাংলা কত তারিখ';
        $seo = new Seo(title: $title,
            description: 'আজ '.($wb ? BanglaDate::text($wb).' (পশ্চিমবঙ্গ), ' : '').BanglaDate::text($bd).' (বাংলাদেশ)। বাংলা মাস, তারিখ, ঋতু আর পুজো-পার্বণ, দুই বাংলার ক্যালেন্ডার পাশাপাশি।',
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['বাংলা ক্যালেন্ডার', BanglaDate::url()]])]);
        Ads::allow();

        return view('janun.calendar.index', ['d' => $d, 'wb' => $wb, 'bd' => $bd, 'grids' => self::grids($y, $m), 'year' => $y, 'month' => $m, 'seo' => $seo]);
    }

    public function year(int $year)
    {
        abort_unless(self::offered($year), 404);
        $rows = collect(range(1, 12))->map(function ($m) use ($year) {
            [$wbStart, $wbLen] = self::wbMonth($year, $m);
            $bdStart = BanglaDate::bdStart($year, $m);

            return ['month' => $m, 'wb' => $wbStart, 'wbLen' => $wbLen, 'bd' => $bdStart, 'bdLen' => BanglaDate::bdLengths($year + 594)[$m - 1]];
        });
        $from = $rows->first()['bd']->min($rows->first()['wb'] ?? $rows->first()['bd']);
        $to = BanglaDate::bdStart($year + 1, 1)->addDays(2);
        $gy = Bn::num((string) ($year + 593)).'-'.Bn::num(substr((string) ($year + 594), 2));
        $seo = new Seo(title: 'বাংলা ক্যালেন্ডার '.Bn::num((string) $year).' ('.$gy.'): মাস, তারিখ ও পার্বণ',
            description: 'বঙ্গাব্দ '.Bn::num((string) $year).': বৈশাখ থেকে চৈত্র, প্রতিটি মাস কবে শুরু, কত দিনের, পশ্চিমবঙ্গ আর বাংলাদেশের ক্যালেন্ডারে; পুজো-পার্বণ আর বিশেষ দিন।',
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['বাংলা ক্যালেন্ডার', BanglaDate::url()], ['বঙ্গাব্দ '.Bn::num((string) $year), BanglaDate::url($year)]])]);
        Ads::allow();

        return view('janun.calendar.year', ['year' => $year, 'rows' => $rows, 'days' => self::events($from, $to), 'seo' => $seo]);
    }

    public function month(int $year, string $slug)
    {
        $m = array_search($slug, BanglaDate::SLUGS, true);
        abort_unless(self::offered($year) && $m !== false, 404);
        $m++;
        $name = BanglaDate::MONTHS[$m - 1];
        $bdStart = BanglaDate::bdStart($year, $m);
        $span = self::EN_MONTHS[$bdStart->month - 1].'-'.self::EN_MONTHS[$bdStart->addDays(29)->month - 1].' '.Bn::num((string) $bdStart->addDays(29)->year);
        $seo = new Seo(title: $name.' '.Bn::num((string) $year).': বাংলা ক্যালেন্ডার ('.$span.')',
            description: $name.' '.Bn::num((string) $year).' মাসের বাংলা ক্যালেন্ডার: প্রতিদিনের বাংলা ও ইংরেজি তারিখ, পশ্চিমবঙ্গ আর বাংলাদেশ পাশাপাশি, আর এ মাসের পুজো-পার্বণ ও বিশেষ দিন।',
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['জেনে রাখুন', Url::to('/janun')], ['বাংলা ক্যালেন্ডার', BanglaDate::url()], ['বঙ্গাব্দ '.Bn::num((string) $year), BanglaDate::url($year)], [$name, BanglaDate::url($year, $m)]])]);
        Ads::allow();

        return view('janun.calendar.month', ['year' => $year, 'month' => $m, 'grids' => self::grids($year, $m), 'seo' => $seo]);
    }

    /** Years with pages: from launch to next year. */
    public static function offered(int $year): bool
    {
        return $year >= self::FIRST_YEAR && $year <= BanglaDate::bd(self::today())[0] + 1;
    }

    /** West Bengal month: [first day, length] when the month and the next are checked, else [null, null]. */
    public static function wbMonth(int $year, int $month): array
    {
        $rows = BanglaMonth::where('status', 'verified')->where(fn ($q) => $q->where(['year' => $year, 'month' => $month])
            ->orWhere(fn ($n) => $n->where(['year' => $month === 12 ? $year + 1 : $year, 'month' => $month === 12 ? 1 : $month + 1])))->orderBy('starts_on')->get();
        if ($rows->count() < 2) {
            return [null, null];
        }

        return [CarbonImmutable::parse($rows[0]->starts_on->toDateString()), (int) $rows[0]->starts_on->diffInDays($rows[1]->starts_on)];
    }

    /** One month in both calendars: ['wb' => grid|null, 'bd' => grid]. */
    public static function grids(int $year, int $month): array
    {
        [$wbStart, $wbLen] = self::wbMonth($year, $month);
        $bdStart = BanglaDate::bdStart($year, $month);
        $bdLen = BanglaDate::bdLengths($year + 594)[$month - 1];
        $events = self::events(($wbStart ?? $bdStart)->min($bdStart), ($wbStart ?? $bdStart)->max($bdStart)->addDays(32));
        $grid = fn (CarbonImmutable $start, int $len, string $other) => ['lead' => $start->dayOfWeek, 'days' => collect(range(0, $len - 1))->map(function ($i) use ($start, $other, $events) {
            $g = $start->addDays($i);

            return ['n' => $i + 1, 'g' => $g, 'today' => $g->equalTo(self::today()), 'other' => $other === 'bd' ? BanglaDate::bd($g) : BanglaDate::wb($g),
                'events' => $events[$g->toDateString()] ?? []];
        })->all()];

        return ['wb' => $wbStart ? $grid($wbStart, $wbLen, 'bd') : null, 'bd' => $grid($bdStart, $bdLen, 'wb')];
    }

    /** date => list of [name, link|null] between two days: checked festival days and the fixed days. */
    public static function events(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        $pages = \App\Models\JanunPage::live()->whereIn('key', collect(FestivalPlan::FESTIVALS)->pluck(1)->filter()->all())->get(['id', 'key', 'section', 'slug'])->keyBy('key');
        foreach (FestivalTime::where('status', 'verified')->whereBetween('day', [$from->toDateString(), $to->toDateString()])->orderBy('position')->get() as $t) {
            [$name, $pageKey] = FestivalPlan::FESTIVALS[$t->festival] ?? [null, null];
            if ($name) {
                $label = $t->festival === 'durga' || count(FestivalPlan::FESTIVALS[$t->festival][2]) > 1 ? $name.': '.$t->label : $name;
                $out[$t->day->toDateString()][] = [$label, isset($pages[$pageKey]) ? $pages[$pageKey]->url() : null];
            }
        }
        for ($g = $from; $g->lte($to); $g = $g->addDay()) {
            foreach (BanglaDate::fixedDays($g, BanglaDate::wb($g), BanglaDate::bd($g)) as $name) {
                $out[$g->toDateString()][] = [$name, null];
            }
        }

        return $out;
    }

    /** "২৬ নভেম্বর" */
    public static function short(CarbonImmutable $g): string
    {
        return Bn::num((string) $g->day).' '.self::EN_MONTHS[$g->month - 1];
    }
}
