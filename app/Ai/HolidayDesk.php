<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\HolidayList;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * D314 (owner: holiday lists, automatic once checked): the West Bengal and Bangladesh government holiday lists. A list is
 * looked for only once its official notice exists (never an "expected" list); every date counts only when the server
 * opens at least two pages that show that date with the holiday's name on the same lines. Shown when ten or more dates
 * and at least seven in ten of the notice's dates are checked. Part of the janun job: one search a day at most, from
 * October for the next year.
 */
final class HolidayDesk
{
    private const ASK = [
        'west-bengal' => 'the Government of West Bengal holiday list for {year} (Finance Department, Audit Branch notification for State Government offices; public holidays under the NI Act and state holidays)',
        'bangladesh' => 'the Government of Bangladesh public holiday list for {year} (the প্রজ্ঞাপন of the Ministry of Public Administration / Cabinet Division: সাধারণ ছুটি, নির্বাহী আদেশে ছুটি, ঐচ্ছিক ছুটি)',
    ];

    public static function run(CarbonImmutable $now): bool
    {
        if (Setting::get('holiday.day') === $now->toDateString()) {
            return false;
        }
        $years = $now->month >= 10 ? [$now->year, $now->year + 1] : [$now->year];
        foreach ($years as $year) {
            foreach (array_keys(self::ASK) as $country) {
                $list = HolidayList::firstOrCreate(['country' => $country, 'year' => $year]);
                $tries = (int) Setting::get("holiday.tries.{$country}.{$year}.".$now->format('Y-m'), 0);
                if ($list->status !== 'verified' && $tries < 8) {   // about twice a week until it is out
                    Setting::put('holiday.day', $now->toDateString());
                    Setting::put("holiday.tries.{$country}.{$year}.".$now->format('Y-m'), $tries + 1);
                    self::find($list);

                    return true;
                }
            }
        }

        return false;
    }

    /** @return int dates checked */
    public static function find(HolidayList $list): int
    {
        [$d] = Claude::search('factcheck',
            'You find official government holiday lists for a Bengali magazine. ONLY if the official notification for the year has been published, return it; otherwise return {"published":false}. '
            .'Never return an expected, draft or previous year\'s list. Give the notice\'s number and date as printed, its official URL if one exists, every holiday exactly as listed (date YYYY-MM-DD, the name in Bengali, the name in English, the notice\'s category, and moon=true for days that depend on the moon being sighted), '
            .'and the URLs of at least two independent web pages (official page, a government mirror, or national newspapers) that print the list as text. Answer ONLY with JSON: '
            .'{"published":true,"notice":"...","notice_url":"...","holidays":[{"date":"YYYY-MM-DD","name_bn":"...","name_en":"...","kind":"...","moon":false}],"sources":[{"url":"https://..."}]}',
            '<list>'.e(str_replace('{year}', (string) $list->year, self::ASK[$list->country])).'</list>', 6);
        if (! is_array($d) || ! ($d['published'] ?? false)) {
            $list->forceFill(['note' => 'notice not found yet', 'checked_at' => now()])->save();

            return 0;
        }
        $pages = collect((array) ($d['sources'] ?? []))->pluck('url')->push($d['notice_url'] ?? null)->filter()->unique()->take(6)
            ->reject(fn ($u) => str_contains(strtolower((string) parse_url((string) $u, PHP_URL_HOST)), 'hatpakha'))
            ->mapWithKeys(fn ($u) => [(string) $u => JanunDesk::fetchText((string) $u)])->filter()->map(fn ($t) => FestivalDesk::digits(\Normalizer::normalize($t, \Normalizer::FORM_C) ?: $t));
        $claimed = collect((array) ($d['holidays'] ?? []))->filter(fn ($h) => preg_match('/^'.$list->year.'-\d\d-\d\d$/', (string) ($h['date'] ?? '')));
        $ok = 0;
        foreach ($claimed as $h) {
            $day = CarbonImmutable::parse($h['date']);
            $found = $pages->filter(fn ($text) => self::shows($text, $day, (string) ($h['name_bn'] ?? ''), (string) ($h['name_en'] ?? '')))->keys()->values();
            $good = $found->count() >= 2;
            Holiday::updateOrCreate(['holiday_list_id' => $list->id, 'day' => $day->format('Y-m-d 00:00:00'), 'name' => trim((string) $h['name_bn']) ?: (string) $h['name_en']],
                ['kind' => mb_substr(trim((string) ($h['kind'] ?? '')), 0, 60) ?: null, 'moon' => (bool) ($h['moon'] ?? false), 'sources' => $found->take(3)->all(), 'status' => $good ? 'verified' : 'held']);
            $ok += $good ? 1 : 0;
        }
        $live = $ok >= 10 && $ok * 10 >= $claimed->count() * 7;
        $list->forceFill(['notice' => mb_substr((string) ($d['notice'] ?? ''), 0, 250) ?: null, 'notice_url' => self::https($d['notice_url'] ?? null),
            'status' => $live ? 'verified' : 'held', 'note' => $live ? null : $ok.' of '.$claimed->count().' dates checked', 'checked_at' => now()])->save();
        AuditLog::record('holiday.found', null, ['country' => $list->country, 'year' => $list->year, 'checked' => $ok, 'claimed' => $claimed->count(), 'pages' => $pages->count()]);
        if ($live) {
            \App\Support\IndexNow::ping([$list->url(), \App\Support\Url::to('/janun/holidays/'.$list->year)]);
        }

        return $ok;
    }

    /** The page shows this date, and on that date's lines a word of the holiday's name (Bengali or English). */
    public static function shows(string $text, CarbonImmutable $day, string $bn, string $en): bool
    {
        if (! FestivalDesk::hasDate($text, $day)) {
            return false;
        }
        $near = mb_strtolower(FestivalDesk::near($text, $day));
        $words = collect(preg_split('/[\s,()\/\-–:]+/u', \Normalizer::normalize($bn, \Normalizer::FORM_C).' '.$en))
            ->map(fn ($w) => mb_strtolower(trim($w)))->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, ['দিবস', 'ছুটি', 'holiday', 'day', 'the', 'and', 'puja', 'পূজা', 'পুজো', 'জয়ন্তী', 'jayanti'], true));

        return $words->contains(fn ($w) => str_contains($near, $w));
    }

    private static function https(?string $u): ?string
    {
        return $u && str_starts_with($u, 'https://') ? mb_substr($u, 0, 500) : null;
    }
}
