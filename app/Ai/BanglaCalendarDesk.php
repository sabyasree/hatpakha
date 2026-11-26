<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\BanglaMonth;
use App\Models\Setting;
use App\Support\BanglaDate;
use Carbon\CarbonImmutable;

/**
 * D303: the West Bengal month starts are calculated here (BanglaDate::wbComputed) and checked against published
 * calendars: a month is shown only when a web search names the same first day (or one a day away, a সংক্রান্তি near
 * midnight, in which case the published day is kept) AND the server finds that date on at
 * least two independent pages it opens itself. A month that disagrees is held (the page says it is being checked).
 * Part of the "janun" handover job: one search an hour at most, three tries per Bengali year.
 */
final class BanglaCalendarDesk
{
    public static function run(CarbonImmutable $now): bool
    {
        [$current] = BanglaDate::bd($now);
        foreach ([$current, $current + 1] as $year) {
            self::seed($year);
        }
        if (Setting::get('calendar.bn.hour') === $now->format('Y-m-d H')) {
            return false;
        }
        foreach ([$current, $current + 1, $current + 2] as $year) {
            $tries = (int) Setting::get("calendar.bn.tries.{$year}", 0);
            if ($tries < 3 && BanglaMonth::where('year', $year)->where('status', '!=', 'verified')->exists()) {
                Setting::put('calendar.bn.hour', $now->format('Y-m-d H'));
                Setting::put("calendar.bn.tries.{$year}", $tries + 1);
                self::check($year);

                return true;
            }
        }

        return false;
    }

    /** Rows for a year's 12 months and the next year's বৈশাখ (whose first day ends this year's চৈত্র). */
    public static function seed(int $year): void
    {
        $starts = BanglaDate::wbComputed($year);
        foreach ($starts as $i => $d) {
            [$y, $m] = $i === 12 ? [$year + 1, 1] : [$year, $i + 1];
            BanglaMonth::firstOrCreate(['year' => $y, 'month' => $m], ['starts_on' => $d->toDateString()]);
        }
    }

    /**
     * A published বিশুদ্ধ সিদ্ধান্ত calendar that writes each month's first day as plain text (found 2026-11-26: it agreed
     * with our calculation on 23 of the 24 months of 1433-34; the other was শ্রাবণ ১৪৩৩, whose সংক্রান্তি falls 33 minutes
     * before midnight). Its month pages: https://myastrology.in/bangla-calendar/{slug}-{year}
     */
    public const SOURCE = 'https://myastrology.in/bangla-calendar/';

    public const SOURCE_SLUGS = ['baishakh', 'jyeshtha', 'ashadh', 'shrabon', 'bhadra', 'ashwin', 'kartik', 'agrahayan', 'poush', 'magh', 'falgun', 'chaitra'];

    private const BN_MONTHS = ['জানুয়ারি' => 1, 'ফেব্রুয়ারি' => 2, 'মার্চ' => 3, 'এপ্রিল' => 4, 'মে' => 5, 'জুন' => 6, 'জুলাই' => 7, 'আগস্ট' => 8, 'সেপ্টেম্বর' => 9, 'অক্টোবর' => 10, 'নভেম্বর' => 11, 'ডিসেম্বর' => 12];

    /** The first day of a month as the published calendar gives it ("১ অগ্রহায়ণ ১৪৩৩ বঙ্গাব্দ শুরু মঙ্গলবার ১৭ নভেম্বর"), or null. */
    public static function published(int $year, int $month, CarbonImmutable $near): ?CarbonImmutable
    {
        $text = JanunDesk::fetchText(self::SOURCE.self::SOURCE_SLUGS[$month - 1].'-'.$year);
        if ($text === null) {
            return null;
        }
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        if (! preg_match('/শুরু\s+\S+বার\s+([০-৯0-9]{1,2})\s+(\S+)/u', $text, $m)) {
            return null;
        }
        $mo = collect(self::BN_MONTHS)->first(fn ($n, $name) => \Normalizer::normalize($name, \Normalizer::FORM_C) === rtrim($m[2], ',।'));
        if (! $mo) {
            return null;
        }
        $day = (int) FestivalDesk::digits($m[1]);
        foreach ([$near->year - 1, $near->year, $near->year + 1] as $y) {
            $d = rescue(fn () => CarbonImmutable::create($y, $mo, $day), null, false);
            if ($d && abs($d->diffInDays($near, false)) <= 3) {
                return $d;
            }
        }

        return null;
    }

    /** The published month page that holds a Gregorian day (its day list carries each day's tithi times as text). */
    public static function sourceUrlFor(CarbonImmutable $day): ?string
    {
        [$y] = BanglaDate::bd($day);
        foreach ([$y - 1, $y, $y + 1] as $year) {
            $s = BanglaDate::wbComputed($year);
            for ($i = 0; $i < 12; $i++) {
                if ($day->gte($s[$i]) && $day->lt($s[$i + 1])) {
                    return self::SOURCE.self::SOURCE_SLUGS[$i].'-'.$year;
                }
            }
        }

        return null;
    }

