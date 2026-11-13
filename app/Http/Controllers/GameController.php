<?php

namespace App\Http\Controllers;

use App\Seo\Seo;
use App\Support\GameScores;
use App\Support\Url;
use App\Support\WordGame;
use Illuminate\Http\Request;

/** D174, D177: the daily games (শব্দ-খেলা, ধাঁধা, শব্দজব্দ), the scores of signed-in players and the leaderboard. */
class GameController extends Controller
{
    public function __construct()
    {
        request()->attributes->set('hp.compact-footer', true);   // a game page ends with one quiet line, like the account
    }

    public function word()
    {
        return view('game', ['game' => WordGame::day(), 'kind' => 'word', 'seo' => new Seo(
            title: 'শব্দ-খেলা: রোজ পাঁচটা বাংলা শব্দ',
            description: 'হাতপাখার রোজকার শব্দের খেলা। এলোমেলো অক্ষর সাজিয়ে পাঁচটা বাংলা শব্দ বানান, বন্ধুদের সঙ্গে মিলিয়ে দেখুন। রোজ রাত বারোটায় নতুন খেলা।',
            canonical: Url::to('/khela'),
        )]);
    }

    public function riddle()
    {
        return view('game', ['game' => WordGame::riddle(), 'kind' => 'riddle', 'seo' => new Seo(
            title: 'ধাঁধা: রোজ একটা বাংলা ধাঁধা',
            description: 'হাতপাখায় রোজ একটা বাংলা ধাঁধা। ভেবে উত্তর বের করুন, অক্ষর সাজিয়ে মিলিয়ে দেখুন, বন্ধুদের জিজ্ঞেস করুন।',
            canonical: Url::to('/khela/dhadha'),
        )]);
    }

    public function crossword()
    {
        return view('crossword', ['game' => WordGame::crossword(), 'seo' => new Seo(
            title: 'শব্দজব্দ: রোজ একটা ছোট বাংলা শব্দছক',
            description: 'হাতপাখার রোজকার ছোট শব্দজব্দ। সূত্র দেখে ছক ভরুন; কি-বোর্ড লাগে না, অক্ষরে টিপলেই হয়।',
            canonical: Url::to('/khela/shobdojobdo'),
        )]);
    }

    public function board(Request $request)
    {
        $me = $request->user();

        return view('game-board', ['boards' => ['today' => GameScores::board('today', $me), 'week' => GameScores::board('week', $me), 'all' => GameScores::board('all', $me)], 'seo' => new Seo(
            title: 'খেলার লিডারবোর্ড',
            description: 'হাতপাখার শব্দ-খেলা, ধাঁধা আর শব্দজব্দে আজ, এ সপ্তাহে আর সব মিলিয়ে কারা এগিয়ে।',
            canonical: Url::to('/khela/board'),
        )]);
    }

    /** A signed-in player's result of the day. */
    public function score(Request $request)
    {
        $data = $request->validate(['game' => ['required', 'string'], 'day' => ['required', 'date_format:Y-m-d'], 'seconds' => ['required', 'integer', 'min:1'],
            'results' => ['nullable', 'array', 'max:10'], 'hints' => ['nullable', 'integer'], 'solved' => ['nullable', 'boolean']]);
        $points = GameScores::points($data['game'], $data);
        if ($points === null) {
            return response()->json(['ok' => false], 422);
        }
        GameScores::record($request->user(), $data['game'], $data['day'], $points, (int) $data['seconds']);

        return response()->json(['ok' => true, 'points' => $points, 'streak' => GameScores::streak($request->user())['now']]);
    }
}
