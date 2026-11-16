<?php

namespace App\Support;

use App\Models\Serial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * D213 (owner: "yes, I want reader guess"): in a detective case (D212) a signed-in reader names who they think did it,
 * any time before the last episode comes out. One guess a reader a case; it can be changed until then; nobody else
 * sees it. When the last episode is out, the case page says how many guessed and how many were right, and lists the
 * names of those who were right and agreed to be named. Right or wrong is decided by the case's own plan (the
 * culprit's name), never by a person or an AI reading the guesses.
 */
final class CaseGuesses
{
    /** The people of the case a reader can name; empty when this serial is not a case with a named culprit. @return list<string> */
    public static function suspects(Serial $s): array
    {
        if ($s->writer !== 'serial_detective') {
            return [];
        }
        $names = array_values(array_unique(array_filter(array_map(fn ($c) => trim((string) ($c['name'] ?? '')), (array) ($s->bible['characters'] ?? [])))));

        return in_array(self::culprit($s), $names, true) ? $names : [];
    }

    public static function culprit(Serial $s): string
    {
        return trim((string) ($s->bible['solution']['culprit_name'] ?? ''));
    }

    /** Has the last planned episode been published? Then the answer is out. */
    public static function solved(Serial $s): bool
    {
        return $s->status === 'finished' || $s->liveEpisodes()->where('episode_no', '>=', $s->plannedEpisodes())->exists();
    }

    public static function open(Serial $s): bool
    {
        return $s->status === 'active' && self::suspects($s) !== [] && $s->liveEpisodes()->exists() && ! self::solved($s);
    }

    public static function mine(Serial $s, ?User $u): ?object
    {
        return $u ? DB::table('case_guesses')->where(['serial_id' => $s->id, 'user_id' => $u->id])->first() : null;
    }

    public static function store(Serial $s, User $u, string $suspect, ?string $why, bool $showName): bool
    {
        if (! self::open($s) || ! in_array($suspect, self::suspects($s), true)) {
            return false;
        }
        DB::table('case_guesses')->updateOrInsert(['serial_id' => $s->id, 'user_id' => $u->id],
            ['suspect' => $suspect, 'why' => filled($why) ? mb_substr(trim(strip_tags((string) $why)), 0, 600) : null, 'show_name' => $showName, 'updated_at' => now(), 'created_at' => now()]);

        return true;
    }

    /**
     * After the last episode: how the readers did. Null before that, so nothing leaks.
     *
     * @return ?array{total: int, right: int, names: list<string>}
     */
    public static function result(Serial $s): ?array
    {
        if (self::suspects($s) === [] || ! self::solved($s)) {
            return null;
        }
        $all = DB::table('case_guesses')->where('serial_id', $s->id);
        $right = (clone $all)->where('suspect', self::culprit($s))->orderBy('updated_at');
        $ids = (clone $right)->where('show_name', true)->limit(60)->pluck('user_id');
        $pen = \App\Models\Author::whereIn('user_id', $ids)->whereNotNull('display_name_bn')->pluck('display_name_bn', 'user_id');
        $names = User::whereKey($ids)->whereNull('spam_reason')->get(['id', 'name'])->sortBy(fn ($u) => $ids->search($u->id))->map(fn ($u) => (string) ($pen[$u->id] ?? $u->name))->values()->all();

        return ['total' => (clone $all)->count(), 'right' => (clone $right)->count(), 'names' => $names];
    }

    /** For the desk: how many named each person so far. @return array<string, int> */
    public static function tally(Serial $s): array
    {
        return DB::table('case_guesses')->where('serial_id', $s->id)->groupBy('suspect')->selectRaw('suspect, count(*) as n')->orderByDesc('n')->pluck('n', 'suspect')->map(fn ($n) => (int) $n)->all();
    }
}
