<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D177 (owner: "we need a leaderboard for games"): each signed-in player's result of the day (0-100 per game), and
 * the boards made from them: today, the last seven days, all time. Guests can play; only accounts are listed.
 * A player can hide their name in Settings.
 */
final class GameScores
{
    public const GAMES = ['word' => 'শব্দ-খেলা', 'riddle' => 'ধাঁধা', 'riddle2' => 'ধাঁধা (কঠিন)', 'cross' => 'শব্দজব্দ', 'cross1' => 'শব্দজব্দ (সহজ)', 'cross3' => 'শব্দজব্দ (কঠিন)', 'probad' => 'প্রবাদ পূরণ'];

    /**
     * D243 (owner: "plan properly for the points, make like proper games"; with a game designer): four games, each
     * worth 100 a day; the day counts a player's best three, so a full day is 300 whatever is added later.
     * group => [name, the game keys in it]
     */
    public const GROUPS = [
        'word' => ['শব্দ-খেলা', ['word']],
        'riddle' => ['ধাঁধা', ['riddle', 'riddle2']],
        'cross' => ['শব্দজব্দ', ['cross1', 'cross', 'cross3']],
        'probad' => ['প্রবাদ পূরণ', ['probad']],
    ];

    public const DAY_MAX = 300;

    /**
     * The first month that is judged for a title, and the day the longer word lists begin (owner: "start date 1 Dec").
     * The points themselves (ধাঁধা 40 + 60, শব্দজব্দ 20 + 30 + 50) change on the day the কঠিন ধাঁধা and প্রবাদ পূরণ
     * begin (WordGame::EXTRA_FROM): all four reviewers of the built screens found the old numbers beside "every game
     * is worth 100" wrong, and an easy riddle worth more than the hard one absurd. November is not judged either way.
     */
    public const RULES_FROM = '2026-12-01';

    /** A month's score is the best this-many days; a year's is the best this-many months. */
    public const MONTH_DAYS = 20;

    public const YEAR_MONTHS = 10;

    /** To be মাসের সেরা a player must have played this many days; to be বছরের সেরা, this many such months. */
    public const MONTH_MIN_DAYS = 10;

    public const YEAR_MIN_MONTHS = 6;

    /** Levels by all the points ever earned. [from, name] */
    public const LEVELS = [[0, 'পাঠক'], [1000, 'শব্দপ্রেমী'], [5000, 'শব্দসন্ধানী'], [15000, 'শব্দশিল্পী'], [40000, 'শব্দবিশারদ'], [100000, 'শব্দগুরু']];

    public static function newRules(string $day): bool
    {
        return $day >= WordGame::EXTRA_FROM;
    }

    /** ধাঁধা: [alone, with a letter shown] */
    public static function riddlePoints(string $game, string $day): array
    {
        return $game === 'riddle2' ? [60, 30] : (self::newRules($day) ? [40, 20] : [100, 50]);
    }

    /** শব্দজব্দ: [full points, cost of one shown square, the least a solved puzzle gives] */
    public static function crossPoints(string $level, string $day): array
    {
        [, , , , , , $full, $cost] = WordGame::LEVELS[$level];

        return self::newRules($day) ? ['easy' => [20, 2, 4], 'medium' => [30, 3, 6], 'hard' => [50, 5, 10]][$level] : [$full, $cost, 10];
    }

    /** Points from what the browser reports; the shape is checked, the answer itself is the player's honour. */
    public static function points(string $game, array $r, ?string $day = null): ?int
    {
        $day ??= now('Asia/Kolkata')->toDateString();
        $res = array_values((array) ($r['results'] ?? []));
        if ($game === 'word') {
            if (count($res) !== WordGame::PER_DAY || array_diff($res, ['g', 'y', 'r'])) {
                return null;
            }

            return array_sum(array_map(fn ($x) => ['g' => 20, 'y' => 10, 'r' => 0][$x], $res));
        }
        if ($game === 'probad') {   // g: first try, y: second try, r: shown
            if (count($res) !== Proverbs::PER_DAY || array_diff($res, ['g', 'y', 'r'])) {
                return null;
            }

            return array_sum(array_map(fn ($x) => ['g' => 25, 'y' => 10, 'r' => 0][$x], $res));
        }
        if ($game === 'riddle' || $game === 'riddle2') {
            [$alone, $helped] = self::riddlePoints($game, $day);

            return ['g' => $alone, 'y' => $helped, 'r' => 0][$res[0] ?? ''] ?? null;
        }
        foreach (WordGame::LEVELS as $level => [, , $key]) {   // D201: each level of শব্দজব্দ has its own points
            if ($game === $key) {
                [$full, $cost, $least] = self::crossPoints($level, $day);
                $hints = (int) ($r['hints'] ?? -1);

                return $hints < 0 || $hints > 120 ? null : (($r['solved'] ?? false) ? max($least, $full - $cost * $hints) : 0);
            }
        }

        return null;
    }

