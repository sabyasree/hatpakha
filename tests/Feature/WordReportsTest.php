<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Support\WordGame;
use App\Support\WordReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D179 (owner): players report a wrong word; it is corrected or taken out by itself, from the next day. */
class WordReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        Cache::forget('hp.wordfixes');
        parent::tearDown();
    }

    public function test_a_player_reports_a_word_from_todays_game_and_only_from_it(): void
    {
        $word = WordGame::day()['words'][0]['answer'];
        $this->postJson('/khela/report', ['game' => 'word', 'word' => $word, 'reason' => 'spelling', 'note' => '<b>ঠিক বানান</b> অন্য'])->assertOk()->assertJson(['ok' => true]);
        $this->postJson('/khela/report', ['game' => 'word', 'word' => $word, 'reason' => 'clue'])->assertOk();   // same person again: counted once
        $this->assertSame(1, DB::table('word_reports')->count());
        $this->assertSame('ঠিক বানান অন্য', DB::table('word_reports')->value('note'));
        $this->postJson('/khela/report', ['game' => 'word', 'word' => 'যাখুশিতাই', 'reason' => 'other'])->assertStatus(422);
        $this->postJson('/khela/report', ['game' => 'word', 'word' => $word, 'reason' => 'rude'])->assertStatus(422);
        $this->get('/khela/')->assertSee('js/hp-report.js', false)->assertSee('data-report', false);
    }

    public function test_the_ai_corrects_or_removes_from_tomorrow_and_today_stays_as_it_is(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $today = WordGame::day();
        [$a, $b, $c] = [$today['words'][0], $today['words'][1], $today['words'][2]];
        foreach ([$a, $b, $c] as $i => $w) {
            DB::table('word_reports')->insert(['game' => 'word', 'word' => $w['answer'], 'reason' => 'clue', 'sender_hash' => 'h'.$i, 'created_at' => now(), 'updated_at' => now()]);
        }
        Claude::$fake = fn ($purpose, $user) => ['entries' => [
            ['key' => 'word|'.$a['answer'], 'verdict' => 'fix', 'word' => '', 'clue' => 'একদম নতুন পরিষ্কার সূত্র', 'why' => 'সূত্রটি অন্য শব্দেও খাটে'],
            ['key' => 'word|'.$b['answer'], 'verdict' => 'remove', 'word' => '', 'clue' => '', 'why' => 'শব্দটি অচেনা'],
            ['key' => 'word|'.$c['answer'], 'verdict' => 'keep', 'word' => '', 'clue' => '', 'why' => 'ঠিকই আছে'],
        ]];
        $this->assertSame(3, WordReports::review());
        $this->assertSame(['fixed', 'removed', 'kept'], DB::table('word_reports')->orderBy('id')->pluck('status')->all());

        $this->assertSame($today, WordGame::day(), 'A puzzle already being played never changes');
        $tomorrow = now('Asia/Kolkata')->addDay();
        $list = collect(WordGame::words($tomorrow))->keyBy(0);
        $this->assertSame('একদম নতুন পরিষ্কার সূত্র', $list[$a['answer']][1]);
        $this->assertFalse($list->has($b['answer']), 'Out of the games');
        $this->assertSame($c['clue'], $list[$c['answer']][1]);
        $this->assertCount(5, WordGame::day($tomorrow)['words'], 'Still five words a day');

        WordReports::undo('word', $b['answer']);
        $this->assertTrue(collect(WordGame::words($tomorrow))->pluck(0)->contains($b['answer']), 'The desk can put it back');
    }

    public function test_a_correction_that_breaks_the_rules_removes_the_word_and_no_ai_needs_three_players(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $w = WordGame::day()['words'][0];
        DB::table('word_reports')->insert(['game' => 'word', 'word' => $w['answer'], 'reason' => 'spelling', 'sender_hash' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        Claude::$fake = fn () => ['entries' => [['key' => 'word|'.$w['answer'], 'verdict' => 'fix', 'word' => 'two words', 'clue' => '', 'why' => 'x']]];
        WordReports::review();
        $this->assertSame('removed', DB::table('word_reports')->value('status'), 'Safer out than wrong');
        $this->assertSame('remove', DB::table('word_fixes')->value('action'));

        config(['ai.enabled' => false]);
        Claude::$fake = null;
        $r = WordGame::riddle()['words'][0];
        foreach (['p', 'q'] as $h) {
            DB::table('word_reports')->insert(['game' => 'riddle', 'word' => $r['answer'], 'reason' => 'clue', 'sender_hash' => $h, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame(0, WordReports::review(), 'Two players without AI: waits');
        DB::table('word_reports')->insert(['game' => 'riddle', 'word' => $r['answer'], 'reason' => 'clue', 'sender_hash' => 'r', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(1, WordReports::review());
        $this->assertNotSame($r['answer'], WordGame::riddle(now('Asia/Kolkata')->addDay()->startOfDay())['words'][0]['answer'] === $r['answer'] ? 'same' : $r['answer'].'-gone');
        $this->assertFalse(collect(WordGame::riddles(now('Asia/Kolkata')->addDay()))->pluck(1)->contains($r['answer']));
    }
}
