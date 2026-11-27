<?php

namespace App\Support;

use App\Models\TithiDay;
use Carbon\CarbonImmutable;

/**
 * D313: the year's পূর্ণিমা, অমাবস্যা and একাদশী from the checked tithi days (D310): each one's start and end in India
 * time and the day it is kept (the day its tithi holds at sunrise; if it holds at no sunrise, the day it begins).
 * Only tithis whose start and end both come from checked days are listed.
 */
final class LunarDays
{
    /** slug => [name, tithi numbers, English] */
    public const TYPES = [
        'purnima' => ['পূর্ণিমা', [15], 'Purnima'],
        'amavasya' => ['অমাবস্যা', [30], 'Amavasya'],
        'ekadashi' => ['একাদশী', [11, 26], 'Ekadashi'],
    ];

    /** @return list<array{tithi: int, start: CarbonImmutable, end: CarbonImmutable, day: CarbonImmutable}> for a Gregorian year */
    public static function year(string $type, int $year): array
    {
        $want = self::TYPES[$type][1];
        $days = TithiDay::where('status', 'verified')->whereDate('day', '>=', ($year - 1).'-12-01')->whereDate('day', '<=', ($year + 1).'-01-31')
            ->orderBy('day')->get();
        // every change (moment, tithi that begins), from checked days only, in order
        $changes = [];
        foreach ($days as $d) {
            foreach ((array) $d->turns as [$at, $next]) {
                $changes[] = [CarbonImmutable::parse($at), (int) $next, $d->day->toDateString()];
            }
        }
        usort($changes, fn ($a, $b) => $a[0] <=> $b[0]);
        $sunrise = $days->mapWithKeys(fn ($d) => [$d->day->toDateString() => $d->tithi]);
        $out = [];
        foreach ($changes as $i => [$start, $tithi]) {
            if (! in_array($tithi, $want, true) || ! isset($changes[$i + 1])) {
                continue;
            }
            [$end] = $changes[$i + 1];
            if ($end->diffInHours($start, true) > 30) {
                continue;   // a gap in the checked days: never guess an end
            }
            $s = $start->setTimezone('Asia/Kolkata');
            $kept = collect([$s->toDateString(), $s->addDay()->toDateString()])->first(fn ($k) => ($sunrise[$k] ?? null) === $tithi) ?? $s->toDateString();
            $day = CarbonImmutable::parse($kept);
            if ($day->year === $year) {
                $out[] = ['tithi' => $tithi, 'start' => $start, 'end' => $end, 'day' => $day];
            }
        }

        return $out;
    }

    public static function url(string $type, int $year): string
    {
        return Url::to('/janun/puja/'.$type.'-'.$year);
    }

    /** "শুক্লা একাদশী" / "কৃষ্ণা একাদশী" / "পূর্ণিমা" */
    public static function label(int $tithi): string
    {
        return match ($tithi) { 11 => 'শুক্লা একাদশী', 26 => 'কৃষ্ণা একাদশী', 15 => 'পূর্ণিমা', 30 => 'অমাবস্যা', default => Tithi::name($tithi) };
    }
}
