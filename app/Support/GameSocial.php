<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * D328 (owner: "the Social: সাপ্তাহিক লড়াই and পাড়ার দল").
 *
 * সাপ্তাহিক লড়াই: one set of ten questions a week (four spellings, three এক শব্দে প্রকাশ, three proverbs). A player
 * plays it, gets a link and sends it to friends; whoever opens the link plays the same ten and sees everyone's
 * score on that link. No account is needed to answer a challenge (a name is enough), which is how it brings new
 * readers in. Not part of the daily points: it is between friends.
 *
 * পাড়ার দল: a team of players (a school, a club, an association abroad, a family). A player is in one team at a time,
 * joins by the team's link and can leave at any time. A team's month is its ten best players' points this month
 * (the same points as the board, D243), so a big team cannot win by numbers alone. The desk can take a team off the
 * board (a name that should not be there).
 */
final class GameSocial
{
    public const QUESTIONS = 10;

    public const POINTS = 10;

    public const TEAM_BEST = 10;

    /** A name a player typed: plain, short, and never a link. Null when it cannot be used. */
    public static function name(?string $raw, int $max = 30): ?string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $raw)));
        $s = (string) preg_replace('/[^\p{L}\p{M}\p{N} .\-\'’]/u', '', $s);
        $s = mb_substr(trim($s), 0, $max);
        if (mb_strlen($s) < 2 || preg_match('~https?|www\.|\.(com|in|net|org|bd)\b~iu', $s)) {
            return null;
        }

        return $s;
    }

    /** The name a signed-in player is shown by on the boards. */
    public static function playerName(User $user): string
    {
        return (string) (\App\Models\Author::where('user_id', $user->id)->value('display_name_bn') ?: $user->name);
    }

    // ---------------------------------------------------------------- সাপ্তাহিক লড়াই

    /**
     * The week's ten questions: [kind, prompt, options, answer, note].
     * @return list<array{kind: string, prompt: string, options: list<string>, answer: string, note: string}>
     */
    public static function questions(string $week): array
    {
        return Cache::remember('hp.lorai.'.$week, 86400 * 8, function () use ($week) {
            $seed = crc32('lorai'.$week);
            $out = [];
            $pick = function (array $list, int $n, int $salt) use ($seed) {
                $list = array_values(array_filter($list));
                mt_srand($seed + $salt);
                $keys = $list ? (array) array_rand($list, min($n, count($list))) : [];
                mt_srand();

                return array_map(fn ($k) => $list[$k], $keys);
            };
            foreach ($pick(WordChoice::all('banan'), 4, 1) as $i => [$answer, $wrong, $note]) {
                $out[] = ['kind' => 'banan', 'prompt' => 'কোন বানানটি ঠিক?', 'options' => self::shuffled(array_merge([$answer], array_slice((array) $wrong, 0, 3)), $seed + 10 + $i), 'answer' => $answer, 'note' => (string) $note];
            }
            $one = array_values(array_filter(WordChoice::all('ekshobde')));
            $answers = array_column($one, 1);
            foreach ($pick($one, 3, 2) as $i => $row) {
                [$phrase, $answer, $note] = $row + [2 => ''];
                mt_srand($seed + 20 + $i);
                $decoys = WordChoice::decoys($answer, $phrase, $answers);
                mt_srand();
                $out[] = ['kind' => 'ekshobde', 'prompt' => $phrase, 'options' => self::shuffled(array_merge([$answer], $decoys), $seed + 30 + $i), 'answer' => $answer, 'note' => (string) $note];
            }
            $proverbs = Proverbs::all();
            $words = array_values(array_unique(array_column($proverbs, 1)));
            foreach ($pick($proverbs, 3, 3) as $i => [$full, $answer, $meaning]) {
                $parts = preg_split('/\s+/u', trim($full));
                $at = (int) array_search($answer, $parts, true);
                $others = array_values(array_filter($words, fn ($w) => $w !== $answer && ! in_array($w, $parts, true)));
                mt_srand($seed + 40 + $i);
                shuffle($others);
                mt_srand();
                $out[] = ['kind' => 'probad', 'prompt' => trim(implode(' ', array_slice($parts, 0, $at)).' ____ '.implode(' ', array_slice($parts, $at + 1))),
                    'options' => self::shuffled(array_merge([$answer], array_slice($others, 0, 3)), $seed + 50 + $i), 'answer' => $answer, 'note' => (string) $meaning];
            }
            // the kinds mixed, the same order for everyone this week
            mt_srand($seed + 99);
            shuffle($out);
            mt_srand();

            return $out;
        });
    }

    private static function shuffled(array $a, int $seed): array
    {
        mt_srand($seed);
        shuffle($a);
        mt_srand();

        return $a;
    }

    /**
     * Who is playing: the account, or else this browser's own long-lived cookie (set on the লড়াই page). Not the
     * address and the phone model: two guests on one home Wi-Fi with the same phone were taken for one person.
     */
    public static function key(Request $r): string
    {
        $pid = (string) $r->cookie('hp_pid', '');
        $who = $r->user() ? 'u'.$r->user()->id : (preg_match('/^[A-Za-z0-9]{20,40}$/', $pid) ? 'p'.$pid : 'ip'.$r->ip().'|'.mb_substr((string) $r->userAgent(), 0, 120));

        return hash_hmac('sha256', $who, (string) config('app.key'));
    }

    /** The code of the challenge this player started this week, if any. */
    public static function ownCode(Request $r): ?string
    {
        return DB::table('game_challenge_plays')->join('game_challenges', 'game_challenges.id', '=', 'game_challenge_plays.game_challenge_id')
            ->where('game_challenge_plays.sender_hash', self::key($r))->where('game_challenge_plays.started', true)
            ->where('game_challenges.week', WeeklyQuiz::week())->orderByDesc('game_challenges.id')->value('game_challenges.code');
    }

    /** A new challenge from a finished game; the maker's own result is the first on the link. */
    public static function challenge(Request $r, string $name, int $score, int $seconds): string
    {
        $week = WeeklyQuiz::week();
        do {
            $code = Str::lower(Str::random(8));
        } while (DB::table('game_challenges')->where('code', $code)->exists());
        $id = DB::table('game_challenges')->insertGetId(['code' => $code, 'week' => $week, 'created_at' => now(), 'updated_at' => now()]);
        self::play($r, $id, $name, $score, $seconds, true);

        return $code;
    }

    /** One result on a challenge; the first from a player (or a browser) counts. */
    public static function play(Request $r, int $challengeId, string $name, int $score, int $seconds, bool $started = false): bool
    {
        return (bool) DB::table('game_challenge_plays')->insertOrIgnore([
            'game_challenge_id' => $challengeId, 'user_id' => $r->user()?->id, 'name' => $name,
            'score' => max(0, min(self::QUESTIONS * self::POINTS, $score)), 'seconds' => max(1, min(3600, $seconds)), 'started' => $started,
            'sender_hash' => self::key($r), 'created_at' => now(),
        ]);
    }

    /** @return ?array{id: int, code: string, week: string, open: bool, plays: list<array{name: string, score: int, seconds: int, started: bool, mine: bool}>} */
    public static function find(string $code, ?Request $r = null): ?array
    {
        $c = DB::table('game_challenges')->where('code', Str::lower($code))->first();
        if (! $c) {
            return null;
        }
        $mine = $r ? self::key($r) : null;
        $plays = DB::table('game_challenge_plays')->where('game_challenge_id', $c->id)->orderByDesc('score')->orderBy('seconds')->orderBy('id')->limit(100)->get()
            ->map(fn ($p) => ['name' => $p->name, 'score' => (int) $p->score, 'seconds' => (int) $p->seconds, 'started' => (bool) $p->started, 'mine' => $mine !== null && $p->sender_hash === $mine])->all();
        $week = substr((string) $c->week, 0, 10);

        return ['id' => (int) $c->id, 'code' => $c->code, 'week' => $week, 'open' => $week === WeeklyQuiz::week(), 'plays' => $plays];
    }

    // ---------------------------------------------------------------- পাড়ার দল

    /**
     * D330 (owner: no admin work for games): a team's name is read by the AI that screens comments before the team is
     * made; a rude, hateful, political-campaign, advertising or personal-attack name is turned away with a kind word,
     * so the desk never has to take one down. Without the AI the name goes through (it can still be hidden at the desk).
     */
    public static function teamNameOk(string $name): bool
    {
        if (\App\Ai\Claude::blocked()) {
            return true;
        }
        [$d] = \App\Ai\Claude::json('comment_screen',
            'You check the name of a team in a family-friendly Bengali word game (schools, clubs, families, associations abroad). '
            .'ok=false if the name is rude, sexual, hateful, mocks a person, group, caste, religion or region, is a political or election slogan, or advertises something. Ordinary names, places, schools, jokes and nicknames are fine.',
            '<name>'.e($name).'</name>',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean']]],
            config('ai.anthropic.tags_model'), 200);

        return ! is_array($d) || ($d['ok'] ?? true) !== false;
    }

    public static function makeTeam(User $user, string $name): string
    {
        do {
            $code = Str::lower(Str::random(7));
        } while (DB::table('game_teams')->where('code', $code)->exists());
        $id = DB::table('game_teams')->insertGetId(['code' => $code, 'name' => $name, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        self::join($user, $id);

        return $code;
    }

    public static function join(User $user, int $teamId): void
    {
        DB::table('users')->where('id', $user->id)->update(['game_team_id' => $teamId, 'game_team_joined_at' => now()]);
        Cache::forget('hp.teams.'.now('Asia/Kolkata')->format('Y-m'));
    }

    public static function leave(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['game_team_id' => null, 'game_team_joined_at' => null]);
        Cache::forget('hp.teams.'.now('Asia/Kolkata')->format('Y-m'));
    }

    public static function teamOf(User $user): ?object
    {
        $id = DB::table('users')->where('id', $user->id)->value('game_team_id');

        return $id ? DB::table('game_teams')->where('id', $id)->first() : null;
    }

    /**
     * This month's teams, best first: the ten best players' month points added up. A team needs two players to be
     * listed. Players a reader cannot see on the board (hidden by choice) still give their team points, without
     * their name; players the desk kept off, and the admin, give nothing.
     *
     * @return list<array{id: int, code: string, name: string, points: int, players: int, rank: int}>
     */
    public static function teams(): array
    {
        $month = now('Asia/Kolkata')->format('Y-m');

        return Cache::remember('hp.teams.'.$month, 120, function () {
            $members = DB::table('users')->whereNotNull('game_team_id')->whereNull('game_blocked_at')->where('role', '!=', \App\Enums\Role::Admin->value)
                ->whereNull('spam_reason')->pluck('game_team_id', 'id')->all();
            if (! $members) {
                return [];
            }
            $points = collect(GameScores::ranking('month'))->pluck('points', 'user_id')->all();
            $byTeam = [];
            foreach ($members as $userId => $teamId) {
                $byTeam[$teamId][] = (int) ($points[$userId] ?? 0);
            }
            $teams = DB::table('game_teams')->whereIn('id', array_keys($byTeam))->whereNull('hidden_at')->get()->keyBy('id');
            $out = [];
            foreach ($byTeam as $teamId => $list) {
                if (! isset($teams[$teamId]) || count($list) < 2) {
                    continue;
                }
                rsort($list);
                $out[] = ['id' => (int) $teamId, 'code' => $teams[$teamId]->code, 'name' => $teams[$teamId]->name, 'points' => (int) array_sum(array_slice($list, 0, self::TEAM_BEST)), 'players' => count($list)];
            }
            usort($out, fn ($a, $b) => [$b['points'], $b['players'], $a['id']] <=> [$a['points'], $a['players'], $b['id']]);
            foreach ($out as $i => $t) {
                $out[$i]['rank'] = $i + 1;
            }

            return $out;
        });
    }

    /** A team's players this month, best first; hidden players are counted but not named. */
    public static function members(int $teamId, ?User $me = null): array
    {
        $ranking = collect(GameScores::ranking('month'))->keyBy('user_id');
        $users = DB::table('users')->where('game_team_id', $teamId)->get(['id', 'name', 'board_hidden', 'game_blocked_at', 'role']);
        $names = \App\Models\Author::whereIn('user_id', $users->pluck('id'))->whereNotNull('display_name_bn')->pluck('display_name_bn', 'user_id');
        $out = [];
        foreach ($users as $u) {
            $hidden = $u->board_hidden || $u->game_blocked_at || $u->role === \App\Enums\Role::Admin->value;
            $out[] = ['name' => $hidden && $u->id !== $me?->id ? null : (string) ($names[$u->id] ?? $u->name), 'points' => (int) ($ranking[$u->id]['points'] ?? 0), 'me' => $u->id === $me?->id,
                'counts' => ! $u->game_blocked_at && $u->role !== \App\Enums\Role::Admin->value];
        }
        usort($out, fn ($a, $b) => $b['points'] <=> $a['points']);

        return $out;
    }

    /** The day a week's challenge links stop taking answers. */
    public static function weekEnds(string $week): Carbon
    {
        return Carbon::parse($week, 'Asia/Kolkata')->addDays(6)->endOfDay();
    }
}