    /** The first result of the day counts. Only today's puzzle (or yesterday's, finished just after midnight). */
    public static function record(User $user, string $game, string $day, int $points, int $seconds): bool
    {
        $today = now('Asia/Kolkata')->toDateString();
        if (! isset(self::GAMES[$game]) || ! in_array($day, [$today, now('Asia/Kolkata')->subDay()->toDateString()], true)) {
            return false;
        }
        $made = (bool) DB::table('game_scores')->insertOrIgnore([
            'user_id' => $user->id, 'game' => $game, 'day' => $day, 'points' => max(0, min(150, $points)),
            'seconds' => max(1, min(86400, $seconds)), 'created_at' => now(),
        ]);
        if ($made) {
            self::forget();
        }

        return $made;
    }

    /** The boards are kept for two minutes; a new result (or a name hidden or shown) is seen at once. */
    public static function forget(): void
    {
        foreach (['today', 'week', 'month', 'year'] as $range) {
            Cache::forget('hp.board.'.$range.'.'.self::period($range));
        }
    }

    /** A day's points from its games: each game gives at most 100, the best three count. @param array<string, int> $byGame */
    public static function dayTotal(array $byGame): int
    {
        $groups = [];
        foreach (self::GROUPS as $key => [, $games]) {
            $groups[$key] = min(100, array_sum(array_intersect_key($byGame, array_flip($games))));
        }
        rsort($groups);

        return min(self::DAY_MAX, array_sum(array_slice($groups, 0, 3)));
    }

    /** The best $n of a list of numbers, added up. */
    private static function best(array $values, int $n): int
    {
        rsort($values);

        return (int) array_sum(array_slice($values, 0, $n));
    }

    private static function period(string $range, ?Carbon $at = null): string
    {
        $at ??= now('Asia/Kolkata');

        return match ($range) {
            'month' => $at->format('Y-m'), 'year' => $at->format('Y'), default => $at->toDateString(),
        };
    }

