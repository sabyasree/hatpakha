<?php

namespace App\Http\Controllers;

use App\Seo\Seo;
use App\Support\GameScores;
use App\Support\GameSocial;
use App\Support\GameTitles;
use App\Support\Proverbs;
use App\Support\Url;
use App\Support\WeeklyQuiz;
use App\Support\WordChoice;
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

    /** D328: বানান-যাচাই (open to all) and এক শব্দে প্রকাশ (with an account): five questions, four choices. */
    public function choice(Request $request, string $game)
    {
        abort_unless(WordChoice::open(), 404);
        \App\Support\Ads::allow();
        $day = WordChoice::day($game);
        abort_if(count($day['items']) < WordChoice::PER_DAY, 404);
        $banan = $game === 'banan';

        return view('game-choice', ['game' => $day, 'kind' => $game, 'locked' => ! $banan && ! $request->user(), 'seo' => new Seo(
            title: $banan ? 'বানান-যাচাই: রোজ পাঁচটা বাংলা বানান, কোনটা ঠিক?' : 'এক শব্দে প্রকাশ: রোজ পাঁচটা বাক্য সংকোচন',
            description: $banan ? 'হাতপাখার রোজকার বানানের খেলা। চারটে বানানের মধ্যে ঠিকটা বেছে নিন; উত্তরের পরে নিয়মটাও জেনে নিন। মুহূর্ত না মুহুর্ত, উজ্জ্বল না উজ্জল?'
                : 'হাতপাখার রোজকার এক কথায় প্রকাশের খেলা। পাঁচটা বাক্যাংশ, প্রতিটির জন্য চারটে শব্দ; ঠিক শব্দটা বেছে নিন। স্কুলের বাক্য সংকোচন, নতুন করে।',
            canonical: Url::to($banan ? '/khela/banan' : '/khela/ek-shobde'),
        )]);
    }

    /** D328: অক্ষরজাল, a 4×4 grid of অক্ষর (with an account). */
    public function jal(Request $request)
    {
        abort_unless(WordChoice::open(), 404);
        \App\Support\Ads::allow();

        return view('game-jal', ['game' => \App\Support\Akshorjal::day(), 'locked' => ! $request->user(), 'seo' => new Seo(
            title: 'অক্ষরজাল: অক্ষরের জালে লুকোনো বাংলা শব্দ খুঁজুন',
            description: 'হাতপাখার রোজকার অক্ষরজাল। ষোলোটা অক্ষর, তিন মিনিট: পাশের অক্ষরে আঙুল টেনে যত পারেন বাংলা শব্দ খুঁজে বের করুন।',
            canonical: Url::to('/khela/akshorjal'),
        )]);
    }

    /** D328: the weekly magazine quiz, open to all (points with an account). */
    public function quiz(Request $request)
    {
        \App\Support\Ads::allow();
        $quiz = WeeklyQuiz::live();
        $posts = $quiz ? \App\Models\Post::published()->whereIn('id', array_column($quiz['questions'], 'post_id'))->get()->keyBy('id') : collect();

        return view('game-quiz', ['quiz' => $quiz, 'posts' => $posts, 'played' => $quiz && $request->user() ? WeeklyQuiz::played($request->user(), now('Asia/Kolkata')->toDateString()) : false,
            'seo' => new Seo(
                title: 'পত্রিকা-কুইজ: এ সপ্তাহের হাতপাখা কতটা পড়েছেন?',
                description: 'প্রতি রবিবার পাঁচটা প্রশ্ন, এ সপ্তাহে হাতপাখায় বেরোনো লেখা থেকে। পড়ে থাকলেই উত্তর জানা; প্রতিটি উত্তরের সঙ্গে লেখাটার লিঙ্ক।',
                canonical: Url::to('/khela/quiz'),
            )]);
    }

    /** D328: সাপ্তাহিক লড়াই. Without a code: play the week's ten and get a link. With one: answer a friend's challenge. */
    public function lorai(Request $request, ?string $code = null)
    {
        $challenge = $code ? GameSocial::find($code, $request) ?? abort(404) : null;
        $week = $challenge['week'] ?? WeeklyQuiz::week();
        \App\Support\Ads::allow();
        $maker = $challenge ? collect($challenge['plays'])->firstWhere('started', true) : null;

        return view('game-lorai', ['challenge' => $challenge, 'maker' => $maker, 'questions' => GameSocial::questions($week), 'week' => $week,
            'name' => $request->user() ? GameSocial::playerName($request->user()) : '',
            'seo' => new Seo(
                title: $maker ? $maker['name'].' আপনাকে লড়াইয়ে ডেকেছেন: হাতপাখা সাপ্তাহিক লড়াই' : 'সাপ্তাহিক লড়াই: বন্ধুদের সঙ্গে দশ প্রশ্নের বাংলা খেলা',
                description: $maker ? 'বানান, এক কথায় প্রকাশ আর প্রবাদের দশটা প্রশ্ন। '.$maker['name'].' পেয়েছেন '.\App\Support\Bn::num((string) $maker['score']).'। আপনি কত পান?'
                    : 'দশটা প্রশ্ন: বানান, এক কথায় প্রকাশ, প্রবাদ। খেলে লিঙ্কটা বন্ধুদের পাঠান; তাঁরাও একই প্রশ্ন খেলবেন, সবার নম্বর এক জায়গায়।',
                canonical: Url::to($challenge ? '/khela/lorai/'.$challenge['code'] : '/khela/lorai'),
                noindex: (bool) $challenge,   // a friend's link: shared, never searched
            )]);
    }

    /** A finished লড়াই: a new link (no code), or a result on a friend's link. */
    public function loraiStore(Request $request, ?string $code = null)
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:60'], 'score' => ['required', 'integer', 'min:0', 'max:'.(GameSocial::QUESTIONS * GameSocial::POINTS)],
            'seconds' => ['required', 'integer', 'min:1', 'max:86400']]);
        $name = $request->user() ? GameSocial::playerName($request->user()) : GameSocial::name($data['name'] ?? null);
        if (! $name) {
            return response()->json(['ok' => false, 'error' => 'name'], 422);
        }
        if ($data['score'] % GameSocial::POINTS) {
            return response()->json(['ok' => false], 422);
        }
        if (! $code) {
            $code = GameSocial::challenge($request, $name, (int) $data['score'], (int) $data['seconds']);
        } else {
            $c = GameSocial::find($code) ?? abort(404);
            if (! $c['open']) {
                return response()->json(['ok' => false, 'error' => 'closed'], 422);
            }
            GameSocial::play($request, $c['id'], $name, (int) $data['score'], (int) $data['seconds']);
        }

        return response()->json(['ok' => true, 'url' => Url::to('/khela/lorai/'.$code), 'plays' => GameSocial::find($code, $request)['plays']]);
    }

    /** D328: পাড়ার দল. The teams this month, the player's own team, and making one. */
    public function teams(Request $request)
    {
        \App\Support\Ads::allow();
        $me = $request->user();

        return view('game-teams', ['teams' => GameSocial::teams(), 'me' => $me, 'mine' => $me ? GameSocial::teamOf($me) : null, 'team' => null, 'members' => [],
            'seo' => new Seo(
                title: 'পাড়ার দল: স্কুল, ক্লাব, পরিবার মিলে খেলুন',
                description: 'হাতপাখার খেলায় দল বেঁধে খেলুন: স্কুল, পাড়ার ক্লাব, প্রবাসের সংগঠন বা পরিবার। দলের সেরা দশজনের মাসের পয়েন্ট মিলে দলের হিসেব।',
                canonical: Url::to('/khela/dol'),
            )]);
    }

    public function team(Request $request, string $code)
    {
        $team = \Illuminate\Support\Facades\DB::table('game_teams')->where('code', strtolower($code))->first() ?? abort(404);
        $me = $request->user();
        $row = collect(GameSocial::teams())->firstWhere('id', $team->id);

        return view('game-teams', ['teams' => [], 'me' => $me, 'mine' => $me ? GameSocial::teamOf($me) : null, 'team' => $team, 'row' => $row,
            'members' => GameSocial::members($team->id, $me),
            'seo' => new Seo(title: $team->name.': হাতপাখার খেলার দল', description: $team->name.' দলে যোগ দিন: হাতপাখার রোজকার বাংলা খেলায় একসঙ্গে পয়েন্ট জমান।',
                canonical: Url::to('/khela/dol/'.$team->code), noindex: true)]);
    }

    public function teamStore(Request $request)
    {
        $request->validate(['name' => ['required', 'string', 'max:80']]);
        $name = GameSocial::name($request->input('name'), 40);
        if (! $name) {
            return back()->withErrors(['name' => 'দলের নাম অন্তত দুই অক্ষরের হোক, কোনো লিঙ্ক ছাড়া।'])->withInput();
        }
        $code = GameSocial::makeTeam($request->user(), $name);
        \App\Models\AuditLog::record('games.team_made', null, ['code' => $code, 'name' => $name]);

        return redirect(Url::to('/khela/dol/'.$code))->with('team_done', 'made');
    }

    public function teamJoin(Request $request, string $code)
    {
        $team = \Illuminate\Support\Facades\DB::table('game_teams')->where('code', strtolower($code))->whereNull('hidden_at')->first() ?? abort(404);
        GameSocial::join($request->user(), $team->id);

        return redirect(Url::to('/khela/dol/'.$team->code))->with('team_done', 'joined');
    }

    public function teamLeave(Request $request)
    {
        GameSocial::leave($request->user());

        return redirect(Url::to('/khela/dol'))->with('team_done', 'left');
    }

    /** D243: today's games in one place: what is done, what is left, today's points, and who is ahead. */
    public function hub(Request $request)
    {
        \App\Support\Ads::allow();
        $me = $request->user();

        return view('game-hub', ['me' => $me, 'today' => $me ? GameScores::today($me) : null, 'streak' => $me ? GameScores::streak($me) : null,
            'level' => $me ? GameScores::level(GameScores::lifetime($me)) : null, 'month' => GameScores::board('month', $me, 1),
            'seo' => new Seo(
                title: 'আজকের খেলা: শব্দ-খেলা, ধাঁধা, শব্দজব্দ, বানান, অক্ষরজাল আরও',
                description: 'হাতপাখার রোজকার বাংলা খেলা এক জায়গায়: শব্দ-খেলা, ধাঁধা, শব্দজব্দ, প্রবাদ, বানান, এক শব্দে প্রকাশ, অক্ষরজাল আর সাপ্তাহিক কুইজ। রোজ ৩০০ পয়েন্ট পর্যন্ত।',
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
            'results' => ['nullable', 'array', 'max:10'], 'hints' => ['nullable', 'integer'], 'solved' => ['nullable', 'boolean'],
            'words' => ['nullable', 'array', 'max:300'], 'words.*' => ['string', 'max:40']]);
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
        $urls = ['word' => Url::to('/khela'), 'riddle' => Url::to('/khela/dhadha'), 'cross' => WordGame::crosswordUrl('medium'), 'probad' => Url::to('/khela/probad'),
            'banan' => Url::to('/khela/banan'), 'ekshobde' => Url::to('/khela/ek-shobde'), 'jal' => Url::to('/khela/akshorjal'), 'quiz' => Url::to('/khela/quiz')];
        $next = $data['game'] === 'riddle' && ! isset($today['games']['riddle2']) ? ['name' => 'কঠিন ধাঁধা', 'url' => Url::to('/khela/dhadha/kothin')] : null;
        foreach ($today['groups'] as $g) {
            if (! $g['done'] && ! $next && ($g['key'] !== 'quiz' || WeeklyQuiz::live())) {
                $next = ['name' => $g['name'], 'url' => $urls[$g['key']]];
            }
        }
        if (! $next && isset($today['games']['riddle']) && ! isset($today['games']['riddle2'])) {
            $next = ['name' => 'কঠিন ধাঁধা', 'url' => Url::to('/khela/dhadha/kothin')];
        }
        $level = GameScores::level(GameScores::lifetime($user));

        $quizOpen = (bool) WeeklyQuiz::live();
        $groups = array_values(array_filter($today['groups'], fn ($g) => $g['key'] !== 'quiz' || $g['done'] || $quizOpen));

        return response()->json(['ok' => true, 'points' => $points, 'streak' => $streak['now'], 'today' => ['total' => $today['total'], 'max' => GameScores::DAY_MAX, 'groups' => $groups],
            'rank' => ['now' => $mine['rank'] ?? 0, 'up' => $new && $before && ($mine['rank'] ?? 0) ? max(0, $before - $mine['rank']) : 0, 'gap' => $mine['gap'] ?? null],
            'next' => $next, 'level' => ['name' => $level['name'], 'nextName' => $level['nextName'], 'left' => $level['left'], 'percent' => $level['percent']]]);
    }

    /** D179: a player says a word, clue or riddle is wrong. No account needed. */
    public function report(Request $request)
    {
        $data = $request->validate(['game' => ['required', 'in:word,riddle,riddle2,cross,cross1,cross3,probad,banan,ekshobde'], 'word' => ['required', 'string', 'max:60'],
            'reason' => ['required', 'in:'.implode(',', array_keys(\App\Support\WordReports::REASONS))], 'note' => ['nullable', 'string', 'max:300']]);
        $ok = \App\Support\WordReports::store($request, in_array($data['game'], ['riddle', 'riddle2'], true) ? 'riddle' : (isset(WordChoice::GAMES[$data['game']]) ? $data['game'] : 'word'), $data['word'], $data['reason'], $data['note'] ?? null);

        return response()->json(['ok' => $ok], $ok ? 200 : 422);
    }
}
