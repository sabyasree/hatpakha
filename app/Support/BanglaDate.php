<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * D303: the Bengali date in both Bengals.
 *
 * Bangladesh: the revised calendar (Bangla Academy, from 1426): 1 বৈশাখ = 14 April; বৈশাখ–আশ্বিন 31 days, কার্তিক–মাঘ
 * and চৈত্র 30, ফাল্গুন 29 (30 when that February has 29 days). Pure arithmetic, exact.
 *
 * West Bengal: a month begins the day after the sun enters the next sidereal sign (সংক্রান্তি), in India time.
 * We compute each সংক্রান্তি (Meeus' solar longitude with the Lahiri/চিত্রপক্ষ ayanamsa, a few minutes' accuracy);
 * App\Ai\BanglaCalendarDesk then checks every month's first day against two published calendars, and only checked
 * months are shown (App\Models\BanglaMonth).
 */
final class BanglaDate
{
    public const MONTHS = ['বৈশাখ', 'জ্যৈষ্ঠ', 'আষাঢ়', 'শ্রাবণ', 'ভাদ্র', 'আশ্বিন', 'কার্তিক', 'অগ্রহায়ণ', 'পৌষ', 'মাঘ', 'ফাল্গুন', 'চৈত্র'];

    public const SLUGS = ['boishakh', 'joishtho', 'asharh', 'srabon', 'bhadro', 'ashwin', 'kartik', 'ogrohayon', 'poush', 'magh', 'falgun', 'choitro'];

    public const EN = ['Boishakh', 'Joishtho', 'Asharh', 'Srabon', 'Bhadro', 'Ashwin', 'Kartik', 'Agrahayan', 'Poush', 'Magh', 'Falgun', 'Chaitra'];

