<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\TithiDay;
use App\Support\Tithi;
use Carbon\CarbonImmutable;

/**
 * D310 (owner, 2026-11-27: "add Tithi of the day on the calendar"): each day's tithi comes from the published বিশুদ্ধ
 * সিদ্ধান্ত month page (BanglaCalendarDesk::SOURCE, as text) and is shown only when our own calculation (App\Support\Tithi)
 * agrees: the same tithi at sunrise and every end time within ten minutes. No AI and no cost: one page an hour at most,
 * for the West Bengal months holding today and the next five weeks.
 */
final class TithiDesk
{
    private const PARTS = ['ভোর', 'সকাল', 'দুপুর', 'বিকেল', 'সন্ধ্যা', 'রাত'];

    public static function run(CarbonImmutable $now): bool
    {
        if (Setting::get('tithi.hour') === $now->format('Y-m-d H')) {
            return false;
        }
        $today = CarbonImmutable::create($now->year, $now->month, $now->day);
        foreach ([$today, $today->addDays(35)] as $d) {
            $have = TithiDay::whereBetween('day', [$d->toDateString(), $d->addDays(6)->toDateString()])->count();
            if ($have < 7 && ($url = BanglaCalendarDesk::sourceUrlFor($d))) {
                Setting::put('tithi.hour', $now->format('Y-m-d H'));
                self::fill($url);

                return true;
            }
        }

        return false;
    }

    /** Reads one month page and stores every day it can check. @return int days confirmed */
    public static function fill(string $url): int
    {
        $text = JanunDesk::fetchText($url);
        if ($text === null) {
            return 0;
        }
        $text = FestivalDesk::digits(\Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text);
        $ok = 0;
        $day = null;
        foreach (preg_split('/\n/u', $text) as $line) {
            $line = trim($line);
            if (preg_match('/(\d{1,2})\s+(জানুয়ারি|ফেব্রুয়ারি|মার্চ|এপ্রিল|মে|জুন|জুলাই|আগস্ট|সেপ্টেম্বর|অক্টোবর|নভেম্বর|ডিসেম্বর)\s+(\d{4})$/u', $line, $m)) {
                $mo = array_search($m[2], ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'], true);
                $day = $mo === false ? null : CarbonImmutable::create((int) $m[3], $mo + 1, (int) $m[1], 0, 0, 0, 'Asia/Kolkata');

                continue;
            }
            if ($day && preg_match('/^\S+বার · (শুক্লপক্ষ|কৃষ্ণপক্ষ) (.*)$/u', $line, $m)) {
                $r = self::parse($day, $m[1], $m[2]);
                if ($r !== null) {
                    $ok += self::store($day, ...$r) ? 1 : 0;
                }
                $day = null;
            }
        }
        AuditLog::record('tithi.filled', null, ['url' => $url, 'verified' => $ok]);

        return $ok;
    }

    /** @return array{0: int, 1: list<array{0: CarbonImmutable, 1: int}>, 2: ?CarbonImmutable}|null [tithi at sunrise, changes, sunrise] */
    public static function parse(CarbonImmutable $day, string $paksha, string $rest): ?array
    {
        $parts = implode('|', self::PARTS);
        if (! preg_match('/^(\S+)/u', $rest, $w) || ($first = Tithi::number($paksha, $w[1])) === null) {
            return null;
        }
        $rest = mb_substr($rest, mb_strlen($w[1]));
        $sunrise = preg_match('/সূর্যোদয় ('.$parts.') (\d{1,2}):(\d\d)/u', $rest, $s) ? self::clock($day, $s[1], (int) $s[2], (int) $s[3], false) : null;
        if (preg_match('/^\s*\(সারাদিন\)/u', $rest)) {
            return [$first, [], $sunrise];
        }
        $changes = [];
        $current = $first;
        // " রাত 3:18 (পরদিন) পর্যন্ত, তারপর ষষ্ঠী" once, or twice when a tithi is skipped that day
        while (preg_match('/^\s*('.$parts.') (\d{1,2}):(\d\d)( \(পরদিন\))? পর্যন্ত, তারপর (\S+)/u', $rest, $c)) {
            $next = $current === 30 ? 1 : $current + 1;
            if (Tithi::number($next <= 15 ? 'শুক্লপক্ষ' : 'কৃষ্ণপক্ষ', $c[5]) !== $next) {
                return null;   // not the tithi that should follow: do not guess
            }
            $changes[] = [self::clock($day, $c[1], (int) $c[2], (int) $c[3], $c[4] !== ''), $next];
            $current = $next;
            $rest = mb_substr($rest, mb_strlen($c[0]));
        }

        return $changes === [] ? null : [$first, $changes, $sunrise];
    }

    /** "রাত 3:18 (পরদিন)" on a day → the moment in India time. */
    public static function clock(CarbonImmutable $day, string $part, int $h, int $m, bool $nextDay): CarbonImmutable
    {
        $h24 = match ($part) {
            'ভোর', 'সকাল' => $h % 12,
            'দুপুর' => $h === 12 ? 12 : ($h < 6 ? $h + 12 : $h),
            'বিকেল', 'সন্ধ্যা' => $h % 12 + 12,
            default => $h === 12 ? 0 : ($h >= 6 ? $h + 12 : $h),   // রাত: 7-11 in the evening, 12-5 after midnight
        };
        $t = $day->setTime($h24 % 24, $m);

        return $nextDay ? $t->addDay() : $t;
    }

    /** Our calculation must agree: the tithi at sunrise, and every end within ten minutes. */
    public static function store(CarbonImmutable $day, int $tithi, array $changes, ?CarbonImmutable $sunrise): bool
    {
        $at = $sunrise ?? $day->setTime(6, 0);
        $why = null;
        if (Tithi::at($at) !== $tithi) {
            $why = 'calculation gives '.Tithi::name(Tithi::at($at)).' at sunrise';
        }
        $from = $at;
        foreach ($changes as [$t, $next]) {
            $calc = Tithi::endAfter($from);
            if (abs($calc->diffInMinutes($t, false)) > 10) {
                $why ??= 'end '.$t->format('H:i').' vs calculated '.$calc->setTimezone('Asia/Kolkata')->format('H:i');
            }
            $from = $calc->addMinutes(5);
        }
        if ($changes === [] && Tithi::endAfter($at)->lt($at->addHours(23))) {
            $why ??= 'published all day, calculation ends it sooner';
        }
        TithiDay::updateOrCreate(['day' => $day->format('Y-m-d 00:00:00')], ['tithi' => $tithi, 'status' => $why ? 'held' : 'verified', 'note' => $why,
            'turns' => array_map(fn ($c) => [$c[0]->utc()->toIso8601String(), $c[1]], $changes)]);

        return $why === null;
    }
}
