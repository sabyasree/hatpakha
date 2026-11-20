<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GameScores;
use App\Support\GameTitles;
use App\Support\Proverbs;
use App\Support\WordGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D243 (owner: "make all the proper badges: মাসের সেরা, বছরের সেরা, সেরা for multiple years… plan properly for the
 * points… add more words so it will not repeat for 1 year… ধাঁধা easy and hard… no points without account").
 */
class GameTitlesTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $when): void
    {
        Carbon::setTestNow(Carbon::parse($when, 'Asia/Kolkata'));
        Cache::flush();
    }

    /** One day's games for a player, straight into the table. */
    private function played(User $u, string $day, array $games, int $seconds = 60): void
    {
        foreach ($games as $game => $points) {
            DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => $game, 'day' => $day, 'points' => $points, 'seconds' => $seconds, 'created_at' => $day.' 10:00:00']);
        }
    }

    public function test_each_game_is_worth_a_hundred_and_the_day_counts_the_best_three(): void
    {
        $new = '2026-12-05';
        $this->assertSame(40, GameScores::points('riddle', ['results' => ['g']], $new));
        $this->assertSame(20, GameScores::points('riddle', ['results' => ['y']], $new));
        $this->assertSame(60, GameScores::points('riddle2', ['results' => ['g']], $new));
        $this->assertSame(0, GameScores::points('riddle2', ['results' => ['r']], $new));
        $this->assertSame([20, 30, 50], [GameScores::points('cross1', ['hints' => 0, 'solved' => true], $new), GameScores::points('cross', ['hints' => 0, 'solved' => true], $new), GameScores::points('cross3', ['hints' => 0, 'solved' => true], $new)]);
        $this->assertSame(40, GameScores::points('cross3', ['hints' => 2, 'solved' => true], $new), 'Five off for each square shown');
        $this->assertSame(10, GameScores::points('cross3', ['hints' => 30, 'solved' => true], $new), 'A solved puzzle always gives a little');
        $this->assertSame(60, GameScores::points('probad', ['results' => ['g', 'g', 'y', 'r']], $new));
        $this->assertNull(GameScores::points('probad', ['results' => ['g', 'g']], $new), 'All four proverbs or nothing');
        $this->assertSame(100, GameScores::points('riddle', ['results' => ['g']], '2026-11-19'), 'What was played before keeps its points');
        $this->assertSame(150, GameScores::points('cross3', ['hints' => 0, 'solved' => true], '2026-11-19'));
        $this->assertSame([40, 50], [GameScores::points('riddle', ['results' => ['g']], '2026-11-20'), GameScores::points('cross3', ['hints' => 0, 'solved' => true], '2026-11-20')], 'The new points come with the new games');

        $this->assertSame(300, GameScores::dayTotal(['word' => 100, 'riddle' => 40, 'riddle2' => 60, 'cross1' => 20, 'cross' => 30, 'cross3' => 50, 'probad' => 100]), 'Four full games still make 300');
        $this->assertSame(210, GameScores::dayTotal(['word' => 80, 'riddle' => 40, 'probad' => 60, 'cross1' => 20, 'cross3' => 50]), 'The best three: 80 + 70 + 60 (the ধাঁধা\'s 40 is the fourth)');
        $this->assertSame(100, GameScores::dayTotal(['cross1' => 60, 'cross' => 100, 'cross3' => 150]), 'One game never gives more than 100, old days included');
    }

    public function test_a_month_counts_the_best_twenty_days_and_writes_its_first_three_down_once(): void
    {
        $this->at('2026-12-31 20:00');
        [$a, $b, $c, $d, $hidden] = [User::factory()->create(['name' => 'Asha']), User::factory()->create(['name' => 'Bina']), User::factory()->create(['name' => 'Chhaya']), User::factory()->create(['name' => 'Dipa']), User::factory()->create(['name' => 'Hidden', 'board_hidden' => true])];
        for ($i = 1; $i <= 25; $i++) {
            $day = sprintf('2026-12-%02d', $i);
            $this->played($a, $day, ['word' => 100, 'riddle' => 40, 'riddle2' => 60, 'probad' => 100]);   // 300 on 25 days: the best 20 count
            $this->played($hidden, $day, ['word' => 100, 'riddle' => 40, 'riddle2' => 60, 'probad' => 100]);
            if ($i <= 12) {
                $this->played($b, $day, ['word' => 100, 'probad' => 100]);   // 200 on 12 days
            }
            if ($i <= 9) {
                $this->played($c, $day, ['word' => 100, 'riddle' => 40, 'riddle2' => 60, 'probad' => 100]);   // 300, but only 9 days
            }
            if ($i <= 10) {
                $this->played($d, $day, ['word' => 60]);
            }
        }
        $board = GameScores::board('month', $b);
        $this->assertSame(['Asha', 'Chhaya', 'Bina', 'Dipa'], array_column($board['rows'], 'name'));
        $this->assertSame([6000, 2700, 2400, 600], array_column($board['rows'], 'points'));
        $this->assertSame(3, $board['mine']['rank']);
        $this->assertSame(301, $board['mine']['gap'], 'Only the next step up is told');

        $this->at('2027-01-01 00:20');
        $this->assertSame(3, GameTitles::award());
        $this->assertSame(0, GameTitles::award(), 'Once');
        $titles = DB::table('game_titles')->where('kind', 'month')->orderBy('place')->get();
        $this->assertSame([$a->id, $b->id, $d->id], $titles->pluck('user_id')->all(), 'Nine days are not enough; a hidden name is not on the roll');
        $this->assertSame(['2026-12'], $titles->pluck('period')->unique()->values()->all());
        $this->assertSame(['months' => ['2026-12'], 'place' => 1], ['months' => GameTitles::of($a)['months'], 'place' => 1]);
        $this->assertSame(['m'], GameTitles::marks()[$a->id]);
        $this->assertArrayNotHasKey($b->id, GameTitles::marks(), 'The mark beside a name is for first place');

        $this->get('/khela/board/')->assertOk()->assertSee('ডিসেম্বর ২০২৬-এর সেরা তিন')->assertSee('Asha')->assertSee('সম্মান-ফলক')->assertSee('মাসের সেরা');
        $this->actingAs($a)->get('/account/profile/')->assertOk()->assertSee('খেতাব')->assertSee('মাসের সেরা');
        $this->actingAs($c)->get('/account/profile/')->assertOk()->assertSee('মাসের সেরা তিন জন খেতাব পান');
    }

    /** D245 (owner: winners should be told; and "a desk button to remove a suspicious score"). */
    public function test_winners_are_told_and_the_desk_can_keep_a_player_off_the_boards(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->at('2026-12-31 20:00');
        [$cheat, $a, $b, $quiet] = [User::factory()->create(['name' => 'Too Fast']), User::factory()->create(['name' => 'Asha']), User::factory()->create(['name' => 'Bina']), User::factory()->create(['name' => 'Quiet', 'mail_opt_out' => true])];
        for ($i = 1; $i <= 12; $i++) {
            $day = sprintf('2026-12-%02d', $i);
            $this->played($cheat, $day, ['word' => 100, 'riddle' => 40, 'riddle2' => 60, 'probad' => 100], 3);
            $this->played($a, $day, ['word' => 100, 'probad' => 100]);
            $this->played($b, $day, ['word' => 80]);
            $this->played($quiet, $day, ['word' => 40]);
        }
        $this->assertSame('Too Fast', GameScores::board('month')['rows'][0]['name']);

        $admin = User::factory()->create(['role' => \App\Enums\Role::Admin]);
        $this->actingAs($admin)->get('/khela/board/')->assertOk()->assertSee('/desk/users/'.$cheat->id.'/edit', false);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)->callTableAction('gameBlock', $cheat)->assertHasNoTableActionErrors();
        $this->assertNotNull($cheat->fresh()->game_blocked_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.player_blocked', 'subject_id' => $cheat->id]);
        $this->assertSame(['Asha', 'Bina', 'Quiet'], array_column(GameScores::board('month')['rows'], 'name'), 'Off the board at once');
        $this->assertSame(36, DB::table('game_scores')->where('user_id', $cheat->id)->count() - 12, 'Nothing is deleted');
        auth()->logout();
        $this->actingAs($a)->get('/khela/board/')->assertOk()->assertDontSee('Too Fast')->assertDontSee('/desk/users/', false);

        $this->at('2027-01-01 00:20');
        $this->assertSame(3, GameTitles::award());
        $this->assertSame([$a->id, $b->id, $quiet->id], DB::table('game_titles')->orderBy('place')->pluck('user_id')->all(), 'No title for a player kept off');
        \Illuminate\Support\Facades\Notification::assertSentTo($a, \App\Notifications\TemplateMail::class, function ($n) use ($a) {
            $mail = $n->toMail($a);

            return $n->key === 'game_title' && str_contains($mail->subject, 'মাসের সেরা') && str_contains(implode(' ', $mail->introLines), 'ডিসেম্বর ২০২৬') && str_contains((string) $mail->actionUrl, '/khela/board/#hall');
        });
        \Illuminate\Support\Facades\Notification::assertSentTo($b, \App\Notifications\TemplateMail::class, fn ($n) => $n->values['{খেতাব}'] === 'মাসের সেরা তিনে' && $n->values['{স্থান}'] === 'দ্বিতীয়');
        \Illuminate\Support\Facades\Notification::assertNotSentTo($quiet, \App\Notifications\TemplateMail::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($cheat, \App\Notifications\TemplateMail::class);
        GameTitles::award();
        \Illuminate\Support\Facades\Notification::assertSentToTimes($a, \App\Notifications\TemplateMail::class, 1);

        // the same button sits on the player's own desk page (where the leaderboard's link leads)
        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $cheat->id])->assertActionVisible('gameBlock')->assertActionHasLabel('gameBlock', 'খেলার তালিকায় ফেরান');

        // put back; and titles can be taken away with the same button
        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)->callTableAction('gameBlock', $cheat);
        $this->assertNull($cheat->fresh()->game_blocked_at);
        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)->callTableAction('gameBlock', $a, ['titles' => true]);
        $this->assertSame(0, DB::table('game_titles')->where('user_id', $a->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'game.player_blocked', 'subject_id' => $a->id]);
    }

    public function test_november_is_not_judged_and_a_year_needs_six_regular_months(): void
    {
        $this->at('2026-12-01 00:20');
        $u = User::factory()->create();
        for ($i = 14; $i <= 30; $i++) {
            $this->played($u, sprintf('2026-11-%02d', $i), ['word' => 100]);
        }
        $this->assertSame(0, GameTitles::award(), 'The first month judged is December 2026');

        // 2027: Asha plays 10 days in each of 7 months, Bina in 5
        [$a, $b] = [User::factory()->create(['name' => 'Asha']), User::factory()->create(['name' => 'Bina'])];
        foreach (range(1, 7) as $m) {
            foreach (range(1, 10) as $dd) {
                $this->played($a, sprintf('2027-%02d-%02d', $m, $dd), ['word' => 100, 'probad' => 100]);
                if ($m <= 5) {
                    $this->played($b, sprintf('2027-%02d-%02d', $m, $dd), ['word' => 100, 'probad' => 100, 'riddle' => 40, 'riddle2' => 60]);
                }
            }
        }
        $this->at('2028-01-01 00:20');
        GameTitles::award();
        $year = DB::table('game_titles')->where('kind', 'year')->get();
        $this->assertSame([$a->id], $year->pluck('user_id')->all(), 'Five months are not enough for the year');
        $this->assertSame(14000, (int) $year[0]->points);
        $this->assertSame('২০২৭', GameTitles::label('year', '2027'));
        $this->assertNull(GameTitles::of($a)['multi']);

        DB::table('game_titles')->insert(['user_id' => $a->id, 'kind' => 'year', 'period' => '2028', 'place' => 1, 'points' => 9, 'days' => 6]);
        $this->assertSame('শব্দ-সাধক', GameTitles::of($a)['multi'], 'The owner\'s name for two years');
        DB::table('game_titles')->insert(['user_id' => $a->id, 'kind' => 'year', 'period' => '2029', 'place' => 1, 'points' => 9, 'days' => 6]);
        $this->assertSame('হাতপাখা-রত্ন', GameTitles::of($a)['multi'], 'Three or more');
        Cache::flush();
        $this->assertSame(['yy', 'm'], array_slice(array_merge(GameTitles::marks()[$a->id], ['m']), 0, 2));
        $this->assertStringContainsString('<svg', GameTitles::svg('multi', 1, 92, 'সেরা', 3));
    }

    public function test_levels_come_from_all_points_and_a_single_rest_day_keeps_the_run(): void
    {
        $this->assertSame(['পাঠক', 'শব্দপ্রেমী', 1], [GameScores::level(999)['name'], GameScores::level(1000)['name'], GameScores::level(999)['left']]);
        $this->assertSame('শব্দগুরু', GameScores::level(250000)['name']);
        $this->assertNull(GameScores::level(250000)['nextName']);

        $this->at('2026-12-12 09:00');
        $u = User::factory()->create();
        foreach (['2026-12-01', '2026-12-02', '2026-12-04', '2026-12-05', '2026-12-06', '2026-12-07', '2026-12-08', '2026-12-09', '2026-12-11'] as $day) {
            $this->played($u, $day, ['word' => 100, 'riddle' => 40, 'riddle2' => 60, 'probad' => 100]);
        }
        $s = GameScores::streak($u);
        $this->assertSame(9, $s['now'], 'The 3rd and the 10th were rest days, a week apart: the run goes on');
        $this->assertSame(['2026-12-03', '2026-12-10'], $s['rest']);
        $this->assertSame(9, GameScores::feats($u)['perfect']);

        $v = User::factory()->create();
        foreach (['2026-12-05', '2026-12-07', '2026-12-09', '2026-12-11'] as $day) {
            $this->played($v, $day, ['word' => 50]);
        }
        $this->assertSame(2, GameScores::streak($v)['now'], 'A second rest day in the same week starts the run again');
    }

    public function test_guests_try_the_easy_games_and_points_need_an_account(): void
    {
        $this->at('2026-12-05 10:00');
        $this->get('/khela/')->assertOk()->assertSee('js/hp-khela.js', false);
        $this->get('/khela/dhadha/')->assertOk()->assertSee('js/hp-khela.js', false)->assertSee('সহজ ধাঁধা')->assertSee('কঠিন ধাঁধা')->assertSee('৪০ পয়েন্ট')->assertSee('অ্যাকাউন্ট ছাড়া পয়েন্ট ওঠে না');
        $hard = WordGame::riddle(null, 'hard')['words'][0];
        $this->get('/khela/dhadha/kothin/')->assertOk()->assertDontSee('js/hp-khela.js', false)->assertDontSee($hard['clue'])->assertSee('অ্যাকাউন্ট খুলুন');
        $this->get('/khela/probad/')->assertOk()->assertDontSee('js/hp-probad.js', false)->assertSee('অ্যাকাউন্ট খুলুন');
        $this->get('/khela/aaj/')->assertOk()->assertSee('আজকের খেলা')->assertSee('অ্যাকাউন্ট লাগে');
        $this->postJson('/khela/score', ['game' => 'riddle', 'day' => '2026-12-05', 'seconds' => 9, 'results' => ['g']])->assertUnauthorized();

        $u = User::factory()->create();
        // the hard riddle's first day shows no "yesterday's answer": it would be today's
        $this->at(WordGame::EXTRA_FROM.' 10:00');
        $this->actingAs($u)->get('/khela/dhadha/kothin/')->assertOk()->assertDontSee('কালকের ধাঁধার উত্তর');
        $this->at('2026-12-05 10:00');
        $this->get('/khela/dhadha/kothin/')->assertOk()->assertSee('কালকের ধাঁধার উত্তর');
        $page = $this->get('/khela/dhadha/kothin/')->assertOk()->assertSee($hard['clue'])->assertSee('"kind":"riddle2"', false)->getContent();
        $this->assertStringNotContainsString('>'.$hard['answer'].'<', $page);
        $this->assertNotSame($hard['answer'], WordGame::riddle()['words'][0]['answer'], 'Two different riddles a day');
        $this->assertGreaterThan(count(WordGame::riddle()['words'][0]['tiles']) - count(WordGame::letters(WordGame::riddle()['words'][0]['answer'])), count($hard['tiles']) - count(WordGame::letters($hard['answer'])), 'More spare letters');

        $game = Proverbs::day();
        $page = $this->get('/khela/probad/')->assertOk()->assertSee('js/hp-probad.js', false)->assertSee($game['items'][0]['meaning'])->getContent();
        foreach ($game['items'] as $q) {
            $this->assertCount(4, array_unique($q['options']));
            $this->assertContains($q['answer'], $q['options']);
            $this->assertStringNotContainsString($q['full'], $page, 'The whole proverb is not printed');
        }

        $this->postJson('/khela/score', ['game' => 'riddle2', 'day' => '2026-12-05', 'seconds' => 30, 'results' => ['g']])->assertOk()
            ->assertJson(['ok' => true, 'points' => 60, 'today' => ['total' => 60, 'max' => 300], 'rank' => ['now' => 1], 'next' => ['name' => 'শব্দ-খেলা']]);
        $this->postJson('/khela/score', ['game' => 'riddle', 'day' => '2026-12-04', 'seconds' => 30, 'results' => ['g']])->assertJson(['points' => 40, 'next' => ['name' => 'কঠিন ধাঁধা']]);   // yesterday's, just after midnight: the hard one is offered next
        $this->postJson('/khela/score', ['game' => 'probad', 'day' => '2026-12-05', 'seconds' => 30, 'results' => ['g', 'g', 'g', 'y']])->assertJson(['points' => 85, 'today' => ['total' => 145]]);
        $this->get('/khela/aaj/')->assertOk()->assertSee('হয়ে গেছে · ৮৫ পয়েন্ট')->assertSee('আরও বাকি');
        $this->get('/khela/dhadha/')->assertSee('✓ হয়ে গেছে');
    }

    public function test_nothing_repeats_for_a_year_from_the_first_of_december(): void
    {
        $from = Carbon::parse(WordGame::NEW_FROM, 'Asia/Kolkata');
        $before = [];
        for ($d = Carbon::parse(WordGame::START, 'Asia/Kolkata'); $d->lt($from); $d = $d->copy()->addDay()) {
            $before = array_merge($before, array_column(WordGame::day($d)['words'], 'answer'));
        }
        $words = $easy = $hard = $proverbs = [];
        for ($i = 0; $i < 365; $i++) {
            $day = $from->copy()->addDays($i);
            $today = WordGame::day($day)['words'];
            $this->assertCount(5, $today);
            $words = array_merge($words, array_column($today, 'answer'));
            $easy[] = WordGame::riddle($day)['words'][0]['answer'];
            $hard[] = WordGame::riddle($day, 'hard')['words'][0]['answer'];
            if ($i < 90) {
                $proverbs = array_merge($proverbs, array_column(Proverbs::day($day)['items'], 'full'));
            }
            if ($i < 150) {
                $this->assertSame([], array_intersect(array_column($today, 'answer'), $before), 'What November played does not come straight back');
            }
        }
        $this->assertCount(5 * 365, array_unique($words), 'Owner: no word again for a year');
        $this->assertCount(365, array_unique($easy), 'No easy riddle again for a year');
        $this->assertGreaterThanOrEqual(300, count(array_unique($hard)), 'The hard riddles last most of a year');
        $this->assertCount(4 * 90, array_unique($proverbs), 'No proverb again for three months');
        $this->assertSame([], array_intersect($easy, $hard), 'The two riddles of a day never share an answer');
        $this->assertSame(WordGame::day($from), WordGame::day($from->copy()->addHours(20)), 'Same for everyone all day');
    }

    /** D246: covers in cards and thumbnails come as small WebP copies when they exist. */
    public function test_cover_thumbnails_use_the_small_copies(): void
    {
        $dir = public_path('media/covers/2026/11');
        @mkdir($dir, 0777, true);
        foreach (['.jpg', '.w480.webp', '.w960.webp'] as $ext) {
            file_put_contents($dir.'/testcoverabc'.$ext, 'x');
        }
        try {
            $this->assertStringContainsString('/media/covers/2026/11/testcoverabc.w480.webp 480w', \App\Support\Images::source('media/covers/2026/11/testcoverabc.jpg', '88px'));
            $this->assertSame('', \App\Support\Images::source('media/covers/2026/11/none.jpg', '88px'));
            $html = \Illuminate\Support\Facades\Blade::render('<x-post-row :post="$p" />', ['p' => $this->makePost(['slug' => 'cover-row', 'featured_image_path' => 'media/covers/2026/11/testcoverabc.jpg', 'featured_image_width' => 1536, 'featured_image_height' => 1024, 'status' => 'published', 'published_at' => '2026-11-19 10:00:00'])]);
            $this->assertStringContainsString('<source type="image/webp" srcset="/media/covers/2026/11/testcoverabc.w480.webp 480w', $html);
        } finally {
            foreach (['.jpg', '.w480.webp', '.w960.webp'] as $ext) {
                @unlink($dir.'/testcoverabc'.$ext);
            }
        }
    }

    public function test_every_new_entry_is_fit_for_its_game(): void
    {
        $seen = [];
        foreach (['word-game', 'word-game-hard', 'word-game-2', 'word-game-hard-2'] as $file) {
            foreach (WordGame::file($file) as [$word, $clue]) {
                $n = count(WordGame::letters($word));
                $this->assertTrue($n >= 3 && $n <= 6, "$file: $word has $n letters");
                $this->assertArrayNotHasKey($word, $seen, "$file: $word is there twice");
                $this->assertStringNotContainsString($word, $clue, "$file: $word is in its own clue");
                $this->assertDoesNotMatchRegularExpression('/[\s\-\x{200C}\x{200D}0-9]/u', $word);
                $seen[$word] = true;
            }
        }
        $seen = [];
        foreach (['riddles', 'riddles-2', 'riddles-hard'] as $file) {
            foreach (WordGame::file($file) as [$riddle, $answer]) {
                $n = count(WordGame::letters($answer));
                $this->assertTrue($n >= 2 && $n <= 6, "$file: $answer has $n letters");
                $this->assertArrayNotHasKey($answer, $seen, "$file: $answer is there twice");
                $this->assertStringNotContainsString($answer, $riddle, "$file: $answer is in its own riddle");
                $seen[$answer] = true;
            }
        }
        $seen = [];
        foreach (Proverbs::all() as [$full, $word, $meaning]) {
            $this->assertCount(1, array_keys(preg_split('/\s+/u', $full), $word, true), "$full: the hidden word must be there once");
            $this->assertArrayNotHasKey($full, $seen);
            $this->assertNotSame('', trim($meaning));
            $seen[$full] = true;
        }
        $this->assertGreaterThanOrEqual(1095, count(WordGame::file('word-game')) + count(WordGame::file('word-game-2')));
        $this->assertGreaterThanOrEqual(730, count(WordGame::file('word-game-hard')) + count(WordGame::file('word-game-hard-2')));
        $this->assertGreaterThanOrEqual(365, count(WordGame::file('riddles')) + count(WordGame::file('riddles-2')));
        $this->assertGreaterThanOrEqual(360, count(Proverbs::all()));
    }
}
