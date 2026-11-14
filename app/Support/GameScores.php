<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * D177 (owner: "we need a leaderboard for games"): each signed-in player's result of the day (0-100 per game), and
 * the boards made from them: today, the last seven days, all time. Guests can play; only accounts are listed.
 * A player can hide their name in Settings.
 */
final class GameScores
{
    public const GAMES = ['word' => 'শব্দ-খেলা', 'riddle' => 'ধাঁধা', 'cross' => 'শব্দজব্দ', 'cross1' => 'শব্দজব্দ (সহজ)', 'cross3' => 'শব্দজব্দ (কঠিন)'];

    /** Points from what the browser reports; the shape is checked, the answer itself is the player's honour. */
    public static function points(string $game, array $r): ?int
    {
        if ($game === 'word') {
            $res = array_values((array) ($r['results'] ?? []));
            if (count($res) !== WordGame::PER_DAY || array_diff($res, ['g', 'y', 'r'])) {
                return null;
            }

            return array_sum(array_map(fn ($x) => ['g' => 20, 'y' => 10, 'r' => 0][$x], $res));
        }
        if ($game === 'riddle') {
            $x = ((array) ($r['results'] ?? []))[0] ?? null;

            return ['g' => 100, 'y' => 50, 'r' => 0][$x] ?? null;
        }
        foreach (WordGame::LEVELS as [, , $key, , , , $full, $cost]) {   // D201: each level of শব্দজব্দ has its own points
            if ($game === $key) {
                $hints = (int) ($r['hints'] ?? -1);

                return $hints < 0 || $hints > 120 ? null : (($r['solved'] ?? false) ? max(10, $full - $cost * $hints) : 0);
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

        return (bool) DB::table('game_scores')->insertOrIgnore([
            'user_id' => $user->id, 'game' => $game, 'day' => $day, 'points' => max(0, min(150, $points)),
            'seconds' => max(1, min(86400, $seconds)), 'created_at' => now(),
        ]);
    }

    /**
     * @return array{rows: list<array{rank: int, name: string, points: int, days: int, me: bool}>, mine: ?array}
     */
    public static function board(string $range, ?User $me = null, int $top = 20): array
    {
        $today = now('Asia/Kolkata');
        $q = DB::table('game_scores')->join('users', 'users.id', '=', 'game_scores.user_id')->whereNull('users.spam_reason');
        $q = match ($range) {
            'today' => $q->where('day', $today->toDateString()),
            'week' => $q->where('day', '>=', $today->copy()->subDays(6)->toDateString()),
            default => $q,
        };
        $all = $q->groupBy('game_scores.user_id', 'users.name', 'users.board_hidden')
            ->selectRaw('game_scores.user_id, users.name, users.board_hidden, sum(points) as points, sum(seconds) as seconds, count(distinct day) as days')
            ->orderByDesc('points')->orderBy('seconds')->orderBy('game_scores.user_id')->limit(500)->get();
        $names = \App\Models\Author::whereIn('user_id', $all->pluck('user_id'))->whereNotNull('display_name_bn')->pluck('display_name_bn', 'user_id');
        $rows = [];
        $mine = null;
        $rank = 0;
        foreach ($all as $r) {
            if ($r->board_hidden && $r->user_id !== $me?->id) {
                continue;   // plays, but not listed
            }
            $rank += $r->board_hidden ? 0 : 1;
            $row = ['rank' => $r->board_hidden ? 0 : $rank, 'name' => (string) ($names[$r->user_id] ?? $r->name), 'points' => (int) $r->points, 'days' => (int) $r->days, 'me' => $r->user_id === $me?->id];
            if ($row['me']) {
                $mine = $row;
            }
            if (! $r->board_hidden && count($rows) < $top) {
                $rows[] = $row;
            }
        }

        return ['rows' => $rows, 'mine' => $mine];
    }

    /** Longest run of days in a row with at least one game, and the current one. @return array{best: int, now: int} */
    public static function streak(User $user): array
    {
        $days = DB::table('game_scores')->where('user_id', $user->id)->distinct()->orderBy('day')->pluck('day')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $best = $run = 0;
        $prev = null;
        foreach ($days as $d) {
            $run = $prev && Carbon::parse($prev)->addDay()->toDateString() === $d ? $run + 1 : 1;
            $best = max($best, $run);
            $prev = $d;
        }
        $today = now('Asia/Kolkata')->toDateString();
        $live = $prev && in_array($prev, [$today, now('Asia/Kolkata')->subDay()->toDateString()], true) ? $run : 0;

        return ['best' => $best, 'now' => $live];
    }
}