    /**
     * Everyone's score for a stretch of days, best first. today and week: the days added up. month: the best 20 days.
     * year: the best 10 months (each month its best 20 days). Equal points: more days played first, then the fuller
     * total, then less time. Cached for two minutes as plain numbers and names (D18).
     *
     * @return list<array{user_id: int, name: string, hidden: bool, points: int, days: int, months: int, full: int, seconds: int}>
     */
    public static function ranking(string $range, ?string $period = null): array
    {
        $now = now('Asia/Kolkata');
        $period ??= self::period($range, $now);
        [$from, $to] = match ($range) {
            'today' => [$period, $period],
            'week' => [Carbon::parse($period)->subDays(6)->toDateString(), $period],
            'month' => [$period.'-01', Carbon::parse($period.'-01')->endOfMonth()->toDateString()],
            default => [$period.'-01-01', $period.'-12-31'],
        };
        $live = $period === self::period($range, $now);

        return Cache::remember('hp.board.'.$range.'.'.$period, $live ? 120 : 86400, function () use ($range, $from, $to) {
            $rows = DB::table('game_scores')->join('users', 'users.id', '=', 'game_scores.user_id')->whereNull('users.spam_reason')
                ->whereBetween('day', [$from, $to])
                ->groupBy('game_scores.user_id', 'day', 'game')
                ->selectRaw('game_scores.user_id, day, game, sum(points) as points, sum(seconds) as seconds')->get();
            $days = $secs = [];
            foreach ($rows as $r) {
                $days[$r->user_id][substr((string) $r->day, 0, 10)][$r->game] = (int) $r->points;
                $secs[$r->user_id] = ($secs[$r->user_id] ?? 0) + (int) $r->seconds;
            }
            if (! $days) {
                return [];
            }
            $users = DB::table('users')->whereIn('id', array_keys($days))->get(['id', 'name', 'role', 'board_hidden', 'game_blocked_at'])->keyBy('id');
            $names = \App\Models\Author::whereIn('user_id', array_keys($days))->whereNotNull('display_name_bn')->pluck('display_name_bn', 'user_id');
            $out = [];
            foreach ($days as $userId => $byDay) {
                $totals = array_map(fn ($games) => self::dayTotal($games), $byDay);
                $months = 0;
                if ($range === 'year') {
                    $byMonth = $played = [];
                    foreach ($totals as $day => $t) {
                        $byMonth[substr($day, 0, 7)][] = $t;
                    }
                    $scores = [];
                    foreach ($byMonth as $list) {
                        if (count($list) >= self::MONTH_MIN_DAYS) {
                            $months++;
                        }
                        $scores[] = self::best($list, self::MONTH_DAYS);
                    }
                    $points = self::best($scores, self::YEAR_MONTHS);
                } else {
                    $points = $range === 'month' ? self::best($totals, self::MONTH_DAYS) : array_sum($totals);
                }
                $out[] = ['user_id' => (int) $userId, 'name' => (string) ($names[$userId] ?? $users[$userId]->name), 'hidden' => $users[$userId]->board_hidden || $users[$userId]->game_blocked_at || $users[$userId]->role === \App\Enums\Role::Admin->value,   // D245: kept off by the desk counts as hidden; the site's admin never shows (owner, 2026-11-27)
                    'points' => (int) $points, 'days' => count($totals), 'months' => $months, 'full' => (int) array_sum($totals), 'seconds' => (int) $secs[$userId]];
            }
            usort($out, fn ($a, $b) => [$b['points'], $b['days'], $b['full'], $a['seconds'], $a['user_id']] <=> [$a['points'], $a['days'], $a['full'], $b['seconds'], $b['user_id']]);

            return $out;
        });
    }

    /**
     * @return array{rows: list<array{rank: int, user_id: int, name: string, points: int, days: int, me: bool}>, mine: ?array, count: int}
     */
    public static function board(string $range, ?User $me = null, int $top = 20): array
    {
        $rows = [];
        $mine = null;
        $rank = 0;
        $above = null;
        $count = 0;
        foreach (self::ranking($range) as $r) {
            if ($r['hidden'] && $r['user_id'] !== $me?->id) {
                continue;   // plays, but not listed
            }
            $rank += $r['hidden'] ? 0 : 1;
            $count += $r['hidden'] ? 0 : 1;
            $row = ['rank' => $r['hidden'] ? 0 : $rank, 'user_id' => $r['user_id'], 'name' => $r['name'], 'points' => $r['points'], 'days' => $r['days'], 'months' => $r['months'], 'me' => $r['user_id'] === $me?->id];
            if ($row['me']) {
                // only the next step up is told: never who is behind
                $mine = $row + ['gap' => $above !== null && ! $r['hidden'] && $rank > 1 ? max(1, $above - $r['points'] + 1) : null];
            }
            if (! $r['hidden']) {
                $above = $r['points'];
                if (count($rows) < $top) {
                    $rows[] = $row;
                }
            }
        }

        return ['rows' => $rows, 'mine' => $mine, 'count' => $count];
    }

    /** All the points a player ever earned (what the level and the শব্দ-শিকারি badge count). */
    public static function lifetime(User $user): int
    {
        return (int) DB::table('game_scores')->where('user_id', $user->id)->sum('points');
    }

