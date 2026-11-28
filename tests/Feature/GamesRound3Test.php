<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Models\User;
use App\Support\GameCommunity;
use App\Support\GameFestival;
use App\Support\GameScores;
use App\Support\GameSocial;
use App\Support\WordGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D331: the owner's list after the games research. */
class GamesRound3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-12-09 12:00', 'Asia/Kolkata'));   // a Wednesday
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function played(User $u, string $day, array $games): void
    {
        foreach ($games as $g => $p) {
            DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => $g, 'day' => $day, 'points' => $p, 'seconds' => 30, 'created_at' => $day.' 10:00:00']);
        }
    }

    public function test_a_missed_day_is_made_up_with_a_fourth_game_and_never_told_as_lost(): void
    {
        $u = User::factory()->create();
        foreach (['2026-12-01', '2026-12-02', '2026-12-04', '2026-12-05'] as $d) {   // the 3rd missed: the week's rest day
            $this->played($u, $d, ['word' => 100, 'banan' => 100, 'riddle' => 40]);
        }
        // the 6th missed too: the rest day is used, so the run waits to be joined today
        Carbon::setTestNow(Carbon::parse('2026-12-07 12:00', 'Asia/Kolkata'));
        $s = GameScores::streak($u);
        $this->assertTrue($s['mend']);
        $this->assertSame(4, $s['now'], 'The run (days played) is still shown, with an offer');
        $this->actingAs($u)->get('/khela/aaj/')->assertOk()->assertSee('ধারা জোড়া লাগবে');
        $this->played($u, '2026-12-07', ['word' => 100, 'banan' => 100, 'riddle' => 40, 'probad' => 50]);   // four games
        $s = GameScores::streak($u);
        $this->assertFalse($s['mend']);
        $this->assertSame(5, $s['now']);
        $this->assertSame(['2026-12-06'], $s['mended']);
        // with three games only the run would have started again
        $v = User::factory()->create();
        foreach (['2026-12-01', '2026-12-02', '2026-12-04', '2026-12-05', '2026-12-07'] as $d) {
            $this->played($v, $d, ['word' => 100, 'banan' => 100, 'riddle' => 40]);
        }
        $this->assertSame(1, GameScores::streak($v)['now']);
    }

    public function test_old_puzzles_for_practice_and_shares_that_name_the_day(): void
    {
        $this->get('/khela/purono/')->assertOk()->assertSee('পুরোনো খেলা')->assertSee('/khela/purono/banan/2026-12-08/', false);
        $page = $this->get('/khela/purono/banan/2026-12-08/')->assertOk()->assertSee('অনুশীলন')->assertSee('noindex', false);
        $this->assertStringNotContainsString('data-score-url', $page->getContent(), 'Practice gives no points');
        $this->assertStringContainsString('"practice":true', $page->getContent());
        $this->get('/khela/purono/shobdo/2026-12-01/')->assertOk();
        $this->get('/khela/purono/shobdojobdo/2026-12-01/')->assertOk();
        $this->get('/khela/purono/banan/2026-12-09/')->assertRedirect(\App\Support\Url::to('/khela/banan'));   // today: the live game
        $this->get('/khela/purono/banan/2026-12-10/')->assertNotFound();
        $this->get('/khela/purono/banan/2026-10-01/')->assertNotFound();
        $share = \App\Http\Controllers\GameController::shareFor('banan', '2026-12-09');
        $this->assertStringEndsWith('/khela/purono/banan/2026-12-09/', $share['dayUrl']);
        $this->assertNotSame('', $share['familyLine']);
        $this->get('/khela/banan/')->assertSee('purono\/banan\/2026-12-09', false);
    }

    public function test_a_private_circle_is_never_listed_and_the_board_shows_ones_neighbours(): void
    {
        [$a, $b] = [User::factory()->create(), User::factory()->create()];
        $code = GameSocial::makeTeam($a, 'চৌধুরী পরিবার', true);
        GameSocial::join($b, DB::table('game_teams')->where('code', $code)->value('id'));
        $this->played($a, '2026-12-09', ['banan' => 100]);
        $this->played($b, '2026-12-09', ['banan' => 80]);
        Cache::flush();
        $this->assertSame([], GameSocial::teams(), 'A private circle is not on the public list');
        $this->actingAs($a)->get('/khela/dol/'.$code.'/')->assertOk()->assertSee('পরিবার-বন্ধুদের দল')->assertSee('আজ দলে');

        $me = null;
        foreach (range(1, 26) as $i) {
            $u = User::factory()->create(['name' => 'খেলোয়াড় '.$i]);
            $this->played($u, '2026-12-09', ['word' => 300 - $i * 10]);
            $me = $i === 24 ? $u : $me;
        }
        Cache::flush();
        $board = GameScores::board('today', $me);
        $this->assertCount(5, $board['near']);
        $this->assertTrue(collect($board['near'])->contains('me', true));
    }

    public function test_a_festival_week_brings_its_words_a_riddle_and_a_keepsake(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-02-20 12:00', 'Asia/Kolkata'));   // the day before ২১শে ফেব্রুয়ারি
        $f = GameFestival::current();
        $this->assertSame('ekushe-february', $f['slug']);
        $words = array_column(WordGame::day()['words'], 'answer');
        $this->assertContains(GameFestival::word(now('Asia/Kolkata'))[0], $words);
        $this->assertSame(GameFestival::riddle(now('Asia/Kolkata'))[1], WordGame::riddle()['words'][0]['answer']);
        $this->get('/khela/')->assertOk()->assertSee('-এর সপ্তাহ');
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/khela/score', ['game' => 'banan', 'day' => '2027-02-20', 'seconds' => 30, 'results' => ['g', 'g', 'g', 'g', 'g']])
            ->assertOk()->assertJsonPath('keepsake', $f['name']);
        $this->postJson('/khela/score', ['game' => 'word', 'day' => '2027-02-20', 'seconds' => 30, 'results' => ['g', 'g', 'g', 'g', 'g']])->assertJsonPath('keepsake', null);   // once
        $this->get('/account/profile/')->assertOk()->assertSee('উৎসবের স্মারক')->assertSee($f['name']);
        Carbon::setTestNow(Carbon::parse('2027-03-10 12:00', 'Asia/Kolkata'));
        $this->assertNull(GameFestival::current());
    }

    public function test_the_sunday_crossword_is_bigger_and_still_fits_a_phone(): void
    {
        $sat = WordGame::crossword(Carbon::parse('2026-12-12', 'Asia/Kolkata'), 'hard');
        $sun = WordGame::crossword(Carbon::parse('2026-12-13', 'Asia/Kolkata'), 'hard');
        $this->assertGreaterThan(count($sat['words']), count($sun['words']));
        $this->assertLessThanOrEqual(12, $sun['size']);
    }

    public function test_your_month_in_a_card(): void
    {
        $u = User::factory()->create();
        $this->played($u, '2026-11-20', ['banan' => 100, 'word' => 80]);
        $this->played($u, '2026-11-21', ['banan' => 60]);
        $m = GameScores::month($u, '2026-11');
        $this->assertSame(2, $m['days']);
        $this->assertSame(180, $m['best']);
        $this->assertSame('বানান-যাচাই', $m['favourite']);
        Carbon::setTestNow(Carbon::parse('2026-12-03 12:00', 'Asia/Kolkata'));
        $this->actingAs($u)->get('/khela/aaj/')->assertOk()->assertSee('আপনার নভেম্বর')->assertSee('WhatsApp-এ আমার মাস পাঠান');
    }

    public function test_the_talk_opens_after_playing_and_the_ai_screens_every_message(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Claude::$fake = fn ($purpose, $user) => ['checks' => [['id' => (int) preg_replace('/\D.*/s', '', explode('id="', $user)[1]), 'ok' => ! str_contains($user, 'বাজে'), 'reason' => '']]];
        $u = User::factory()->create(['name' => 'মিতা']);
        $this->actingAs($u)->post('/khela/adda', ['body' => 'আজ বানানটা কঠিন ছিল'])->assertSessionHas('adda');
        $this->assertSame(0, DB::table('game_talk')->count(), 'Only after a game today');
        $this->played($u, '2026-12-09', ['banan' => 100]);
        $this->post('/khela/adda', ['body' => 'আজ বানানটা কঠিন ছিল']);
        $this->post('/khela/adda', ['body' => 'বাজে কথা']);
        $this->assertSame(['shown', 'hidden'], DB::table('game_talk')->orderBy('id')->pluck('status')->all());
        $other = User::factory()->create();
        $this->played($other, '2026-12-09', ['word' => 100]);
        $this->actingAs($other)->get('/khela/aaj/')->assertOk()->assertSee('আজ বানানটা কঠিন ছিল')->assertDontSee('বাজে কথা');
        $this->get('/logout');
    }

    public function test_a_readers_riddle_is_checked_and_shown_with_their_name(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        Claude::$fake = fn ($purpose, $user) => ['checks' => [['id' => (int) preg_replace('/\D.*/s', '', explode('id="', $user)[1]), 'ok' => true, 'reason' => '']]];
        $u = User::factory()->create(['name' => 'রাজু']);
        $this->actingAs($u)->post('/khela/dhadha/pathan', ['riddle' => 'বনের মধ্যে টিয়া পাখি, লাল তার ঠোঁট, পেট ভরা মধু', 'answer' => 'লিচু'])->assertRedirect();
        $this->assertSame('ok', DB::table('reader_riddles')->value('status'));
        $r = GameCommunity::todaysRiddle();
        $this->assertSame('রাজু', $r['name']);
        $this->get('/khela/dhadha/')->assertOk()->assertSee('পাঠকের ধাঁধা')->assertSee('পাঠিয়েছেন রাজু');
    }

    public function test_places_are_compared_by_their_players_average(): void
    {
        foreach ([['কলকাতা', 300], ['কলকাতা', 100], ['কলকাতা', 100], ['ঢাকা বিভাগ', 250], ['ঢাকা বিভাগ', 200], ['ঢাকা বিভাগ', 150], ['বীরভূম', 300]] as $i => [$r, $p]) {
            $u = User::factory()->create(['game_region' => $r]);
            $this->played($u, '2026-12-09', ['word' => min(100, $p), 'banan' => max(0, min(100, $p - 100)), 'riddle' => max(0, $p - 200)]);
        }
        Cache::flush();
        $list = GameCommunity::regions();
        $this->assertSame(['ঢাকা বিভাগ', 'কলকাতা'], array_column($list, 'region'), 'Average, three players at least (বীরভূম has one)');
        $u = User::factory()->create();
        $this->actingAs($u)->post('/khela/region', ['region' => 'বীরভূম'])->assertRedirect();
        $this->assertSame('বীরভূম', DB::table('users')->where('id', $u->id)->value('game_region'));
        $this->post('/khela/region', ['region' => 'কোথাও না'])->assertStatus(422);
        $this->get('/khela/dol/')->assertOk()->assertSee('জায়গার লড়াই');
    }
}
