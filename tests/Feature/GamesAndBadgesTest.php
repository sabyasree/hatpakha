<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\User;
use App\Support\Badges;
use App\Support\GameScores;
use App\Support\WordGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D177 (riddle, crossword, leaderboard) and D178 (badges). */
class GamesAndBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_riddle_and_the_crossword_are_the_same_for_everyone_and_well_formed(): void
    {
        $day = Carbon::parse('2026-12-01', 'Asia/Kolkata');
        $r = WordGame::riddle($day);
        $this->assertSame($r, WordGame::riddle($day->copy()->addHours(10)));
        $w = $r['words'][0];
        $letters = WordGame::letters($w['answer']);
        $this->assertSame([], array_diff($letters, $w['tiles']), 'Every letter of the answer is among the tiles');

        foreach ([0, 3, 40] as $add) {
            $c = WordGame::crossword($day->copy()->addDays($add));
            $this->assertGreaterThanOrEqual(4, count($c['words']), 'A real crossword, not one word');
            $grid = [];
            foreach ($c['words'] as $word) {
                $this->assertSame(WordGame::letters($word['answer']), $word['letters']);
                foreach ($word['letters'] as $i => $l) {
                    $key = ($word['row'] + ($word['down'] ? $i : 0)).','.($word['col'] + ($word['down'] ? 0 : $i));
                    $this->assertTrue(! isset($grid[$key]) || $grid[$key] === $l, 'Crossing squares agree');
                    $grid[$key] = $l;
                    $this->assertContains($l, $c['bank']);
                    $this->assertLessThan($c['size'], max($word['row'] + ($word['down'] ? $i : 0), $word['col'] + ($word['down'] ? 0 : $i)));
                }
            }
            $this->assertLessThan(array_sum(array_map(fn ($x) => count($x['letters']), $c['words'])), count($grid), 'Words cross');
        }
        foreach (WordGame::riddles() as [$riddle, $answer]) {
            $n = count(WordGame::letters($answer));
            $this->assertTrue($n >= 2 && $n <= 6 && ! str_contains($riddle, $answer), 'Riddle answer: '.$answer);
        }
    }

    public function test_game_pages_load_and_do_not_print_answers_in_plain_sight(): void
    {
        $this->get('/khela/dhadha/')->assertOk()->assertSee('ধাঁধা')->assertSee('js/hp-khela.js', false)->assertSee('লিডারবোর্ড');
        $c = WordGame::crossword();
        $page = $this->get('/khela/shobdojobdo/')->assertOk()->assertSee('শব্দজব্দ')->assertSee('js/hp-jobdo.js', false)->assertSee($c['words'][0]['clue'])->getContent();
        $this->assertStringNotContainsString('"'.$c['words'][0]['answer'].'"', $page);
        $this->get('/khela/board/')->assertOk()->assertSee('লিডারবোর্ড')->assertSee('এখনও কেউ খেলেননি');
    }

    public function test_scores_of_signed_in_players_make_the_leaderboard_once_a_day(): void
    {
        $today = now('Asia/Kolkata')->toDateString();
        $this->postJson('/khela/score', ['game' => 'word', 'day' => $today, 'seconds' => 60, 'results' => ['g', 'g', 'g', 'g', 'g']])->assertUnauthorized();

        $a = User::factory()->create(['name' => 'Mita Sen']);
        $b = User::factory()->create(['name' => 'Rina Basu', 'board_hidden' => true]);
        $c = User::factory()->create(['name' => 'Amal Roy']);
        $this->actingAs($a)->postJson('/khela/score', ['game' => 'word', 'day' => $today, 'seconds' => 60, 'results' => ['g', 'g', 'y', 'r', 'g']])->assertOk()->assertJson(['ok' => true, 'points' => 70, 'streak' => 1]);
        $this->postJson('/khela/score', ['game' => 'word', 'day' => $today, 'seconds' => 5, 'results' => ['g', 'g', 'g', 'g', 'g']])->assertOk();
        $this->assertSame(70, (int) DB::table('game_scores')->where('user_id', $a->id)->value('points'), 'The first result of the day counts');
        $this->postJson('/khela/score', ['game' => 'cross', 'day' => $today, 'seconds' => 90, 'hints' => 2, 'solved' => true])->assertJson(['points' => 80]);
        $this->postJson('/khela/score', ['game' => 'riddle', 'day' => '2020-01-01', 'seconds' => 9, 'results' => ['g']])->assertOk();
        $this->assertSame(2, DB::table('game_scores')->count(), 'Only today\'s puzzle');
        $this->postJson('/khela/score', ['game' => 'word', 'day' => $today, 'seconds' => 9, 'results' => ['g', 'x']])->assertStatus(422);

        GameScores::record($b, 'riddle', $today, 100, 30);
        GameScores::record($c, 'riddle', $today, 100, 20);
        $board = GameScores::board('today', $b);
        $this->assertSame(['Mita Sen', 'Amal Roy'], array_column($board['rows'], 'name'), 'Most points first; a hidden name is not listed');
        $this->assertSame([150, 100], array_column($board['rows'], 'points'));
        $this->assertSame(100, $board['mine']['points'], 'A hidden player still sees their own score');

        $this->actingAs($b)->get('/khela/board/')->assertOk()->assertSee('Mita Sen')->assertDontSee('Rina Basu')->assertSee('নাম লুকোনো আছে');
        $this->post('/account/board/', ['on' => 1])->assertRedirect();
        $this->get('/khela/board/')->assertSee('Rina Basu');
        $this->get('/account/settings/')->assertSee('লিডারবোর্ডে আমার নাম দেখান');
    }

    public function test_badges_come_in_three_tiers_from_what_a_person_really_did(): void
    {
        $u = User::factory()->create(['role' => Role::Writer]);
        $this->assertSame(0, collect(Badges::for($u))->sum('tier'), 'A new account has none');

        $author = Author::create(['slug' => 'w', 'display_name' => 'W', 'user_id' => $u->id]);
        $this->makePost(['slug' => 'one'], $author);
        foreach (range(0, 7) as $d) {   // eight days in a row
            DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => 'word', 'day' => now('Asia/Kolkata')->subDays($d)->toDateString(), 'points' => 80, 'seconds' => 50]);
        }
        $b = collect(Badges::for($u))->keyBy('key');
        $this->assertSame([1, 'ব্রোঞ্জ', 10], [$b['writer']['tier'], $b['writer']['tierName'], $b['writer']['next']]);
        $this->assertSame([1, 8, 30], [$b['streak']['tier'], $b['streak']['count'], $b['streak']['next']]);
        $this->assertSame([1, 640], [$b['player']['tier'], $b['player']['count']]);
        $this->assertSame(0, $b['reader']['tier']);
        DB::table('game_scores')->where('user_id', $u->id)->update(['points' => 100]);
        DB::table('game_scores')->insert(collect(range(8, 60))->map(fn ($d) => ['user_id' => $u->id, 'game' => 'word', 'day' => now('Asia/Kolkata')->subDays($d)->toDateString(), 'points' => 100, 'seconds' => 50])->all());
        $b = collect(Badges::for($u))->keyBy('key');
        $this->assertSame([2, 'রুপো'], [$b['streak']['tier'], $b['streak']['tierName']]);
        $this->assertSame(2, $b['player']['tier']);

        $this->actingAs($u)->get('/account/profile/')->assertOk()->assertSee('আমার ব্যাজ')->assertSee('রোজকার খেলোয়াড়')->assertSee('রুপো')->assertSee('সোনা পেতে আর')
            ->assertSee('data-medal', false)->assertSee('data-badge-share', false)->assertSee('data-badge-canvas', false);   // D180: medals with the logo's fan, and a share card
        // D181 (owner + UX review): the medal says what it is for; the tier is colour + edge + stars, never a word
        $small = \App\Support\Badges::medalSvg('reader', 3, 104);
        $big = \App\Support\Badges::medalSvg('reader', 3, 480);
        $this->assertStringNotContainsString('<text', $small, 'No words at sizes where they cannot be read');
        $this->assertStringContainsString('>পড়ুয়া</text>', $big, 'The badge\'s name on the banner of the large medal');
        $this->assertStringNotContainsString('সোনা', $big, 'The tier is not written inside the medal');
        $this->assertStringNotContainsString('হাতপাখা', $big);
        $stars = fn (string $svg) => substr_count($svg, 'M0 -');
        $this->assertSame([1, 2, 3], [$stars(\App\Support\Badges::medalSvg('writer', 1, 104)), $stars(\App\Support\Badges::medalSvg('writer', 2, 104)), $stars($small)], 'One, two, three stars');
        $this->assertNotSame(\App\Support\Badges::EMBLEMS['reader'], \App\Support\Badges::EMBLEMS['writer']);
        $this->assertStringContainsString('stroke-dasharray', \App\Support\Badges::medalSvg('reader', 0, 104), 'Not yet earned: a dashed outline');
        $this->assertStringNotContainsString('data-badge-share data-name="পড়ুয়া"', $this->get('/account/profile/')->getContent(), 'Only earned badges can be shared');
        $author->refreshCount();
        auth()->logout();
        $this->get($author->url())->assertOk()->assertSee('হাতপাখার ব্যাজ')->assertSee('রোজকার খেলোয়াড়');
    }

    public function test_readers_corner_publishes_by_itself_only_what_the_ai_passed_and_the_owner_can_switch_it_off(): void
    {
        // D176 (owner: "get rid of the admin click, I want it mostly automated")
        $p = \App\Support\Kolom::openWeek();
        $ok = \App\Models\ReaderEntry::create(['reader_prompt_id' => $p->id, 'name' => 'ক', 'body' => 'এআই পড়েছে এমন লেখা', 'status' => 'ok', 'ai_score' => 7, 'sender_hash' => 'a']);
        \App\Models\ReaderEntry::create(['reader_prompt_id' => $p->id, 'name' => 'খ', 'body' => 'এআই পড়েনি এমন লেখা', 'status' => 'new', 'sender_hash' => 'b']);
        \App\Support\Kolom::pickWeek();
        $this->assertSame([$ok->id], $p->picked()->pluck('id')->all(), 'Unread entries are not proposed when the AI has read others');

        \App\Models\Setting::put(\App\Support\Kolom::AUTO, '0');
        $this->assertFalse(\App\Support\Kolom::autoPublish(), 'Switched off: waits for the owner');
        \App\Models\Setting::put(\App\Support\Kolom::AUTO, '1');
        $this->assertTrue(\App\Support\Kolom::autoPublish());
        $this->assertSame('published', $p->fresh()->status);
        $this->get('/kolom/')->assertSee('এআই পড়েছে এমন লেখা')->assertDontSee('এআই পড়েনি এমন লেখা');

        $q = \App\Support\Kolom::openWeek();
        \App\Models\ReaderEntry::create(['reader_prompt_id' => $q->id, 'name' => 'গ', 'body' => 'কেউ পড়েনি এমন লেখা', 'status' => 'new', 'sender_hash' => 'c']);
        \App\Support\Kolom::pickWeek();
        $this->assertFalse(\App\Support\Kolom::autoPublish(), 'Nothing the AI has not read goes out without a person');
        $this->assertSame('closed', $q->fresh()->status);
    }

    /** D201 (owner: "for শব্দজব্দ can we do 3 level… change the words accordingly… rearrange the badge"). */
    public function test_the_crossword_has_three_levels_a_day_each_with_its_own_words_points_and_page(): void
    {
        $hardList = array_column(require database_path('content/word-game-hard.php'), 0);
        $day = \Illuminate\Support\Carbon::parse('2026-11-20', 'Asia/Kolkata');
        foreach (range(0, 20) as $add) {
            $d = $day->copy()->addDays($add * 9);
            [$easy, $mid, $hard] = [WordGame::crossword($d, 'easy'), WordGame::crossword($d, 'medium'), WordGame::crossword($d, 'hard')];
            $this->assertSame($mid, WordGame::crossword($d), 'মাঝারি is the puzzle there always was');
            $this->assertGreaterThanOrEqual(4, count($easy['words']));
            $this->assertLessThanOrEqual(3, max(array_map(fn ($w) => count($w['letters']), $easy['words'])), 'সহজ: short words only');
            $this->assertSame([], array_intersect(array_column($easy['words'], 'answer'), $hardList));
            $this->assertGreaterThanOrEqual(8, count($hard['words']), 'কঠিন: a bigger grid');
            $this->assertGreaterThanOrEqual(5, count(array_intersect(array_column($hard['words'], 'answer'), $hardList)), 'কঠিন: mostly the harder words');
            $this->assertLessThanOrEqual(12, $hard['size'], 'Still fits a phone');
            $this->assertSame([], array_diff(array_merge(...array_column($hard['words'], 'letters')), $hard['bank']), 'Every letter needed is in the bank');
        }

        $hard = WordGame::crossword(null, 'hard');
        $this->get('/khela/shobdojobdo/')->assertOk()->assertSee('সহজ')->assertSee('কঠিন')->assertSee('/khela/shobdojobdo/kothin/', false);
        $page = $this->get('/khela/shobdojobdo/kothin/')->assertOk()->assertSee($hard['words'][0]['clue'])->assertSee('"key":"cross3"', false)
            ->assertSee('<link rel="canonical" href="'.\App\Support\Url::to('/khela/shobdojobdo/kothin').'"', false)->getContent();
        $this->assertStringNotContainsString('"'.$hard['words'][0]['answer'].'"', $page);
        $this->get('/khela/shobdojobdo/sohoj/')->assertOk()->assertSee('"key":"cross1"', false);
        $this->get('/khela/shobdojobdo/majhari/')->assertNotFound();

        $today = now('Asia/Kolkata')->toDateString();
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/khela/score', ['game' => 'cross1', 'day' => $today, 'seconds' => 40, 'hints' => 2, 'solved' => true])->assertJson(['points' => 50]);
        $this->postJson('/khela/score', ['game' => 'cross', 'day' => $today, 'seconds' => 40, 'hints' => 0, 'solved' => true])->assertJson(['points' => 100]);
        $this->postJson('/khela/score', ['game' => 'cross3', 'day' => $today, 'seconds' => 40, 'hints' => 1, 'solved' => true])->assertJson(['points' => 125]);
        $this->assertSame(275, (int) DB::table('game_scores')->where('user_id', $u->id)->sum('points'), 'All three count on the same day');
        $this->postJson('/khela/report', ['game' => 'cross3', 'word' => $hard['words'][0]['answer'], 'reason' => 'hard'])->assertOk();
    }

    public function test_the_crossword_medal_asks_for_harder_levels_at_each_tier(): void
    {
        $u = User::factory()->create();
        $add = function (string $game, int $n, int $points = 50) use ($u) {
            static $day = 0;
            foreach (range(1, $n) as $i) {
                DB::table('game_scores')->insert(['user_id' => $u->id, 'game' => $game, 'day' => now()->subDays(++$day)->toDateString(), 'points' => $points, 'seconds' => 60]);
            }
        };
        $medal = fn () => collect(Badges::for($u))->firstWhere('key', 'cross');
        $add('cross1', 9);
        $add('cross', 3, 0);   // gave up: not solved
        $this->assertSame(0, $medal()['tier']);
        $add('cross1', 21);
        $this->assertSame([1, 0, 25], [$medal()['tier'], $medal()['count'], $medal()['next']], 'Thirty easy ones: bronze, and silver needs মাঝারি or কঠিন');
        $add('cross', 25);
        $this->assertSame([2, 0, 50], [$medal()['tier'], $medal()['count'], $medal()['next']]);
        $add('cross3', 50);
        $this->assertSame([3, 'সোনা', 100], [$medal()['tier'], $medal()['tierName'], $medal()['percent']]);
        $this->assertStringContainsString('<svg', Badges::medalSvg('cross', 3, 200));
    }
}
