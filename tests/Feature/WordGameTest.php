<?php

namespace Tests\Feature;

use App\Support\WordGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** D174 (owner): শব্দ-খেলা, the daily word jumble. */
class WordGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_bengali_words_split_into_letters_the_way_a_reader_counts_them(): void
    {
        $this->assertSame(['প্র', 'জা', 'প', 'তি'], WordGame::letters('প্রজাপতি'));
        $this->assertSame(['দা', 'র্জি', 'লিং'], WordGame::letters('দার্জিলিং'));
        $this->assertSame(['আ', 'কা', 'শ'], WordGame::letters('আকাশ'));
        $this->assertSame(['তেঁ', 'তু', 'ল'], WordGame::letters('তেঁতুল'));
    }

    public function test_every_word_in_the_list_is_fit_for_the_game(): void
    {
        $seen = [];
        foreach (WordGame::words() as [$word, $clue]) {
            $l = WordGame::letters($word);
            $this->assertSame($word, implode('', $l), $word);
            $this->assertTrue(count($l) >= 3 && count($l) <= 6, $word.' has '.count($l).' letters');
            $this->assertGreaterThan(1, count(array_unique($l)), $word);
            $this->assertTrue(mb_strlen($clue) >= 6 && ! str_contains($clue, $word), 'Clue for '.$word.' must not give the word');
            $this->assertArrayNotHasKey($word, $seen, 'Listed twice: '.$word);
            $seen[$word] = true;
        }
        $this->assertGreaterThanOrEqual(915, count($seen), 'Enough words for six months of five a day');
    }

    public function test_everyone_gets_the_same_five_words_each_day_and_no_repeats_for_six_months(): void
    {
        $day = Carbon::parse('2026-11-20', 'Asia/Kolkata');
        $a = WordGame::day($day);
        $this->assertSame($a, WordGame::day($day->copy()->addHours(20)), 'Same all day');
        $this->assertSame(7, $a['no']);
        $this->assertCount(5, $a['words']);
        foreach ($a['words'] as $w) {
            $this->assertNotSame(WordGame::letters($w['answer']), $w['tiles'], 'Shuffled');
            $sorted = $w['tiles']; sort($sorted); $letters = WordGame::letters($w['answer']); sort($letters);
            $this->assertSame($letters, $sorted, 'Same letters');
        }
        $used = [];
        for ($d = 0; $d < 183; $d++) {
            foreach (WordGame::day(Carbon::parse(WordGame::START, 'Asia/Kolkata')->addDays($d))['words'] as $w) {
                $used[] = $w['answer'];
            }
        }
        $this->assertCount(915, array_unique($used), 'Owner: no word comes back within six months');
    }

    public function test_the_page_shows_the_game_without_printing_the_answers_and_is_linked(): void
    {
        $game = WordGame::day();
        $page = $this->get('/khela/')->assertOk()->assertSee('শব্দ-খেলা')->assertSee('js/hp-khela.js', false)->assertSee($game['words'][0]['clue']);
        $json = $page->getContent();
        $this->assertStringNotContainsString('"'.$game['words'][0]['answer'].'"', $json, 'The answer is not in plain sight');
        $this->get('/')->assertSee('আজকের শব্দ-খেলা')->assertSee('/khela/', false);
        $this->get('/sitemap-sections.xml')->assertSee('/khela/', false);
    }
}
