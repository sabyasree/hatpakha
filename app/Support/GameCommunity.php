<?php

namespace App\Support;

use App\Ai\Claude;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D331 (owner's list), all running by themselves (D330: no admin work for the games):
 *  - আজকের আড্ডা: a short talk under today's games, open only to players who have played today (so answers spoil
 *    nothing); every message is read by the AI that screens comments and shown only when it is fine.
 *  - পাঠকের ধাঁধা: riddles sent by readers; the AI keeps those that are solvable, fair and family-friendly; one a
 *    day is shown on the ধাঁধা page with the sender's name (no points).
 *  - জায়গার লড়াই: a player may say where they play from; each month the places are compared by their players'
 *    average points (three players at least), so a small district can beat a big city.
 */
final class GameCommunity
{
    public const TALK_PER_DAY = 5;

    /** West Bengal's districts, Bangladesh's divisions, and Bengalis abroad by country. */
    public const REGIONS = [
        'পশ্চিমবঙ্গ' => ['কলকাতা', 'উত্তর ২৪ পরগনা', 'দক্ষিণ ২৪ পরগনা', 'হাওড়া', 'হুগলি', 'নদিয়া', 'পূর্ব বর্ধমান', 'পশ্চিম বর্ধমান', 'বীরভূম', 'বাঁকুড়া', 'পুরুলিয়া',
            'পূর্ব মেদিনীপুর', 'পশ্চিম মেদিনীপুর', 'ঝাড়গ্রাম', 'মুর্শিদাবাদ', 'মালদহ', 'উত্তর দিনাজপুর', 'দক্ষিণ দিনাজপুর', 'জলপাইগুড়ি', 'আলিপুরদুয়ার', 'কোচবিহার', 'দার্জিলিং', 'কালিম্পং'],
        'বাংলাদেশ' => ['ঢাকা বিভাগ', 'চট্টগ্রাম বিভাগ', 'রাজশাহী বিভাগ', 'খুলনা বিভাগ', 'বরিশাল বিভাগ', 'সিলেট বিভাগ', 'রংপুর বিভাগ', 'ময়মনসিংহ বিভাগ'],
        'ভারতের অন্য রাজ্য' => ['ত্রিপুরা', 'আসাম', 'দিল্লি', 'মহারাষ্ট্র', 'কর্ণাটক', 'ঝাড়খণ্ড', 'ওড়িশা', 'বিহার', 'তামিলনাড়ু', 'তেলেঙ্গানা', 'ভারতের অন্য রাজ্য'],
        'প্রবাসে' => ['যুক্তরাষ্ট্র', 'যুক্তরাজ্য', 'কানাডা', 'অস্ট্রেলিয়া', 'মধ্যপ্রাচ্য', 'ইউরোপ', 'জাপান', 'সিঙ্গাপুর-মালয়েশিয়া', 'অন্য দেশ'],
    ];

    public static function regionOk(string $r): bool
    {
        return in_array($r, array_merge(...array_values(self::REGIONS)), true);
    }

    // ------------------------------------------------------------------ আজকের আড্ডা

    public static function playedToday(User $user): bool
    {
        return DB::table('game_scores')->where('user_id', $user->id)->where('day', now('Asia/Kolkata')->toDateString())->exists();
    }

