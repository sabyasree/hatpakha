<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D243 (owner: "no prize right now, but make all the proper badges: মাসের সেরা, বছরের সেরা, সেরা for multiple years;
 * show those in the profile as well as top of the leaderboard, so people will chase them"). The first three players of
 * a finished month and of a finished year, written down once and kept. Given by the rules alone, never by hand:
 * a month counts a player's best 20 days and asks for 10 days played; a year counts the best 10 months and asks for
 * 6 such months. Two years as বছরের সেরা make a শব্দ-সাধক, three or more a হাতপাখা-রত্ন (the owner's names).
 */
final class GameTitles
{
    /** The first month that is judged: the new points start with it. */
    public const FIRST_MONTH = '2026-12';

    public const PLACES = [1 => 'প্রথম', 2 => 'দ্বিতীয়', 3 => 'তৃতীয়'];

    public const MULTI = [2 => 'শব্দ-সাধক', 3 => 'হাতপাখা-রত্ন'];

    /** Writes down the winners of the month and the year that have just ended, once each. Safe to call any time. */
    public static function award(?Carbon $now = null): int
    {
        $now = ($now ?? now('Asia/Kolkata'))->copy();
        $made = 0;
        $month = $now->copy()->startOfMonth()->subMonth()->format('Y-m');
        if ($month >= self::FIRST_MONTH && ! Cache::get('hp.titles.done.'.$month) && ! DB::table('game_titles')->where('kind', 'month')->where('period', $month)->exists()) {
            $made += self::write('month', $month, array_filter(GameScores::ranking('month', $month), fn ($r) => $r['days'] >= GameScores::MONTH_MIN_DAYS), 'days');
            Cache::put('hp.titles.done.'.$month, true, 86400 * 40);
        }
        $year = (string) ($now->year - 1);
        if ($year.'-12' >= self::FIRST_MONTH && ! Cache::get('hp.titles.done.'.$year) && ! DB::table('game_titles')->where('kind', 'year')->where('period', $year)->exists()) {
            $made += self::write('year', $year, array_filter(GameScores::ranking('year', $year), fn ($r) => $r['months'] >= GameScores::YEAR_MIN_MONTHS), 'months');
            Cache::put('hp.titles.done.'.$year, true, 86400 * 40);
        }
        if ($made) {
            Cache::forget('hp.titles.marks');
        }

        return $made;
    }

    private static function write(string $kind, string $period, array $ranking, string $count): int
    {
        $place = 0;
        foreach ($ranking as $r) {
            if ($r['hidden'] || $r['points'] < 1) {
                continue;   // a player who hid their name is not on the board, so not on the roll either
            }
            if (++$place > 3) {
                break;
            }
            DB::table('game_titles')->insertOrIgnore(['user_id' => $r['user_id'], 'kind' => $kind, 'period' => $period, 'place' => $place, 'points' => $r['points'], 'days' => $r[$count], 'created_at' => now()]);
        }

        return min(3, $place);
    }

    /** "ডিসেম্বর ২০২৬" or "২০২৭" */
    public static function label(string $kind, string $period): string
    {
        return $kind === 'month' ? Feelings::monthLabel($period) : Bn::year((int) $period);
    }

    /** The name for being বছরের সেরা in this many years, or null. */
    public static function multiName(int $years): ?string
    {
        return $years >= 3 ? self::MULTI[3] : ($years === 2 ? self::MULTI[2] : null);
    }

    /**
     * A player's titles. @return array{any: bool, multi: ?string, years: list<string>, yearPlaces: array<int, int>, months: list<string>, monthPlaces: array<int, int>}
     */
    public static function of(User $u): array
    {
        $rows = DB::table('game_titles')->where('user_id', $u->id)->orderBy('period')->get(['kind', 'period', 'place']);
        $out = ['any' => $rows->isNotEmpty(), 'multi' => null, 'years' => [], 'yearPlaces' => [], 'months' => [], 'monthPlaces' => []];
        foreach ($rows as $r) {
            $k = $r->kind === 'year' ? 'year' : 'month';
            $out[$k.'Places'][(int) $r->place] = ($out[$k.'Places'][(int) $r->place] ?? 0) + 1;
            if ((int) $r->place === 1) {
                $out[$k.'s'][] = $r->period;
            }
        }
        $out['multi'] = self::multiName(count($out['years']));

        return $out;
    }

    /**
     * The small marks beside a name on the board: at most two, the rarest first.
     * @return array<int, list<string>> user id => ['yy' | 'y' | 'm', …]
     */
    public static function marks(): array
    {
        return Cache::remember('hp.titles.marks', 600, function () {
            $by = [];
            foreach (DB::table('game_titles')->where('place', 1)->get(['user_id', 'kind']) as $r) {
                $by[$r->user_id][$r->kind] = ($by[$r->user_id][$r->kind] ?? 0) + 1;
            }
            $out = [];
            foreach ($by as $userId => $n) {
                $marks = [];
                if (($n['year'] ?? 0) >= 2) {
                    $marks[] = 'yy';
                } elseif (($n['year'] ?? 0) === 1) {
                    $marks[] = 'y';
                }
                if ($n['month'] ?? 0) {
                    $marks[] = 'm';
                }
                $out[(int) $userId] = $marks;
            }

            return $out;
        });
    }

    /**
     * The roll of honour. @return array{year: ?array, lastMonth: list<array>, years: list<array>, months: list<array>}
     * each entry: [period, label, place, name, points]
     */
    public static function hall(): array
    {
        $rows = DB::table('game_titles')->join('users', 'users.id', '=', 'game_titles.user_id')->orderByDesc('period')->orderBy('place')
            ->get(['game_titles.user_id', 'kind', 'period', 'place', 'points', 'users.name']);
        $names = \App\Models\Author::whereIn('user_id', $rows->pluck('user_id')->unique())->whereNotNull('display_name_bn')->pluck('display_name_bn', 'user_id');
        $list = fn (string $kind) => $rows->where('kind', $kind)->map(fn ($r) => ['period' => $r->period, 'label' => self::label($kind, $r->period), 'place' => (int) $r->place,
            'name' => (string) ($names[$r->user_id] ?? $r->name), 'points' => (int) $r->points, 'user_id' => (int) $r->user_id])->values()->all();
        $years = $list('year');
        $months = $list('month');
        $last = $months[0]['period'] ?? null;

        return ['year' => collect($years)->firstWhere('place', 1), 'lastMonth' => array_values(array_filter($months, fn ($m) => $m['period'] === $last)), 'years' => $years, 'months' => $months];
    }

    /** A scalloped ring round (cx, cy): $n petals between radius $r and $o. */
    private static function scallop(float $cx, float $cy, float $r, float $o, int $n): string
    {
        $d = '';
        for ($k = 0; $k < $n; $k++) {
            [$a, $m, $b] = [2 * M_PI * $k / $n, 2 * M_PI * ($k + .5) / $n, 2 * M_PI * ($k + 1) / $n];
            $d .= ($k ? '' : 'M'.round($cx + $r * cos($a), 1).' '.round($cy + $r * sin($a), 1))
                .'Q'.round($cx + $o * cos($m), 1).' '.round($cy + $o * sin($m), 1).' '.round($cx + $r * cos($b), 1).' '.round($cy + $r * sin($b), 1);
        }

        return $d.'Z';
    }

    /**
     * The title as an object (with a visual designer): the same family as the medals, but another outline, so it reads
     * as rarer at a glance. month: a rosette on one ribbon. year: a crowned rosette with a double rim on two ribbons.
     * multi: the crowned rosette on a shield with rays. Second and third place are sky and peach. Logo palette only.
     * $label goes on the face (a year, or a short word); $count on a small disc ("×৪").
     */
    public static function svg(string $kind, int $place = 1, int $size = 92, string $label = '', int $count = 0, bool $earned = true): string
    {
        [$N, $R, $PE, $CR, $SK] = ['#13212C', '#CB1B32', '#EFC08D', '#FFFBF4', '#2DAAE2'];
        if (! $earned) {   // a cut-out to be filled: what there is to chase
            return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.round($size * 1.25).'" viewBox="0 0 120 150" aria-hidden="true" focusable="false">'
                .'<path d="M50 100V146L60 134L70 146V100Z" fill="none" stroke="#4D5A63" stroke-width="5" stroke-dasharray="9 7"/><circle cx="60" cy="62" r="46" fill="'.$CR.'" stroke="#4D5A63" stroke-width="5" stroke-dasharray="9 7"/></svg>';
        }
        $face = [1 => $R, 2 => $SK, 3 => $PE][$place] ?? $R;
        $ink = $place === 1 ? '#FFFFFF' : $N;
        $s = '';
        if ($kind === 'multi') {
            $s .= '<path d="M12 22H108V86Q108 122 60 146Q12 122 12 86Z" fill="'.$N.'"/>';
            for ($k = 0; $k <= 8; $k++) {
                $a = M_PI * (1 + $k / 8);
                $s .= '<path d="M60 70L'.round(60 + 70 * cos($a), 1).' '.round(70 + 70 * sin($a), 1).'" stroke="'.$SK.'" stroke-width="3"/>';
            }
        } else {
            foreach ($kind === 'month' ? [[52, 68]] : [[34, 54], [66, 86]] as [$a, $b]) {
                $s .= '<path d="M'.$a.' 96V146L'.(($a + $b) / 2).' 136L'.$b.' 146V96Z" fill="'.($kind === 'month' ? $PE : $R).'" stroke="'.$N.'" stroke-width="2.5" stroke-linejoin="round"/>';   // peach, so it shows on a red card too
            }
        }
        if ($kind !== 'month') {
            $s .= '<path d="M38 24L44 4L54 16L60 0L66 16L76 4L82 24Z" fill="'.$PE.'" stroke="'.$N.'" stroke-width="2.5" stroke-linejoin="round"/>';
        }
        $cy = $kind === 'multi' ? 72 : 66;
        $rr = $kind === 'month' ? 40 : 42;
        $s .= '<path d="'.self::scallop(60, $cy, $rr, $rr + 8, $kind === 'month' ? 8 : 12).'" fill="'.$PE.'" stroke="'.$N.'" stroke-width="3" stroke-linejoin="round"/>';
        if ($kind !== 'month') {
            $s .= '<circle cx="60" cy="'.$cy.'" r="'.($rr - 3).'" fill="none" stroke="'.$N.'" stroke-width="2"/>';
        }
        $s .= '<circle cx="60" cy="'.$cy.'" r="'.($rr - 8).'" fill="'.$face.'" stroke="'.$N.'" stroke-width="2.5"/>';
        // the logo's fan
        [$fy, $fr] = $kind === 'month' ? [$cy + 6, 24] : [$cy - 2, 18];
        if ($label === '') {
            [$fy, $fr] = [$cy + 10, 26];
        }
        for ($k = 0; $k < 8; $k++) {
            [$a, $b] = [M_PI + M_PI * $k / 8, M_PI + M_PI * ($k + 1) / 8];
            $s .= '<path d="M60 '.$fy.'L'.round(60 + $fr * cos($a), 1).' '.round($fy + $fr * sin($a), 1).'A'.$fr.' '.$fr.' 0 0 1 '.round(60 + $fr * cos($b), 1).' '.round($fy + $fr * sin($b), 1).'Z" fill="'.($k % 2 ? $PE : $CR).'"/>';
        }
        $s .= '<circle cx="60" cy="'.$fy.'" r="'.round($fr * .3, 1).'" fill="'.$CR.'" stroke="'.$N.'" stroke-width="1.5"/>';
        if ($label !== '') {
            $s .= '<text x="60" y="'.($cy + ($kind === 'month' ? 26 : 24)).'" text-anchor="middle" font-family="\'Anek Bangla\',\'Noto Sans Bengali\',sans-serif" font-weight="800" font-size="'.($kind === 'month' ? 13 : 17).'" fill="'.$ink.'">'.e($label).'</text>';
        }
        if ($count > 1) {
            $s .= '<circle cx="98" cy="'.($cy + 36).'" r="15" fill="'.$CR.'" stroke="'.$N.'" stroke-width="2.5"/><text x="98" y="'.($cy + 42).'" text-anchor="middle" font-family="\'Anek Bangla\',\'Noto Sans Bengali\',sans-serif" font-weight="800" font-size="16" fill="'.$N.'">×'.Bn::num((string) $count).'</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.round($size * 1.25).'" viewBox="0 0 120 150" aria-hidden="true" focusable="false">'.$s.'</svg>';
    }

    /**
     * The mark beside a name, redrawn after the visual review (the first one read as a red dot): a small rosette with
     * its two ribbon tails and a cream heart; the year's has a crown; several years' is a shield with a cream field.
     */
    public static function mark(string $kind, int $size = 24): string
    {
        [$N, $R, $PE, $CR] = ['#13212C', '#CB1B32', '#EFC08D', '#FFFBF4'];
        if ($kind === 'yy') {
            $s = '<path d="M3 2H21V13Q21 19.5 12 23Q3 19.5 3 13Z" fill="'.$N.'"/><path d="M5.6 4.6H18.4V12.8Q18.4 17.6 12 20.2Q5.6 17.6 5.6 12.8Z" fill="'.$CR.'"/><circle cx="12" cy="11.4" r="4" fill="'.$R.'" stroke="'.$N.'" stroke-width="1.2"/>';
        } else {
            $cy = $kind === 'y' ? 12.5 : 9.5;
            $s = '<path d="M7.6 '.($cy + 3).'L6 23.5L9.6 21.4L12 '.($cy + 5).'L14.4 21.4L18 23.5L16.4 '.($cy + 3).'Z" fill="'.$N.'"/>'
                .($kind === 'y' ? '<path d="M6.5 6.4L7.6 1L10.4 4.2L12 .4L13.6 4.2L16.4 1L17.5 6.4Z" fill="'.$PE.'" stroke="'.$N.'" stroke-width="1.2" stroke-linejoin="round"/>' : '')
                .'<path d="'.self::scallop(12, $cy, 6.6, 8.8, 8).'" fill="'.$R.'" stroke="'.$N.'" stroke-width="1.4" stroke-linejoin="round"/><circle cx="12" cy="'.$cy.'" r="2.3" fill="'.$CR.'"/>';
        }
        $title = ['yy' => 'একাধিক বছরের সেরা', 'y' => 'বছরের সেরা', 'm' => 'মাসের সেরা'][$kind];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" role="img" aria-label="'.$title.'" class="flex-none"><title>'.$title.'</title>'.$s.'</svg>';
    }
}