    /** @return int how many months were confirmed */
    public static function check(int $year): int
    {
        // confirmed months are never checked again (a later search must not take back what a page confirmed)
        $computed = BanglaDate::wbComputed($year);
        $ok = 0;
        $unanswered = [];
        foreach (BanglaMonth::where('year', $year)->where('status', '!=', 'verified')->orderBy('month')->get() as $m) {
            $calc = $computed[$m->month - 1];
            $pub = self::published($year, $m->month, $calc);
            if (! $pub) {
                $unanswered[] = $m->id;   // the page did not answer: the web search below tries this month

                continue;
            }
            $diff = (int) $calc->diffInDays($pub, false);
            // the same পঞ্জিকা we follow: a day apart only near midnight, where its own day is kept
            $good = abs($diff) <= 1;
            $m->forceFill(['status' => $good ? 'verified' : 'held', 'starts_on' => $good ? $pub->toDateString() : $calc->toDateString(),
                'sources' => [self::SOURCE.self::SOURCE_SLUGS[$m->month - 1].'-'.$year],
                'note' => $good ? ($diff ? 'calculation said '.$calc->toDateString() : null) : 'published calendar says '.$pub->toDateString()])->save();
            $ok += $good ? 1 : 0;
        }
        $months = BanglaMonth::whereIn('id', $unanswered)->orderBy('month')->get();
        if ($months->isEmpty()) {
            AuditLog::record('calendar.checked', null, ['year' => $year, 'verified' => $ok, 'source' => 'published page']);
            \App\Support\BanglaDate::flush();

            return $ok;
        }
        $ask = $months->map(fn ($m) => '- '.BanglaDate::MONTHS[$m->month - 1].' ('.BanglaDate::EN[$m->month - 1].')')->implode("\n");
        [$d] = Claude::search('factcheck',
            'You check the West Bengal Bengali calendar (as in the Bisuddha Siddhanta panjika and the West Bengal Bengali calendars published online). '
            .'For each month of the given Bengali year, give the Gregorian date (YYYY-MM-DD) of its FIRST day (১ তারিখ) in West Bengal. Give the URLs of web pages (not hatpakha.com) that show these dates as plain text (for example a news article or a list of the year\'s months); not pages that draw a calendar with scripts or pictures. '
            .'If you are not sure of a month, leave it out. Answer ONLY with JSON: {"months":[{"month":"বৈশাখ","first_day":"YYYY-MM-DD"}],"sources":[{"url":"https://..."}]}',
            "<year>বঙ্গাব্দ {$year}</year>\n<months>\n".e($ask)."\n</months>", 5);
        if (! is_array($d)) {
            return 0;
        }
        $pages = collect((array) ($d['sources'] ?? []))->pluck('url')->filter()->unique()->take(6)
            ->reject(fn ($u) => str_contains(strtolower((string) parse_url((string) $u, PHP_URL_HOST)), 'hatpakha'))
            ->mapWithKeys(fn ($u) => [(string) $u => JanunDesk::fetchText((string) $u)])->filter()->map(fn ($t) => FestivalDesk::digits($t));
        $claimed = collect((array) ($d['months'] ?? []))->mapWithKeys(fn ($x) => [(string) ($x['month'] ?? '') => (string) ($x['first_day'] ?? '')]);
        foreach ($months as $m) {
            $name = \Normalizer::normalize(BanglaDate::MONTHS[$m->month - 1], \Normalizer::FORM_C);
            $said = $claimed->first(fn ($v, $k) => \Normalizer::normalize($k, \Normalizer::FORM_C) === $name);
            $saidDay = $said ? rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', $said), null, false) : null;
            // the published calendar wins over our calculation only by a day at most (a সংক্রান্তি near midnight), and only on two pages
            if (! $saidDay || abs($saidDay->diffInDays(CarbonImmutable::parse($m->starts_on->toDateString()), false)) > 1) {
                $m->forceFill(['status' => 'held', 'note' => $said ? 'published calendar says '.$said : 'not found'])->save();

                continue;
            }
            $found = $pages->filter(fn ($text) => FestivalDesk::hasDate($text, CarbonImmutable::parse($said)))->keys()
                ->unique(fn ($u) => preg_replace('/^www\./', '', strtolower((string) parse_url($u, PHP_URL_HOST))))->values();
            $differs = $said !== $m->starts_on->toDateString();
            // our own সংক্রান্তি calculation is one independent source: when the published day matches it, one page that
            // shows the date is the second (calendar sites mostly draw dates with JavaScript or pictures, so two pages with
            // the date as text were rarely found; live site, 2026-11-26). A published day that differs needs two pages.
            $good = $found->count() >= ($differs ? 2 : 1);
            $m->forceFill(['status' => $good ? 'verified' : 'held', 'sources' => $found->take(3)->all(), 'starts_on' => $good ? $said : $m->starts_on,
                'note' => $good ? ($differs ? 'calculation said '.$m->starts_on->toDateString() : null) : 'found on '.$found->count().' page(s)'])->save();
            $ok += $good ? 1 : 0;
        }
        AuditLog::record('calendar.checked', null, ['year' => $year, 'verified' => $ok, 'pages' => $pages->count()]);
        \App\Support\BanglaDate::flush();
        cache()->forget('bangla:today:'.CarbonImmutable::now('Asia/Kolkata')->toDateString());

        return $ok;
    }
}
