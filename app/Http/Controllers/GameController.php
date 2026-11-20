<?php

namespace App\Http\Controllers;

use App\Seo\Seo;
use App\Support\GameScores;
use App\Support\GameTitles;
use App\Support\Proverbs;
use App\Support\Url;
use App\Support\WordGame;
use Illuminate\Http\Request;

/** D174, D177: the daily games (শব্দ-খেলা, ধাঁধা, শব্দজব্দ), the scores of signed-in players and the leaderboard. */
class GameController extends Controller
{
    public function word()
    {
        \App\Support\Ads::allow();   // D237: one ad under the game
        return view('game', ['game' => WordGame::day(), 'kind' => 'word', 'seo' => new Seo(
            title: 'শব্দ-খেলা: রোজ পাঁচটা বাংলা শব্দ',
            description: 'হাতপাখার রোজকার শব্দের খেলা। এলোমেলো অক্ষর সাজিয়ে পাঁচটা বাংলা শব্দ বানান, বন্ধুদের সঙ্গে মিলিয়ে দেখুন। রোজ রাত বারোটায় নতুন খেলা।',
            canonical: Url::to('/khela'),
        )]);
    }

    /** D243: two riddles a day. সহজ keeps the old address and is open to all; কঠিন needs an account. */
    public function riddle(Request $request, ?string $level = null)
    {
        $hard = $level === 'kothin';
        \App\Support\Ads::allow();
        $game = WordGame::riddle(null, $hard ? 'hard' : 'easy');
        abort_if(! $game['words'], 404);

        return view('game', ['game' => $game, 'kind' => $hard ? 'riddle2' : 'riddle', 'locked' => $hard && ! $request->user(),
            // the hard riddle's first day has no yesterday: asking for one would hand back today's
            'yesterday' => $hard && now('Asia/Kolkata')->subDay()->toDateString() < WordGame::EXTRA_FROM ? null : (WordGame::riddle(now('Asia/Kolkata')->subDay(), $hard ? 'hard' : 'easy')['words'][0] ?? null),
            'seo' => new Seo(
                title: $hard ? 'কঠিন ধাঁধা: রোজ একটা ভাবার মতো বাংলা ধাঁধা' : 'ধাঁধা: রোজ দুটো বাংলা ধাঁধা',
                description: $hard ? 'হাতপাখার রোজকার কঠিন ধাঁধা। ভেবে উত্তর বের করুন, অক্ষর সাজিয়ে মিলিয়ে দেখুন। ঠিক হলে ৬০ পয়েন্ট।'
                    : 'হাতপাখায় রোজ দুটো বাংলা ধাঁধা, একটা সহজ আর একটা কঠিন। ভেবে উত্তর বের করুন, অক্ষর সাজিয়ে মিলিয়ে দেখুন, বন্ধুদের জিজ্ঞেস করুন।',
                canonical: Url::to($hard ? '/khela/dhadha/kothin' : '/khela/dhadha'),
            )]);
    }

    /** D243: প্রবাদ পূরণ, the fourth daily game (with an account). */
    public function proverb(Request $request)
    {
        \App\Support\Ads::allow();
        $game = Proverbs::day();
        abort_if(count($game['items']) < Proverbs::PER_DAY, 404);

        return view('game-probad', ['game' => $game, 'locked' => ! $request->user(), 'seo' => new Seo(
            title: 'প্রবাদ পূরণ: রোজ চারটে বাংলা প্রবাদ',
            description: 'হাতপাখার রোজকার প্রবাদের খেলা। চারটে চেনা বাংলা প্রবাদ, প্রতিটিতে একটা শব্দ নেই; চারটে শব্দের মধ্যে ঠিকটা বেছে নিন।',
            canonical: Url::to('/khela/probad'),
        )]);
    }

    /** D243: today's games in one place: what is done, what is left, today's points, and who is ahead. */
    public function hub(Request $request)
    {
        \App\Support\Ads::allow();
        $me = $request->user();

        return view('game-hub', ['me' => $me, 'today' => $me ? GameScores::today($me) : null, 'streak' => $me ? GameScores::streak($me) : null,
            'level' => $me ? GameScores::level(GameScores::lifetime($me)) : null, 'month' => GameScores::board('month', $me, 1),
            'seo' => new Seo(
                title: 'আজকের খেলা: শব্দ-খেলা, ধাঁধা, শব্দজব্দ, প্রবাদ পূরণ',
                description: 'হাতপাখার রোজকার চারটে বাংলা খেলা এক জায়গায়। রোজ ৩০০ পয়েন্ট, মাসের সেরা আর বছরের সেরার খেতাব।',
                canonical: Url::to('/khela/aaj'),
            )]);
    }