    /** A message: stored, then read by the AI at once; shown only if fine. Returns 'shown', 'hidden' or 'wait'. */
    public static function talk(User $user, string $body): string
    {
        $body = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($body))), 0, 300);
        $id = DB::table('game_talk')->insertGetId(['user_id' => $user->id, 'day' => now('Asia/Kolkata')->toDateString(), 'body' => $body, 'created_at' => now(), 'updated_at' => now()]);
        self::screen([$id]);

        return (string) DB::table('game_talk')->where('id', $id)->value('status') === 'new' ? 'wait' : (string) DB::table('game_talk')->where('id', $id)->value('status');
    }

    /** The AI reads waiting messages (also hourly, for any the AI could not read at once). */
    public static function screen(?array $ids = null): int
    {
        $rows = DB::table('game_talk')->where('status', 'new')->when($ids, fn ($q) => $q->whereIn('id', $ids))->limit(30)->get(['id', 'body']);
        if ($rows->isEmpty() || Claude::blocked()) {
            return 0;
        }
        [$d] = Claude::json('comment_screen',
            'You screen short messages players write under a family-friendly Bengali word-game page of a literary magazine. ok=true for ordinary chat: '
            .'talk about the puzzles (answers are fine, only players who finished can see), greetings, jokes, praise, mild complaints. ok=false for anything rude, '
            .'sexual, hateful, a personal attack, political campaigning, advertising, links, phone numbers or personal details. Give a short reason in Bengali.',
            $rows->map(fn ($r) => '<msg id="'.$r->id.'">'.e($r->body).'</msg>')->implode("\n"),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['checks'], 'properties' => ['checks' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'ok', 'reason'],
                'properties' => ['id' => ['type' => 'integer'], 'ok' => ['type' => 'boolean'], 'reason' => ['type' => 'string']]]]]],
            config('ai.anthropic.tags_model'), 2000);
        $done = 0;
        foreach ((array) ($d['checks'] ?? []) as $c) {
            if ($rows->contains('id', (int) $c['id'])) {
                $link = preg_match('~https?://|www\.|\d{10}~u', (string) $rows->firstWhere('id', (int) $c['id'])->body);
                DB::table('game_talk')->where('id', (int) $c['id'])->update(['status' => ! empty($c['ok']) && ! $link ? 'shown' : 'hidden', 'reason' => mb_substr((string) $c['reason'], 0, 200), 'updated_at' => now()]);
                $done++;
            }
        }

        return $done;
    }

    /** @return list<array{name: string, body: string, at: string, mine: bool}> today's shown messages, newest first */
    public static function today(?User $me = null): array
    {
        $rows = DB::table('game_talk')->join('users', 'users.id', '=', 'game_talk.user_id')->where('day', now('Asia/Kolkata')->toDateString())
            ->where(fn ($q) => $q->where('game_talk.status', 'shown')->when($me, fn ($w) => $w->orWhere('game_talk.user_id', $me->id)))
            ->orderByDesc('game_talk.id')->limit(40)->get(['game_talk.user_id', 'game_talk.body', 'game_talk.status', 'game_talk.created_at', 'users.name', 'users.board_hidden']);
        $names = \App\Models\Author::whereIn('user_id', $rows->pluck('user_id'))->whereNotNull('display_name_bn')->pluck('display_name_bn', 'user_id');

        return $rows->map(fn ($r) => ['name' => $r->board_hidden ? 'এক খেলোয়াড়' : (string) ($names[$r->user_id] ?? $r->name), 'body' => $r->body,
            'at' => \Illuminate\Support\Carbon::parse($r->created_at)->setTimezone('Asia/Kolkata')->format('H:i'), 'mine' => $me && (int) $r->user_id === $me->id, 'waiting' => $r->status !== 'shown'])->all();
    }

    // ------------------------------------------------------------------ পাঠকের ধাঁধা

    /** A reader's riddle: the AI decides at once (or hourly). Returns 'ok', 'no' or 'wait'. */
    public static function sendRiddle(User $user, string $riddle, string $answer): string
    {
        $answer = \Normalizer::normalize(trim($answer), \Normalizer::FORM_C) ?: '';
        $id = DB::table('reader_riddles')->insertGetId(['user_id' => $user->id, 'riddle' => mb_substr(trim(strip_tags($riddle)), 0, 300), 'answer' => mb_substr($answer, 0, 40),
            'created_at' => now(), 'updated_at' => now()]);
        self::screenRiddles([$id]);

        return (string) DB::table('reader_riddles')->where('id', $id)->value('status') === 'new' ? 'wait' : (string) DB::table('reader_riddles')->where('id', $id)->value('status');
    }

    public static function screenRiddles(?array $ids = null): int
    {
        $rows = DB::table('reader_riddles')->where('status', 'new')->when($ids, fn ($q) => $q->whereIn('id', $ids))->limit(20)->get(['id', 'riddle', 'answer']);
        if ($rows->isEmpty() || Claude::blocked()) {
            return 0;
        }
        [$d] = Claude::json('words',
            'Readers of a Bengali literary magazine send riddles (ধাঁধা) for a daily family word game. ok=true only if: it is a real riddle in Bengali whose answer is the given word '
            .'(the clues fit it and point to it more than to anything else); a thoughtful reader can solve it; the answer is not written in the riddle; it is family-friendly, '
            .'not mocking any person, group, caste, religion or region; no links or personal details. Traditional riddles are welcome. Give a short reason in Bengali.',
            $rows->map(fn ($r) => '<riddle id="'.$r->id.'"><text>'.e($r->riddle).'</text><answer>'.e($r->answer).'</answer></riddle>')->implode("\n"),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['checks'], 'properties' => ['checks' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['id', 'ok', 'reason'],
                'properties' => ['id' => ['type' => 'integer'], 'ok' => ['type' => 'boolean'], 'reason' => ['type' => 'string']]]]]],
            config('ai.anthropic.tags_model'), 2000);
        $done = 0;
        foreach ((array) ($d['checks'] ?? []) as $c) {
            $row = $rows->firstWhere('id', (int) $c['id']);
            if ($row) {
                $ok = ! empty($c['ok']) && ! str_contains($row->riddle, $row->answer) && preg_match('/^[\x{0980}-\x{09FF}\s]+$/u', $row->answer);
                DB::table('reader_riddles')->where('id', $row->id)->update(['status' => $ok ? 'ok' : 'no', 'reason' => mb_substr((string) $c['reason'], 0, 200), 'updated_at' => now()]);
                $done++;
            }
        }

        return $done;
    }

    /** Today's reader's riddle (the oldest kept one, given to this day once). @return ?array{riddle: string, answer: string, name: string} */
    public static function todaysRiddle(): ?array
    {
        $today = now('Asia/Kolkata')->toDateString();
        $row = DB::table('reader_riddles')->where('shown_on', $today)->first();
        if (! $row && ($next = DB::table('reader_riddles')->where('status', 'ok')->whereNull('shown_on')->orderBy('id')->first())) {
            DB::table('reader_riddles')->where('id', $next->id)->whereNull('shown_on')->update(['shown_on' => $today, 'status' => 'used', 'updated_at' => now()]);
            $row = DB::table('reader_riddles')->where('shown_on', $today)->first();
        }
        if (! $row) {
            return null;
        }
        $user = User::find($row->user_id);
        $name = $user ? (string) (\App\Models\Author::where('user_id', $user->id)->value('display_name_bn') ?: $user->name) : 'এক পাঠক';

        return ['riddle' => $row->riddle, 'answer' => $row->answer, 'name' => $name];
    }

    // ------------------------------------------------------------------ জায়গার লড়াই

    /**
     * This month's places, best first: the average of their players' month points (players the desk kept off and
     * the admin left out), with at least three players. @return list<array{region: string, average: int, players: int}>
     */
    public static function regions(): array
    {
        return Cache::remember('hp.regions.'.now('Asia/Kolkata')->format('Y-m'), 300, function () {
            $where = DB::table('users')->whereNotNull('game_region')->whereNull('game_blocked_at')->where('role', '!=', \App\Enums\Role::Admin->value)->pluck('game_region', 'id')->all();
            if (! $where) {
                return [];
            }
            $points = collect(GameScores::ranking('month'))->pluck('points', 'user_id')->all();
            $by = [];
            foreach ($where as $uid => $region) {
                if (isset($points[$uid])) {
                    $by[$region][] = $points[$uid];
                }
            }
            $out = [];
            foreach ($by as $region => $list) {
                if (count($list) >= 3) {
                    $out[] = ['region' => (string) $region, 'average' => (int) round(array_sum($list) / count($list)), 'players' => count($list)];
                }
            }
            usort($out, fn ($a, $b) => [$b['average'], $b['players']] <=> [$a['average'], $a['players']]);

            return $out;
        });
    }
}
