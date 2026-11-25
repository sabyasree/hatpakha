<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner, 2026-11-25: "online Bengali magazine" in Google should find Hatpakha (D290). */
class SearchFindabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_names_itself_in_bengali_and_english_for_search(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<title>'.e(HomeController::TITLE).'</title>', $html);
        $this->assertLessThanOrEqual(70, mb_strlen(HomeController::TITLE));
        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]*বাংলা[^"]*online Bengali literary magazine/u', $html);
        $this->assertStringContainsString('Hatpakha: an online Bengali literary magazine', $html);   // visible, in the footer

        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        $ld = json_decode(implode('', $m[1]), true);
        $this->assertNotNull($ld);
        $flat = json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('"foundingDate":"2012"', $flat);
        $this->assertStringContainsString('Hatpakha Bengali Magazine', $flat);
        $this->assertStringContainsString('"issn":"2321-3035"', $flat);
        $this->assertStringContainsString('wikidata.org/wiki/Q142281102', $flat);
    }

    public function test_main_sections_use_the_words_people_search_for(): void
    {
        $this->makePost(['slug' => 'kobita-ek', 'status' => 'published']);   // makes the poetry section with one piece
        $html = $this->get('/category/poetry/')->assertOk()->getContent();
        $this->assertStringContainsString('<title>বাংলা কবিতা: নতুন ও চেনা কবিদের কবিতা | হাতপাখা</title>', $html);
        $this->assertStringContainsString('content="হাতপাখার বাংলা কবিতা', $html);

        // A section's own introduction (written in the desk) still wins over the default description.
        Section::where('slug', 'poetry')->update(['about' => 'সম্পাদকের লেখা পরিচিতি।']);
        $this->get('/category/poetry/')->assertSee('content="সম্পাদকের লেখা পরিচিতি।"', false);

        $this->assertSame('ঐতিহ্য ও উৎসব', (new Section(['slug' => 'heritage', 'name_bn' => 'ঐতিহ্য ও উৎসব']))->searchTitle());   // others keep their name
    }

    public function test_short_common_addresses_go_to_the_right_page_not_to_a_piece_that_links_there(): void
    {
        $this->makePost(['slug' => 'jindagi', 'status' => 'published', 'body_html' => '<p>লেখা <a href="https://hatpakha.com/about-hatpakha/">হাতপাখার কথা</a></p>']);

        $this->get('/about/')->assertStatus(301)->assertRedirect(url('/about-hatpakha').'/');
        $this->get('/about-us/')->assertStatus(301)->assertRedirect(url('/about-hatpakha').'/');
        $this->get('/contact/')->assertStatus(301)->assertRedirect(url('/contact-us').'/');
        // A word that only appears in a link, never in a picture's file name, finds no piece.
        $this->get('/about-hatpakha-old/')->assertNotFound();
    }

    public function test_section_introductions_fill_only_empty_ones_and_show_on_the_page(): void
    {
        $this->makePost(['slug' => 'kobita-dui', 'status' => 'published']);   // the poetry section, with a piece
        Section::firstOrCreate(['slug' => 'travel'], ['name_bn' => 'ভ্রমণ', 'format' => 'prose'])->update(['about' => 'সম্পাদকের নিজের লেখা।']);

        $migration = require database_path('migrations/2026_11_25_000200_section_introductions.php');
        $migration->up();

        $this->get('/category/poetry/')->assertOk()
            ->assertSee('এক দশকের বেশি সময় ধরে হাতপাখায় ছাপা বাংলা কবিতা')
            ->assertSee('<meta name="description" content="এক দশকের বেশি সময় ধরে', false);
        $this->assertSame('সম্পাদকের নিজের লেখা।', Section::where('slug', 'travel')->value('about'));   // the desk's text stays
    }
}
