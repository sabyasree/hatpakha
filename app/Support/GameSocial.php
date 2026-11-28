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

    /** D330 (game designers): five, so a family of four is not lost against a school. */
    public const TEAM_BEST = 5;

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
        return Cache::remember('hp.lorai.v2.'.$week, 86400 * 8, function () use ($week) {
            $seed = crc32('lorai'.$week);
            // D330 (game designers): a revision round of the last sixty days' daily questions, so the লড়াই neither
            // wears the lists out nor shows tomorrow's answers; the first weeks, with little past, use the whole lists
            $past = ['banan' => [], 'ekshobde' => [], 'probad' => []];
            for ($d = 1; $d <= 60; $d++) {
                $day = Carbon::parse($week, 'Asia/Kolkata')->subDays($d);
                if ($day->toDateString() < WordChoice::START) {
                    break;
                }
                foreach (['banan', 'ekshobde'] as $g) {
                    foreach (WordChoice::day($g, $day)['items'] as $q) {
                        $past[$g][$q['answer']] = ['kind' => $g, 'prompt' => $g === 'banan' ? 'কোন বানানটি ঠিক?' : $q['prompt'], 'options' => $q['options'], 'answer' => $q['answer'], 'note' => $q['note']];
                    }
                }
                foreach (Proverbs::day($day)['items'] as $q) {
                    $past['probad'][$q['full']] = ['kind' => 'probad', 'prompt' => trim($q['before'].' ____ '.$q['after']), 'options' => $q['options'], 'answer' => $q['answer'], 'note' => $q['meaning']];
                }
            }
            $need = ['banan' => 4, 'ekshobde' => 3, 'probad' => 3];
            $out = [];
            foreach ($need as $g => $n) {
                $pool = array_values($past[$g]);
                if (count($pool) >= $n * 3) {
                    mt_srand($seed + strlen($g));
                    shuffle($pool);
                    mt_srand();
                    $out = array_merge($out, array_slice($pool, 0, $n));
                } else {
                    $out = array_merge($out, self::fromLists($g, $n, $seed));
                }
            }
            // the kinds mixed, the same order for everyone this week
            mt_srand($seed + 99);
            shuffle($out);
            mt_srand();

            return $out;
        });
    }

    /** The first weeks: $n questions of a kind from the whole list. */
    private static function fromLists(string $g, int $n, int $seed): array
    {
        $pick = function (array $list, int $n, int $salt) use ($seed) {
            $list = array_values(array_filter($list));
            mt_srand($seed + $salt);
            $keys = $list ? (array) array_rand($list, min($n, count($list))) : [];
            mt_srand();

            return array_map(fn ($k) => $list[$k], $keys);
        };
        $out = [];
        if ($g === 'banan') {
            foreach ($pick(WordChoice::all('banan'), $n, 1) as $i => [$answer, $wrong, $note]) {
                $out[] = ['kind' => 'banan', 'prompt' => 'কোন বানানটি ঠিক?', 'options' => self::shuffled(array_merge([$answer], array_slice((array) $wrong, 0, 3)), $seed + 10 + $i), 'answer' => $answer, 'note' => (string) $note];
            }
        } elseif ($g === 'ekshobde') {
            $answers = array_column(array_values(array_filter(WordChoice::all('ekshobde'))), 1);
            foreach ($pick(WordChoice::all('ekshobde'), $n, 2) as $i => $row) {
                [$phrase, $answer, $note] = $row + [2 => ''];
                mt_srand($seed + 20 + $i);
                $decoys = WordChoice::decoys($answer, $phrase, $answers);
                mt_srand();
                $out[] = ['kind' => 'ekshobde', 'prompt' => $phrase, 'options' => self::shuffled(array_merge([$answer], $decoys), $seed + 30 + $i), 'answer' => $answer, 'note' => (string) $note];
            }
        } else {
            $proverbs = array_values(array_filter(Proverbs::all()));
            $words = array_values(array_unique(array_column($proverbs, 1)));
            foreach ($pick($proverbs, $n, 3) as $i => [$full, $answer, $meaning]) {
                $parts = preg_split('/\s+/u', trim($full));
                $at = (int) array_search($answer, $parts, true);
                $others = array_values(array_filter($words, fn ($w) => $w !== $answer && ! in_array($w, $parts, true)));
                mt_srand($seed + 40 + $i);
                shuffle($others);
                mt_srand();
                $out[] = ['kind' => 'probad', 'prompt' => trim(implode(' ', array_slice($parts, 0, $at)).' ____ '.implode(' ', array_slice($parts, $at + 1))),
                    'options' => self::shuffled(array_merge([$answer], array_slice($others, 0, 3)), $seed + 50 + $i), 'answer' => $answer, 'note' => (string) $meaning];
            }
        }

        return $out;
    }

    /** D330: one spelling question to answer in the chat itself ("বলুন তো, কোনটা ঠিক: দায়িত্ব না দায়িত্ত্ব?"). */
    public static function teaser(string $week): ?string
    {
        $q = collect(self::questions($week))->firstWhere('kind', 'banan');
        if (! $q) {
            return null;
        }
        $wrong = collect($q['options'])->first(fn ($o) => $o !== $q['answer']);
        $pair = crc32($week) % 2 ? [$q['answer'], $wrong] : [$wrong, $q['answer']];

        return 'বলুন তো, কোনটা ঠিক: '.$pair[0].' না '.$pair[1].'?';
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

    /**
     * D330 (game designers: a link pays off when you hear who answered): a signed-in maker who asked for game notes
     * on their phone hears that a friend played, at most once a day ("মিতা আপনার লড়াইয়ে পেলেন ৭০ (আপনি ৫০)").
     */
    public static function tellMaker(int $challengeId, string $friend, int $score): void
    {
        $maker = DB::table('game_challenge_plays')->where('game_challenge_id', $challengeId)->where('started', true)->first();
        if (! $maker || ! $maker->user_id || ! \App\Support\WebPush::configured()) {
            return;
        }
        $key = 'hp.lorai.told.'.$maker->user_id.'.'.now('Asia/Kolkata')->toDateString();
        if (! Cache::add($key, true, 86400)) {
            return;
        }
        $code = DB::table('game_challenges')->where('id', $challengeId)->value('code');
        foreach (\App\Models\PushSubscription::where('user_id', $maker->user_id)->whereJsonContains('topics', 'games')->get() as $sub) {
            \App\Support\WebPush::send($sub, ['title' => $friend.' আপনার লড়াইয়ে খেললেন', 'body' => $friend.' পেলেন '.Bn::num((string) $score).' (আপনি '.Bn::num((string) $maker->score).')। কে এগিয়ে, দেখুন।',
                'url' => Url::to('/khela/lorai/'.$code), 'tag' => 'lorai', 'kind' => 'lorai']);
        }
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

    public static function makeTeam(User $user, string $name, bool $private = false): string
    {
        do {
            $code = Str::lower(Str::random(7));
        } while (DB::table('game_teams')->where('code', $code)->exists());
        $id = DB::table('game_teams')->insertGetId(['code' => $code, 'name' => $name, 'user_id' => $user->id, 'private' => $private, 'created_at' => now(), 'updated_at' => now()]);
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
            $teams = DB::table('game_teams')->whereIn('id', array_keys($byTeam))->whereNull('hidden_at')->where('private', false)->get()->keyBy('id');   // D331: a private circle is never listed
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
        // D330: who has played today, for the family's own scoreboard
        $today = [];
        foreach (DB::table('game_scores')->whereIn('user_id', $users->pluck('id'))->where('day', now('Asia/Kolkata')->toDateString())->get(['user_id', 'game', 'points'])->groupBy('user_id') as $uid => $rows) {
            $today[$uid] = GameScores::dayTotal($rows->mapWithKeys(fn ($r) => [$r->game => (int) $r->points])->all());
        }
        $out = [];
        foreach ($users as $u) {
            $hidden = $u->board_hidden || $u->game_blocked_at || $u->role === \App\Enums\Role::Admin->value;
            $out[] = ['name' => $hidden && $u->id !== $me?->id ? null : (string) ($names[$u->id] ?? $u->name), 'points' => (int) ($ranking[$u->id]['points'] ?? 0), 'me' => $u->id === $me?->id,
                'counts' => ! $u->game_blocked_at && $u->role !== \App\Enums\Role::Admin->value, 'today' => $today[$u->id] ?? null];
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
