<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * D310: the tithi from the moon's and sun's positions. A tithi is each 12° of the moon's lead over the sun
 * (1-15 শুক্লপক্ষ, 16-30 কৃষ্ণপক্ষ; 15 পূর্ণিমা, 30 অমাবস্যা). Moon: Meeus, Astronomical Algorithms ch. 47 (the
 * terms above 0.002°); sun: BanglaDate (ch. 25). Their lead is good to a few hundredths of a degree, about two
 * minutes of a tithi's end. Used to check the published বিশুদ্ধ সিদ্ধান্ত times (App\Ai\TithiDesk), not alone.
 */
final class Tithi
{
    public const NAMES = ['প্রতিপদ', 'দ্বিতীয়া', 'তৃতীয়া', 'চতুর্থী', 'পঞ্চমী', 'ষষ্ঠী', 'সপ্তমী', 'অষ্টমী', 'নবমী', 'দশমী', 'একাদশী', 'দ্বাদশী', 'ত্রয়োদশী', 'চতুর্দশী'];

    /** [D, M, M', F, coefficient in 1e-6°] for the moon's longitude (Meeus table 47.A, largest terms). */
    private const TERMS = [
        [0, 0, 1, 0, 6288774], [2, 0, -1, 0, 1274027], [2, 0, 0, 0, 658314], [0, 0, 2, 0, 213618], [0, 1, 0, 0, -185116],
        [0, 0, 0, 2, -114332], [2, 0, -2, 0, 58793], [2, -1, -1, 0, 57066], [2, 0, 1, 0, 53322], [2, -1, 0, 0, 45758],
        [0, 1, -1, 0, -40923], [1, 0, 0, 0, -34720], [0, 1, 1, 0, -30383], [2, 0, 0, -2, 15327], [0, 0, 1, 2, -12528],
        [0, 0, 1, -2, 10980], [4, 0, -1, 0, 10675], [0, 0, 3, 0, 10034], [4, 0, -2, 0, 8548], [2, 1, -1, 0, -7888],
        [2, 1, 0, 0, -6766], [1, 0, -1, 0, -5163], [1, 1, 0, 0, 4987], [2, -1, 1, 0, 4036], [2, 0, 2, 0, 3994],
        [4, 0, 0, 0, 3861], [2, 0, -3, 0, 3665], [0, 1, -2, 0, -2689], [2, 0, -1, 2, -2602], [2, -1, -2, 0, 2390],
        [1, 0, 1, 0, -2348], [2, -2, 0, 0, 2236], [0, 1, 2, 0, -2120], [0, 2, 0, 0, -2069],
    ];

    public static function moon(float $jd): float
    {
        $t = ($jd - 2451545.0) / 36525.0;
        $l = 218.3164477 + 481267.88123421 * $t - 0.0015786 * $t * $t;
        $d = deg2rad(297.8501921 + 445267.1114034 * $t - 0.0018819 * $t * $t);
        $m = deg2rad(357.5291092 + 35999.0502909 * $t - 0.0001536 * $t * $t);
        $mp = deg2rad(134.9633964 + 477198.8675055 * $t + 0.0087414 * $t * $t);
        $f = deg2rad(93.2720950 + 483202.0175233 * $t - 0.0036539 * $t * $t);
        $e = 1 - 0.002516 * $t - 0.0000074 * $t * $t;
        $sum = 0.0;
        foreach (self::TERMS as [$cd, $cm, $cmp, $cf, $c]) {
            $sum += $c * (abs($cm) === 2 ? $e * $e : (abs($cm) === 1 ? $e : 1)) * sin($cd * $d + $cm * $m + $cmp * $mp + $cf * $f);
        }
        $a1 = deg2rad(119.75 + 131.849 * $t);
        $a2 = deg2rad(53.09 + 479264.290 * $t);
        $sum += 3958 * sin($a1) + 1962 * sin(deg2rad($l) - $f) + 318 * sin($a2);

        return fmod($l + $sum / 1e6 + 3600, 360.0);
    }

    /** The moon's lead over the sun, 0-360° (the ayanamsa and nutation cancel). */
    public static function elongation(float $jd): float
    {
        $sun = BanglaDate::sidereal($jd) + 23.853 + 0.0139689 * ($jd - 2451545.0) / 365.25;   // back to tropical

        return fmod(self::moon($jd) - $sun + 720.0, 360.0);
    }

    /** 1-30 at a moment. */
    public static function at(\DateTimeInterface $t): int
    {
        return (int) floor(self::elongation(self::jd($t)) / 12.0) + 1;
    }

    /** When the tithi running at $t ends. */
    public static function endAfter(\DateTimeInterface $t): CarbonImmutable
    {
        $jd = self::jd($t);
        $target = floor(self::elongation($jd) / 12.0) * 12.0 + 12.0;
        $f = fn ($x) => fmod(self::elongation($x) - $target + 540.0, 360.0) - 180.0;
        $a = $jd;
        $b = $jd + 1.3;   // a tithi lasts at most about 26.8 hours
        for ($i = 0; $i < 50; $i++) {
            $mid = ($a + $b) / 2;
            $f($mid) < 0 ? $a = $mid : $b = $mid;
        }

        return CarbonImmutable::createFromTimestampUTC((int) round((($a + $b) / 2 - 2440587.5) * 86400));
    }

    public static function jd(\DateTimeInterface $t): float
    {
        return $t->getTimestamp() / 86400.0 + 2440587.5;
    }

    /** "শুক্লপক্ষ পঞ্চমী", "পূর্ণিমা", "অমাবস্যা" */
    public static function name(int $n): string
    {
        return match (true) {
            $n === 15 => 'পূর্ণিমা', $n === 30 => 'অমাবস্যা',
            $n < 15 => 'শুক্লপক্ষ '.self::NAMES[$n - 1], default => 'কৃষ্ণপক্ষ '.self::NAMES[$n - 16],
        };
    }

    /** The number of a published name: পক্ষ + tithi word. */
    public static function number(string $paksha, string $word): ?int
    {
        $word = \Normalizer::normalize(trim($word), \Normalizer::FORM_C);
        if ($word === \Normalizer::normalize('পূর্ণিমা', \Normalizer::FORM_C)) {
            return 15;
        }
        if ($word === \Normalizer::normalize('অমাবস্যা', \Normalizer::FORM_C)) {
            return 30;
        }
        $i = collect(self::NAMES)->search(fn ($n) => \Normalizer::normalize($n, \Normalizer::FORM_C) === $word);
        if ($i === false) {
            return null;
        }

        return str_contains($paksha, 'কৃষ্ণ') ? 16 + $i : 1 + $i;
    }
}