    /** D201: three levels a day. মাঝারি keeps the old address; সহজ and কঠিন sit under it. */
    public function crossword(?string $level = null)
    {
        $key = ['' => 'medium', 'sohoj' => 'easy', 'kothin' => 'hard'][(string) $level] ?? abort(404);
        [$name] = WordGame::LEVELS[$key];
        \App\Support\Ads::allow();

        return view('crossword', ['game' => WordGame::crossword(null, $key), 'level' => $key, 'locked' => $key !== 'easy' && ! request()->user(), 'seo' => new Seo(
            title: $key === 'medium' ? 'শব্দজব্দ: রোজ একটা ছোট বাংলা শব্দছক' : 'শব্দজব্দ ('.$name.'): রোজকার বাংলা শব্দছক',
            description: [
                'easy' => 'হাতপাখার রোজকার সহজ শব্দজব্দ: ছোট ছক, চেনা ছোট শব্দ। কি-বোর্ড লাগে না, অক্ষরে টিপলেই হয়।',
                'medium' => 'হাতপাখার রোজকার ছোট শব্দজব্দ। সূত্র দেখে ছক ভরুন; কি-বোর্ড লাগে না, অক্ষরে টিপলেই হয়।',
                'hard' => 'হাতপাখার রোজকার কঠিন শব্দজব্দ: বড় ছক, বইয়ের পাতার শব্দ। সূত্র দেখে ছক ভরুন।',
            ][$key],
            canonical: WordGame::crosswordUrl($key),
        )]);
    }

    public function board(Request $request)
    {
        $me = $request->user();
        \App\Support\Ads::allow();
        GameTitles::award();   // the 1st of the month writes last month's names down; this is the safety net
        $boards = ['today' => GameScores::board('today', $me), 'month' => GameScores::board('month', $me), 'year' => GameScores::board('year', $me)];
        $ids = array_values(array_unique(array_merge(...array_map(fn ($b) => array_column($b['rows'], 'user_id'), array_values($boards)))));

        return view('game-board', ['boards' => $boards, 'me' => $me, 'hall' => GameTitles::hall(), 'marks' => GameTitles::marks(), 'levels' => GameScores::levelNames($ids), 'seo' => new Seo(
            title: 'খেলার লিডারবোর্ড: মাসের সেরা, বছরের সেরা',
            description: 'হাতপাখার শব্দ-খেলা, ধাঁধা, শব্দজব্দ আর প্রবাদ পূরণে আজ, এ মাসে আর এ বছরে কারা এগিয়ে; আর আগের মাস ও বছরের সেরাদের নাম।',
            canonical: Url::to('/khela/board'),
        )]);
    }

    /** A signed-in player's result of the day. */
    public function score(Request $request)
    {
        $data = $request->validate(['game' => ['required', 'string'], 'day' => ['required', 'date_format:Y-m-d'], 'seconds' => ['required', 'integer', 'min:1'],
            'results' => ['nullable', 'array', 'max:10'], 'hints' => ['nullable', 'integer'], 'solved' => ['nullable', 'boolean']]);
        $points = GameScores::points($data['game'], $data, $data['day']);
        if ($points === null) {
            return response()->json(['ok' => false], 422);
        }
        $user = $request->user();
        $before = GameScores::board('month', $user, 0)['mine']['rank'] ?? 0;
        $new = GameScores::record($user, $data['game'], $data['day'], $points, (int) $data['seconds']);
        $today = GameScores::today($user, $data['day']);
        $mine = GameScores::board('month', $user, 0)['mine'];
        $streak = GameScores::streak($user);
        // the next game not played today, in the order of the games' menu
        $urls = ['word' => Url::to('/khela'), 'riddle' => Url::to('/khela/dhadha'), 'cross' => WordGame::crosswordUrl('medium'), 'probad' => Url::to('/khela/probad')];
        $next = $data['game'] === 'riddle' && ! isset($today['games']['riddle2']) ? ['name' => 'কঠিন ধাঁধা', 'url' => Url::to('/khela/dhadha/kothin')] : null;
        foreach ($today['groups'] as $g) {
            if (! $g['done'] && ! $next) {
                $next = ['name' => $g['name'], 'url' => $urls[$g['key']]];
            }
        }
        if (! $next && isset($today['games']['riddle']) && ! isset($today['games']['riddle2'])) {
            $next = ['name' => 'কঠিন ধাঁধা', 'url' => Url::to('/khela/dhadha/kothin')];
        }
        $level = GameScores::level(GameScores::lifetime($user));

        return response()->json(['ok' => true, 'points' => $points, 'streak' => $streak['now'], 'today' => ['total' => $today['total'], 'max' => GameScores::DAY_MAX, 'groups' => $today['groups']],
            'rank' => ['now' => $mine['rank'] ?? 0, 'up' => $new && $before && ($mine['rank'] ?? 0) ? max(0, $before - $mine['rank']) : 0, 'gap' => $mine['gap'] ?? null],
            'next' => $next, 'level' => ['name' => $level['name'], 'nextName' => $level['nextName'], 'left' => $level['left'], 'percent' => $level['percent']]]);
    }

    /** D179: a player says a word, clue or riddle is wrong. No account needed. */
    public function report(Request $request)
    {
        $data = $request->validate(['game' => ['required', 'in:word,riddle,riddle2,cross,cross1,cross3,probad'], 'word' => ['required', 'string', 'max:60'],
            'reason' => ['required', 'in:'.implode(',', array_keys(\App\Support\WordReports::REASONS))], 'note' => ['nullable', 'string', 'max:300']]);
        $ok = \App\Support\WordReports::store($request, in_array($data['game'], ['riddle', 'riddle2'], true) ? 'riddle' : 'word', $data['word'], $data['reason'], $data['note'] ?? null);

        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }
}
