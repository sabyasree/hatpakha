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

    /** @return int how many months were confirmed */
    public static function check(int $year): int
    {
        $months = BanglaMonth::where('year', $year)->orderBy('month')->get();
        $ask = $months->map(fn ($m) => '- '.BanglaDate::MONTHS[$m->month - 1].' ('.BanglaDate::EN[$m->month - 1].')')->implode("\n");
        [$d] = Claude::search('factcheck',
            'You check the West Bengal Bengali calendar (as in the Bisuddha Siddhanta panjika and the West Bengal Bengali calendars published online). '
            .'For each month of the given Bengali year, give the Gregorian date (YYYY-MM-DD) of its FIRST day (১ তারিখ) in West Bengal. Give the URLs of at least two independent web pages that show these dates (not hatpakha.com). '
            .'If you are not sure of a month, leave it out. Answer ONLY with JSON: {"months":[{"month":"বৈশাখ","first_day":"YYYY-MM-DD"}],"sources":[{"url":"https://..."}]}',
            "<year>বঙ্গাব্দ {$year}</year>\n<months>\n".e($ask)."\n</months>", 5);
        if (! is_array($d)) {
            return 0;
        }
        $pages = collect((array) ($d['sources'] ?? []))->pluck('url')->filter()->unique()->take(6)
            ->reject(fn ($u) => str_contains(strtolower((string) parse_url((string) $u, PHP_URL_HOST)), 'hatpakha'))
            ->mapWithKeys(fn ($u) => [(string) $u => JanunDesk::fetchText((string) $u)])->filter()->map(fn ($t) => FestivalDesk::digits($t));
        $claimed = collect((array) ($d['months'] ?? []))->mapWithKeys(fn ($x) => [(string) ($x['month'] ?? '') => (string) ($x['first_day'] ?? '')]);
        $ok = 0;
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
            $good = $found->count() >= 2;
            $differs = $said !== $m->starts_on->toDateString();
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