    public const WEEKDAYS = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];

    public const SEASONS = ['গ্রীষ্ম', 'গ্রীষ্ম', 'বর্ষা', 'বর্ষা', 'শরৎ', 'শরৎ', 'হেমন্ত', 'হেমন্ত', 'শীত', 'শীত', 'বসন্ত', 'বসন্ত'];

    /** Bangladesh: [year, month 1-12, day]. */
    public static function bd(\DateTimeInterface $date): array
    {
        $d = CarbonImmutable::instance($date)->startOfDay();
        $gy = $d->month < 4 || ($d->month === 4 && $d->day < 14) ? $d->year - 1 : $d->year;
        $start = CarbonImmutable::create($gy, 4, 14)->startOfDay();
        $left = (int) $start->diffInDays($d);
        foreach (self::bdLengths($gy + 1) as $i => $len) {
            if ($left < $len) {
                return [$gy - 593, $i + 1, $left + 1];
            }
            $left -= $len;
        }

        return [$gy - 593, 12, 30];   // not reached
    }

    /** Month lengths of the Bangladesh year that ends in the Gregorian year $endYear. */
    public static function bdLengths(int $endYear): array
    {
        $leap = CarbonImmutable::create($endYear, 1, 1)->isLeapYear();

        return [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, $leap ? 30 : 29, 30];
    }

    /** The first Gregorian day of a Bangladesh month. */
    public static function bdStart(int $year, int $month): CarbonImmutable
    {
        $d = CarbonImmutable::create($year + 593, 4, 14)->startOfDay();
        foreach (array_slice(self::bdLengths($year + 594), 0, $month - 1) as $len) {
            $d = $d->addDays($len);
        }

        return $d;
    }

    /** West Bengal by calculation alone: the first day of each month of a Bengali year, and the next year's 1 বৈশাখ. @return list<CarbonImmutable> 13 dates */
    public static function wbComputed(int $year): array
    {
        $gy = $year + 593;
        $out = [];
        $guess = gregoriantojd(4, 13, $gy) - 0.5;
        for ($k = 0; $k <= 12; $k++) {
            $jd = self::sankranti(($k % 12) * 30.0, $guess);
            $ist = CarbonImmutable::createFromTimestampUTC((int) round(($jd - 2440587.5) * 86400))->setTimezone('Asia/Kolkata');
            $out[] = CarbonImmutable::create($ist->year, $ist->month, $ist->day)->addDay();
            $guess = $jd + 29.5;
        }

        return $out;
    }

    /** The moment (Julian day, UT) the sun's sidereal longitude reaches $target degrees, searched from $from for 40 days. */
    public static function sankranti(float $target, float $from): float
    {
        $f = fn (float $jd) => fmod(self::sidereal($jd) - $target + 540.0, 360.0) - 180.0;
        $a = $from;
        $b = $from + 1;
        while ($f($b) < 0 && $b < $from + 40) {
            $a = $b;
            $b += 1;
        }
        for ($i = 0; $i < 40; $i++) {
            $m = ($a + $b) / 2;
            $f($m) < 0 ? $a = $m : $b = $m;
        }

        return ($a + $b) / 2;
    }

    /** The sun's sidereal longitude (degrees). */
    public static function sidereal(float $jd): float
    {
        $t = ($jd - 2451545.0) / 36525.0;
        $l0 = 280.46646 + 36000.76983 * $t + 0.0003032 * $t * $t;
        $m = deg2rad(357.52911 + 35999.05029 * $t - 0.0001537 * $t * $t);
        $c = (1.914602 - 0.004817 * $t - 0.000014 * $t * $t) * sin($m) + (0.019993 - 0.000101 * $t) * sin(2 * $m) + 0.000289 * sin(3 * $m);
        $omega = deg2rad(125.04 - 1934.136 * $t);
        $apparent = $l0 + $c - 0.00569 - 0.00478 * sin($omega);
        $ayanamsa = 23.853 + 0.0139689 * ($jd - 2451545.0) / 365.25;   // Lahiri (চিত্রপক্ষ)

        return fmod(fmod($apparent - $ayanamsa, 360.0) + 360.0, 360.0);
    }

    /** West Bengal: [year, month, day] from checked months only, or null. */
    public static function wb(\DateTimeInterface $date): ?array
    {
        $d = CarbonImmutable::instance($date)->toDateString();
        $rows = self::verified();
        $m = $rows->filter(fn ($r) => $r->starts_on->toDateString() <= $d)->last();
        if (! $m) {
            return null;
        }
        $next = $rows->first(fn ($r) => $r->starts_on->toDateString() > $d);
        $nextExpected = $m->month === 12 ? [$m->year + 1, 1] : [$m->year, $m->month + 1];
        if (! $next || [$next->year, $next->month] !== $nextExpected) {
            return null;   // the month after it is not checked yet, so its end is not known
        }

        return [$m->year, $m->month, (int) $m->starts_on->diffInDays(CarbonImmutable::parse($d)) + 1];
    }

    private static array $memo = [];

    public static function flush(): void
    {
        self::$memo = [];
    }

    /** The checked months, loaded once per request (a calendar page asks for many days). */
    private static function verified(): \Illuminate\Support\Collection
    {
        $key = app()->bound('request') ? spl_object_id(request()) : 0;
        if (! isset(self::$memo[$key])) {
            self::$memo = [$key => \App\Models\BanglaMonth::where('status', 'verified')->orderBy('starts_on')->get()];
        }

        return self::$memo[$key];
    }

    /** "১০ অগ্রহায়ণ ১৪৩৩" */
    public static function text(?array $ymd): string
    {
        return $ymd ? Bn::num((string) $ymd[2]).' '.self::MONTHS[$ymd[1] - 1].' '.Bn::num((string) $ymd[0]) : '';
    }

    public static function slug(int $month): string
    {
        return self::SLUGS[$month - 1];
    }

    public static function url(?int $year = null, ?int $month = null): string
    {
        return Url::to('/janun/bangla-calendar'.($year ? '/'.$year : '').($month ? '/'.self::slug($month) : ''));
    }

    /**
     * Days kept on a Bengali or English date that need no checking (every year the same), for one Gregorian day.
     *
     * @return list<string>
     */
    public static function fixedDays(\DateTimeInterface $date, ?array $wb, array $bd): array
    {
        $out = [];
        $md = CarbonImmutable::instance($date)->format('m-d');
        $out = array_merge($out, match ($md) {
            '02-21' => ['শহিদ দিবস ও আন্তর্জাতিক মাতৃভাষা দিবস'],
            '03-26' => ['বাংলাদেশের স্বাধীনতা দিবস'],
            '12-16' => ['বাংলাদেশের বিজয় দিবস'],
            default => [],
        });
        foreach (['পশ্চিমবঙ্গ' => $wb, 'বাংলাদেশ' => $bd] as $where => $d) {
            $day = $d ? match ([$d[1], $d[2]]) {
                [1, 1] => 'পয়লা বৈশাখ', [1, 25] => 'রবীন্দ্রজয়ন্তী (পঁচিশে বৈশাখ)', [2, 11] => 'নজরুলজয়ন্তী (এগারোই জ্যৈষ্ঠ)', [4, 22] => 'বাইশে শ্রাবণ (রবীন্দ্রনাথের প্রয়াণদিবস)', default => null,
            } : null;
            if ($day) {
                $out[] = $day.' ('.$where.')';
            }
        }

        return $out;
    }
}
