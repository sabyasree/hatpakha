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

        // D199: the harder words follow the same rules, and none of them is also an everyday word.
        $hard = require database_path('content/word-game-hard.php');
        foreach ($hard as [$word, $clue]) {
            $l = WordGame::letters($word);
            $this->assertSame($word, implode('', $l), $word);
            $this->assertTrue(count($l) >= 3 && count($l) <= 6, $word.' has '.count($l).' letters');
            $this->assertGreaterThan(1, count(array_unique($l)), $word);
            $this->assertTrue(mb_strlen($clue) >= 6 && ! str_contains($clue, $word), 'Clue for '.$word.' must not give the word');
            $this->assertArrayNotHasKey($word, $seen, 'Listed twice, or already an everyday word: '.$word);
            $seen[$word] = true;
        }
        $this->assertGreaterThanOrEqual(366, count($hard), 'Enough harder words for six months of two a day');
    }

    /** D199 (owner: "add words which are a little tough… two words is good"): two harder words a day, last, none again within six months. */
    public function test_the_last_two_words_of_the_day_are_harder_ones_and_none_repeats_for_six_months(): void
    {
        $from = Carbon::parse(WordGame::HARD_FROM, 'Asia/Kolkata');
        $this->assertSame([], WordGame::hardWords($from->copy()->subDay()), 'The days before stay as they were');
        $hardList = array_column(require database_path('content/word-game-hard.php'), 0);
        $day = WordGame::day($from);
        $this->assertCount(5, $day['words']);
        foreach ([3, 4] as $i) {
            $this->assertContains($day['words'][$i]['answer'], $hardList, 'The fourth and fifth words are the harder ones');
        }
        foreach (array_slice($day['words'], 0, 3) as $w) {
            $this->assertNotContains($w['answer'], $hardList, 'The first three stay everyday words');
        }
        $this->assertSame($day, WordGame::day($from->copy()->addHours(20)), 'Same for everyone all day');
        $used = [];
        for ($d = 0; $d < 183; $d++) {
            foreach (array_slice(WordGame::day($from->copy()->addDays($d))['words'], 3) as $w) {
                $used[] = $w['answer'];
            }
        }
        $this->assertCount(366, array_unique($used), 'No harder word comes back within six months');
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

    /** Owner, 2026-11-14: "in the খেলা page there is no footer". Game pages end with the site's full footer like any page. */
    public function test_game_pages_have_the_sites_full_footer(): void
    {
        foreach (['/khela/', '/khela/dhadha/', '/khela/shobdojobdo/', '/khela/board/'] as $url) {
            $this->get($url)->assertOk()->assertSee('<footer class="mt-16 bg-navy', false)->assertSee('aria-label="ফুটার"', false);
        }
    }
}