    /** @return array{index: int, name: string, points: int, next: ?int, nextName: ?string, left: int, percent: int} */
    public static function level(int $points): array
    {
        $i = 0;
        foreach (self::LEVELS as $k => [$from]) {
            if ($points >= $from) {
                $i = $k;
            }
        }
        [$from, $name] = self::LEVELS[$i];
        [$next, $nextName] = self::LEVELS[$i + 1] ?? [null, null];

        return ['index' => $i, 'name' => $name, 'points' => $points, 'next' => $next, 'nextName' => $nextName, 'left' => $next ? $next - $points : 0,
            'percent' => $next ? (int) min(100, round(100 * ($points - $from) / max(1, $next - $from))) : 100];
    }

    /** @param list<int> $userIds @return array<int, string> the level's name of each player who has one above the first */
    public static function levelNames(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        return DB::table('game_scores')->whereIn('user_id', $userIds)->groupBy('user_id')->selectRaw('user_id, sum(points) as points')->pluck('points', 'user_id')
            ->map(fn ($p) => self::level((int) $p)['name'])->all();
    }

    /**
     * What a player has done today. @return array{total: int, games: array<string, int>, groups: list<array{key: string, name: string, points: int, done: bool}>}
     */
    public static function today(User $user, ?string $day = null): array
    {
        $day ??= now('Asia/Kolkata')->toDateString();
        $games = DB::table('game_scores')->where('user_id', $user->id)->where('day', $day)->pluck('points', 'game')->map(fn ($p) => (int) $p)->all();
        $groups = [];
        foreach (self::GROUPS as $key => [$name, $keys]) {
            $mine = array_intersect_key($games, array_flip($keys));
            $groups[] = ['key' => $key, 'name' => $name, 'points' => min(100, array_sum($mine)), 'done' => count($mine) > 0];
        }

        return ['total' => self::dayTotal($games), 'games' => $games, 'groups' => $groups];
    }

    /** Whole days from one date to a later one (dates as Y-m-d). */
    private static function gap(string $from, string $to): int
    {
        return intdiv(strtotime($to.' UTC') - strtotime($from.' UTC'), 86400);
    }

    /** Days with a full 300, and months with 20 or more days played. @return array{perfect: int, regular: int} */
    public static function feats(User $user): array
    {
        $byDay = [];
        foreach (DB::table('game_scores')->where('user_id', $user->id)->get(['day', 'game', 'points']) as $r) {
            $byDay[substr((string) $r->day, 0, 10)][$r->game] = (int) $r->points;
        }
        $months = [];
        $perfect = 0;
        foreach ($byDay as $day => $games) {
            $perfect += self::dayTotal($games) >= self::DAY_MAX ? 1 : 0;
            $months[substr($day, 0, 7)] = ($months[substr($day, 0, 7)] ?? 0) + 1;
        }

        return ['perfect' => $perfect, 'regular' => count(array_filter($months, fn ($n) => $n >= self::MONTH_DAYS))];
    }

    /**
     * Longest run of days with at least one game, and the current one. One missed day does not break a run, as long
     * as it is the only one in seven days (D243: a rest day, so a run is not lost to one busy day).
     *
     * @return array{best: int, now: int, rest: list<string>}
     */
    public static function streak(User $user): array
    {
        $days = DB::table('game_scores')->where('user_id', $user->id)->distinct()->orderBy('day')->pluck('day')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $best = $run = 0;
        $prev = $lastRest = null;
        $rest = [];
        foreach ($days as $d) {
            $gap = $prev ? self::gap($prev, $d) : 0;
            if ($gap === 1) {
                $run++;
            } elseif ($gap === 2 && (! $lastRest || self::gap($lastRest, $d) >= 7)) {
                $lastRest = Carbon::parse($d)->subDay()->toDateString();
                $rest[] = $lastRest;
                $run++;
            } else {
                $run = 1;
                $rest = [];
                $lastRest = null;   // a new run has its own rest day
            }
            $best = max($best, $run);
            $prev = $d;
        }
        $today = now('Asia/Kolkata')->toDateString();
        $since = $prev ? self::gap($prev, $today) : 99;
        // still alive: played today or yesterday, or missed only yesterday with no other rest day in the week
        $live = $since <= 1 || ($since === 2 && (! $lastRest || self::gap($lastRest, $today) >= 7));

        return ['best' => $best, 'now' => $live ? $run : 0, 'rest' => $live ? $rest : []];
    }
}
