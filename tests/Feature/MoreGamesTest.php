<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Enums\Role;
use App\Models\User;
use App\Support\Akshorjal;
use App\Support\GameScores;
use App\Support\GameSocial;
use App\Support\WeeklyQuiz;
use App\Support\WordChoice;
use App\Support\WordGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D328: বানান-যাচাই, এক শব্দে প্রকাশ, অক্ষরজাল, পত্রিকা-কুইজ, সাপ্তাহিক লড়াই, পাড়ার দল. */
class MoreGamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-12-02 12:00', 'Asia/Kolkata'));   // a Wednesday
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function player(string $name = 'খেলোয়াড়'): User
    {
        return User::factory()->create(['name' => $name]);
    }

    public function test_the_word_lists_are_well_formed(): void
    {
        $spelling = WordGame::file('spelling');
        $this->assertGreaterThanOrEqual(200, count($spelling));
        $seen = [];
        foreach ($spelling as $row) {
            [$right, $wrong, $note] = $row;
            $this->assertCount(3, $wrong, $right);
            $this->assertCount(4, array_unique(array_merge([$right], $wrong)), 'Four different spellings: '.$right);
            $this->assertArrayNotHasKey($right, $seen, 'Twice: '.$right);
            $this->assertNotSame('', trim($note), 'A rule or a hint: '.$right);
            foreach (array_merge([$right], $wrong) as $w) {
                $this->assertMatchesRegularExpression('/^[\x{0980}-\x{09FF}\x{200C}\x{200D}]+$/u', $w, 'Bengali letters only: '.$w);
            }
            $seen[$right] = true;
        }
        $one = WordGame::file('one-word');
        $this->assertGreaterThanOrEqual(200, count($one));
        $answers = array_column($one, 1);
        $this->assertSame(count($answers), count(array_unique($answers)), 'Every answer once, since the others are the wrong choices');
        foreach ($one as [$phrase, $answer]) {
            $this->assertNotContains($answer, preg_split('/[\s,;:।]+/u', $phrase), 'The phrase gives the answer away: '.$answer);
        }
        $extra = WordGame::file('akshorjal-words');
        $this->assertGreaterThanOrEqual(2000, count($extra));
        foreach ($extra as $w) {
            $this->assertMatchesRegularExpression('/^[\x{0980}-\x{09FF}]+$/u', $w);
        }
    }

    public function test_choice_games_are_the_same_for_everyone_with_the_answer_among_four(): void
    {
        foreach (array_keys(WordChoice::GAMES) as $game) {
            $day = WordChoice::day($game);
            $this->assertSame($day, WordChoice::day($game, now('Asia/Kolkata')->addHours(5)), 'Same all day');
            $this->assertCount(WordChoice::PER_DAY, $day['items']);
            foreach ($day['items'] as $q) {
                $this->assertCount(4, array_unique($q['options']));
                $this->assertContains($q['answer'], $q['options']);
            }
            $this->assertNotSame(array_column($day['items'], 'answer'), array_column(WordChoice::day($game, now('Asia/Kolkata')->addDay())['items'], 'answer'));
        }
        // a hundred days in a row: no question twice
        $answers = [];
        for ($d = 0; $d < 60; $d++) {
            $answers = array_merge($answers, array_column(WordChoice::day('banan', now('Asia/Kolkata')->addDays($d))['items'], 'answer'));
        }
        $this->assertSame(count($answers), count(array_unique($answers)));
    }

    public function test_akshorjal_grids_hold_real_words_and_only_those_score(): void
    {
        foreach (['2026-12-02', '2026-12-03', '2027-03-15'] as $date) {
            $day = Akshorjal::day(Carbon::parse($date, 'Asia/Kolkata'));
            $this->assertCount(16, $day['grid']);
            $this->assertGreaterThanOrEqual(Akshorjal::MIN_WORDS, count($day['words']), $date);
            $this->assertEqualsCanonicalizing($day['words'], Akshorjal::solve($day['grid']), 'Every listed word can be traced, and no other');
        }
        $day = Akshorjal::day();
        $dict = Akshorjal::dictionary();
        $w = $day['words'][0];
        $this->assertSame(Akshorjal::points(count($dict[$w])), Akshorjal::score([$w, $w, 'নেইশব্দ'], $day['date']), 'Each word once; a word not in the grid gives nothing');
        $this->assertLessThanOrEqual(100, Akshorjal::score($day['words'], $day['date']));
        $this->assertSame(Akshorjal::score($day['words'], $day['date']), GameScores::points('jal', ['words' => $day['words']], $day['date']));
    }

    public function test_points_stay_a_best_three_day_of_300(): void
    {
        $this->assertSame(100, GameScores::points('banan', ['results' => ['g', 'g', 'g', 'g', 'g']]));
        $this->assertSame(60, GameScores::points('ekshobde', ['results' => ['g', 'r', 'g', 'r', 'g']]));
        $this->assertNull(GameScores::points('banan', ['results' => ['g', 'g']]), 'Five answers or nothing');
        $this->assertNull(GameScores::points('banan', ['results' => ['g', 'y', 'g', 'g', 'g']]), 'One try only');
        // eight games, all full: still 300, and the old games keep their numbers
        $all = ['word' => 100, 'riddle' => 40, 'riddle2' => 60, 'cross' => 30, 'cross1' => 20, 'cross3' => 50, 'probad' => 100, 'banan' => 100, 'ekshobde' => 100, 'jal' => 100, 'quiz' => 100];
        $this->assertSame(300, GameScores::dayTotal($all));
        $this->assertSame(220, GameScores::dayTotal(['word' => 100, 'banan' => 80, 'jal' => 40, 'ekshobde' => 20]), 'The best three');
        $this->assertSame(GameScores::DAY_MAX, 300);
    }

    public function test_pages_open_and_the_harder_ones_ask_for_an_account(): void
    {
        $this->get('/khela/banan/')->assertOk()->assertSee('বানান-যাচাই')->assertSee('data-choice', false)->assertDontSee('এটা খেলতে অ্যাকাউন্ট লাগে');
        $page = $this->get('/khela/banan/')->getContent();
        foreach (WordChoice::day('banan')['items'] as $q) {
            $this->assertStringNotContainsString('"answer":"'.$q['answer'].'"', $page, 'No answer in plain sight');
        }
        $this->get('/khela/ek-shobde/')->assertOk()->assertSee('এটা খেলতে অ্যাকাউন্ট লাগে');
        $this->get('/khela/akshorjal/')->assertOk()->assertSee('এটা খেলতে অ্যাকাউন্ট লাগে');
        $this->actingAs($this->player());
        $this->get('/khela/ek-shobde/')->assertOk()->assertSee('data-choice', false);
        $this->get('/khela/akshorjal/')->assertOk()->assertSee('data-jal', false);
        $this->get('/khela/aaj/')->assertOk()->assertSee('অক্ষরজাল')->assertSee('পত্রিকা-কুইজ')->assertSee('সাপ্তাহিক লড়াই');
        // the menu of every game page lists the new games
        $this->get('/khela/')->assertOk()->assertSee('বানান-যাচাই')->assertSee('লড়াই ও দল');
    }

    public function test_a_finished_game_reaches_the_board(): void
    {
        $user = $this->player();
        $this->actingAs($user)->postJson('/khela/score', ['game' => 'banan', 'day' => '2026-12-02', 'seconds' => 40, 'results' => ['g', 'g', 'r', 'g', 'g']])
            ->assertOk()->assertJsonPath('points', 80)->assertJsonPath('today.total', 80);
        $words = Akshorjal::day()['words'];
        $this->postJson('/khela/score', ['game' => 'jal', 'day' => '2026-12-02', 'seconds' => 170, 'words' => array_slice($words, 0, 3)])->assertOk();
        $this->assertSame(2, DB::table('game_scores')->where('user_id', $user->id)->count());
    }

    public function test_the_weekly_quiz_waits_for_approval_and_counts_once_a_week(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $posts = collect(range(1, 4))->map(fn ($i) => $this->makePost(['title' => 'লেখা '.$i, 'published_at' => Carbon::parse('2026-12-0'.(1 + $i).' 10:00', 'Asia/Kolkata')->utc()]));
        Claude::$fake = fn ($purpose, $user) => ['questions' => $posts->map(fn ($p) => ['post_id' => $p->id, 'q' => $p->title.'-য় কোন ফুলের কথা?', 'options' => ['জবা', 'বেলি', 'টগর', 'শিউলি'], 'answer' => 2, 'why' => 'দ্বিতীয় অনুচ্ছেদে'])->all()];
        Carbon::setTestNow(Carbon::parse('2026-12-05 10:15', 'Asia/Kolkata'));   // Saturday
        $this->assertNull(WeeklyQuiz::draft());
        $row = DB::table('game_quizzes')->first();
        $this->assertSame('2026-12-06', substr((string) $row->week, 0, 10));
        $this->assertSame('draft', $row->status);

        Carbon::setTestNow(Carbon::parse('2026-12-07 09:00', 'Asia/Kolkata'));   // Monday of that week
        $this->get('/khela/quiz/')->assertOk()->assertSee('এ সপ্তাহের কুইজ আসছে')->assertDontSee('কোন ফুলের কথা');
        DB::table('game_quizzes')->update(['status' => 'approved']);
        WeeklyQuiz::forget('2026-12-06');
        $this->get('/khela/quiz/')->assertOk()->assertSee('data-choice', false)->assertSee('লেখা 1', false);

        $user = $this->player();
        $this->actingAs($user)->postJson('/khela/score', ['game' => 'quiz', 'day' => '2026-12-07', 'seconds' => 60, 'results' => ['g', 'g', 'g', 'r']])->assertOk()->assertJsonPath('points', 60);
        Carbon::setTestNow(Carbon::parse('2026-12-08 09:00', 'Asia/Kolkata'));
        $this->postJson('/khela/score', ['game' => 'quiz', 'day' => '2026-12-08', 'seconds' => 60, 'results' => ['g', 'g', 'g', 'g']])->assertOk();
        $this->assertSame(1, DB::table('game_scores')->where('game', 'quiz')->count(), 'Once a week');
    }

    public function test_a_quiz_question_never_comes_from_the_desk_byline_and_bad_ones_are_dropped(): void
    {
        $this->assertFalse(WeeklyQuiz::valid(['q' => 'ক?', 'options' => ['এক', 'এক', 'দুই', 'তিন'], 'answer' => 0]), 'Four different answers');
        $this->assertFalse(WeeklyQuiz::valid(['q' => 'ক?', 'options' => ['এক', 'দুই', 'তিন'], 'answer' => 0]));
        $this->assertTrue(WeeklyQuiz::valid(['q' => 'ক?', 'options' => ['এক', 'দুই', 'তিন', 'চার'], 'answer' => 3]));
        $this->assertSame('2026-11-29', WeeklyQuiz::week('2026-12-05'));
        $this->assertSame('2026-12-06', WeeklyQuiz::week('2026-12-06'));
    }

    public function test_a_challenge_link_collects_everyones_scores_without_an_account(): void
    {
        $this->assertCount(GameSocial::QUESTIONS, GameSocial::questions(WeeklyQuiz::week()));
        $this->get('/khela/lorai/')->assertOk()->assertSee('সাপ্তাহিক লড়াই');
        $this->postJson('/khela/lorai', ['name' => 'http://spam.com', 'score' => 70, 'seconds' => 80])->assertStatus(422);
        $r = $this->postJson('/khela/lorai', ['name' => 'মিতা', 'score' => 70, 'seconds' => 80])->assertOk()->json();
        $code = basename(rtrim($r['url'], '/'));
        $this->get('/khela/lorai/'.$code.'/')->assertOk()->assertSee('মিতা আপনাকে লড়াইয়ে ডেকেছেন')->assertSee('noindex', false);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->postJson('/khela/lorai/'.$code, ['name' => 'রাজু', 'score' => 90, 'seconds' => 70])
            ->assertOk()->assertJsonPath('plays.0.name', 'রাজু')->assertJsonPath('plays.1.name', 'মিতা');
        $this->postJson('/khela/lorai/'.$code, ['name' => 'রাজু', 'score' => 75, 'seconds' => 70])->assertStatus(422);
        // next week the link only shows the results
        Carbon::setTestNow(Carbon::parse('2026-12-09 12:00', 'Asia/Kolkata'));
        $this->get('/khela/lorai/'.$code.'/')->assertOk()->assertSee('এই লড়াইয়ের সপ্তাহ শেষ হয়ে গেছে');
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])->postJson('/khela/lorai/'.$code, ['name' => 'নতুন', 'score' => 50, 'seconds' => 70])->assertStatus(422);
    }

    public function test_teams_count_their_ten_best_and_the_desk_can_hide_one(): void
    {
        $a = $this->player('অমল');
        $b = $this->player('বিমল');
        $this->get('/khela/dol/')->assertOk()->assertSee('দল তৈরি করতে বা দলে যোগ দিতে অ্যাকাউন্ট লাগে');
        $this->actingAs($a)->post('/khela/dol', ['name' => 'বেহালা পাঠচক্র'])->assertRedirect();
        $team = DB::table('game_teams')->first();
        $this->assertSame($team->id, (int) DB::table('users')->where('id', $a->id)->value('game_team_id'));
        $this->actingAs($b)->post('/khela/dol/'.$team->code.'/join')->assertRedirect();
        foreach ([[$a, 'banan', 100], [$b, 'banan', 60]] as [$u, $g, $p]) {
            DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => $g, 'day' => '2026-12-02', 'points' => $p, 'seconds' => 10, 'created_at' => now()]);
        }
        GameScores::forget();
        \Illuminate\Support\Facades\Cache::flush();
        $teams = GameSocial::teams();
        $this->assertSame(160, $teams[0]['points']);
        $this->get('/khela/dol/')->assertOk()->assertSee('বেহালা পাঠচক্র');
        $this->get('/khela/dol/'.$team->code.'/')->assertOk()->assertSee('অমল')->assertSee('বিমল');

        $editor = User::factory()->create(['role' => Role::Editor->value]);
        \Livewire\Livewire::actingAs($editor)->test(\App\Filament\Pages\GameDesk::class)->call('hideTeam', $team->id)->assertOk();
        $this->assertSame([], GameSocial::teams());
        $this->actingAs($b)->post('/khela/dol/leave')->assertRedirect();
        $this->assertNull(DB::table('users')->where('id', $b->id)->value('game_team_id'));
    }

    public function test_the_desk_approves_a_hand_written_quiz(): void
    {
        $editor = User::factory()->create(['role' => Role::Editor->value]);
        $next = Carbon::parse(WeeklyQuiz::week())->addDays(7)->toDateString();
        $page = \Livewire\Livewire::actingAs($editor)->test(\App\Filament\Pages\GameDesk::class)->assertOk();
        $page->set('edit.'.$next, [['q' => 'প্রথম প্রশ্ন?', 'options' => ['এক', 'দুই', 'তিন', 'চার'], 'answer' => 1, 'post_id' => null, 'why' => '']])
            ->call('save', $next, true);
        $this->assertSame(0, DB::table('game_quizzes')->where('status', 'approved')->count(), 'At least three questions');
        $q = fn ($t) => ['q' => $t, 'options' => ['এক', 'দুই', 'তিন', 'চার'], 'answer' => 1, 'post_id' => null, 'why' => ''];
        $page->set('edit.'.$next, [$q('ক?'), $q('খ?'), $q('গ?')])->call('save', $next, true);
        $this->assertSame(1, DB::table('game_quizzes')->where('status', 'approved')->count());
        $this->assertSame(3, WeeklyQuiz::count($next));
    }
}
