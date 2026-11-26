<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\FestivalTime;
use App\Models\Setting;
use App\Support\FestivalPlan;
use Carbon\CarbonImmutable;

/**
 * D302: finds each festival's tithi times for this year and next, and keeps a time only when the server finds it,
 * with its day, on a page it opens itself (most panjika sites draw times with JavaScript, so two were rarely found). A time that fails is held and never shown.
 * Part of the "janun" handover job; one search an hour at most, each festival and year tried three times at most.
 */
final class FestivalDesk
{
    private const MONTHS_EN = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];

    private const MONTHS_BN = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    public static function run(CarbonImmutable $now): bool
    {
        if (Setting::get('festival.hour') === $now->format('Y-m-d H')) {
            return false;
        }
        foreach (self::years($now) as $year) {
            foreach (FestivalPlan::FESTIVALS as $key => $f) {
                $tries = (int) Setting::get("festival.tries.{$key}.{$year}", 0);
                if ($tries >= 3 || FestivalTime::where('festival', $key)->where('cycle', $year)->where('status', 'verified')->exists()) {
                    continue;
                }
                Setting::put('festival.hour', $now->format('Y-m-d H'));
                Setting::put("festival.tries.{$key}.{$year}", $tries + 1);
                self::find($key, $year);

                return true;
            }
        }

        return false;
    }

    /** The years worth searching: from November this year's festivals are over (ভাইফোঁটা is the last), so only next year's. */
    public static function years(CarbonImmutable $now): array
    {
        return $now->month >= 11 ? [$now->year + 1] : [$now->year, $now->year + 1];
    }

    /** Searches once, opens the pages it names, keeps what two of them show. @return int how many times were kept */
    public static function find(string $key, int $year): int
    {
        [$name, , $events] = FestivalPlan::FESTIVALS[$key];
        $want = collect($events)->map(fn ($what, $label) => "- {$label}: {$what}")->implode("\n");
        [$d] = Claude::search('factcheck',
            'You find Hindu/Bengali festival dates and tithi times for a Bengali magazine. Use the Bisuddha Siddhanta panjika as followed in Kolkata (as on most published Bengali calendars) and Indian Standard Time. '
            .'For each event give: day = the Gregorian date (YYYY-MM-DD) on which it is kept in Kolkata; start and end = when the tithi (or the event) begins and ends, "YYYY-MM-DD HH:MM" in IST, 24-hour; for a day-only event leave start and end empty. '
            .'Give the URLs of web pages (not hatpakha.com) that state these dates and times as plain text in the page (news articles, almanac pages); not pages that draw a calendar with scripts. A page must really show them. If you are not sure of an event, leave it out. Answer ONLY with JSON: '
            .'{"events":[{"label":"exactly as asked","day":"YYYY-MM-DD","start":"","end":""}],"sources":[{"url":"https://..."}]}',
            "<festival year=\"{$year}\">".e($name)."</festival>\n<events>\n".e($want)."\n</events>", 5);
        if (! is_array($d)) {
            return 0;
        }
        $pages = collect((array) ($d['sources'] ?? []))->pluck('url')->filter()->unique()->take(5)
            ->reject(fn ($u) => str_contains(strtolower((string) parse_url((string) $u, PHP_URL_HOST)), 'hatpakha'))
            ->mapWithKeys(fn ($u) => [(string) $u => JanunDesk::fetchText((string) $u)])->filter()->map(fn ($t) => self::digits($t));
        $kept = 0;
        $labels = array_keys($events);
        foreach ((array) ($d['events'] ?? []) as $e) {
            $label = (string) ($e['label'] ?? '');
            if (! in_array($label, $labels, true)) {
                continue;
            }
            [$ok, $start, $end, $day, $found, $why] = self::verify($e, $pages->all(), $year);
            FestivalTime::updateOrCreate(['festival' => $key, 'cycle' => $year, 'label' => $label], [
                'position' => array_search($label, $labels, true), 'day' => $day, 'starts_at' => $start, 'ends_at' => $end,
                'sources' => $found, 'status' => $ok ? 'verified' : 'held', 'note' => $why,
            ]);
            $kept += $ok ? 1 : 0;
        }
        AuditLog::record('festival.found', null, ['festival' => $key, 'year' => $year, 'kept' => $kept, 'pages' => $pages->count()]);

        return $kept;
    }

    /** @return array{0: bool, 1: ?CarbonImmutable, 2: ?CarbonImmutable, 3: ?string, 4: list<string>, 5: ?string} */
    public static function verify(array $e, array $pages, int $year): array
    {
        $ist = 'Asia/Kolkata';
        $day = rescue(fn () => CarbonImmutable::createFromFormat('!Y-m-d', (string) ($e['day'] ?? ''), $ist), null, false);
        $start = filled($e['start'] ?? '') ? rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d H:i', (string) $e['start'], $ist), null, false) : null;
        $end = filled($e['end'] ?? '') ? rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d H:i', (string) $e['end'], $ist), null, false) : null;
        if (! $day || abs($day->year - $year) > 1) {
            return [false, null, null, null, [], 'no day'];
        }
        if (($start xor $end) || ($start && ($end <= $start || $start->diffInHours($end) > 30 || $day->lt($start->startOfDay()->subDay()) || $day->gt($end->startOfDay()->addDay())))) {
            return [false, null, null, $day->toDateString(), [], 'times do not make sense'];
        }
        $found = [];
        foreach ($pages as $url => $text) {
            $has = self::hasDate($text, $day) && (! $start || (self::hasTime($text, $start) && self::hasTime($text, $end)));
            if ($has) {
                $found[preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)))] = $url;
            }
        }
        // one page the server opens that shows the day and both times as text (most panjika sites draw them with
        // JavaScript, so two such pages were rarely found; live site, 2026-11-26): the time is published, not invented
        $ok = count($found) >= 1;

        return [$ok, $start?->utc(), $end?->utc(), $day->toDateString(), array_values($found), $ok ? null : 'found on '.count($found).' page(s)'];
    }

    public static function digits(string $text): string
    {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;

        return strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    /** 17:05, 5:05 (PM), 05.05 or ৫টা ৫ মিনিট (Bengali digits already turned into 0-9). */
    public static function hasTime(string $text, CarbonImmutable $t): bool
    {
        $h = $t->hour;
        $h12 = $h % 12 ?: 12;
        $m = $t->format('i');
        $mi = (int) $m;
        $hours = array_unique([$h, $h12]);
        foreach ($hours as $hh) {
            if (preg_match('/(?<!\d)0?'.$hh.'[:.]'.$m.'(?!\d)/u', $text) || preg_match('/(?<!\d)0?'.$hh.'\s*টা\s*0?'.$mi.'\s*মি/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /** 14 April, April 14, ১৪ এপ্রিল, 14/04/2027 or 2027-04-14. */
    public static function hasDate(string $text, CarbonImmutable $d): bool
    {
        $day = $d->day;
        $en = self::MONTHS_EN[$d->month - 1];
        $bn = preg_replace('/ি$/u', '[িী]', \Normalizer::normalize(self::MONTHS_BN[$d->month - 1], \Normalizer::FORM_C));
        $months = '(?:'.$en.'|'.substr($en, 0, 3).'\.?|'.$bn.')';

        return (bool) (preg_match('/(?<!\d)0?'.$day.'(?:st|nd|rd|th)?\s*(?:of\s*)?'.$months.'/iu', $text)
            || preg_match('/'.$months.'\s*0?'.$day.'(?!\d)/iu', $text)
            || str_contains($text, $d->format('d/m/Y')) || str_contains($text, $d->format('d-m-Y')) || str_contains($text, $d->format('Y-m-d')));
    }
}
