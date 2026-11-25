<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D291: old WordPress title endings are dropped; the top pieces get their new Google titles once. */
class SearchTitlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_brand_endings_are_dropped_and_the_page_adds_hatpakha_once(): void
    {
        $post = $this->makePost(['slug' => 'dheu', 'status' => 'published', 'title' => 'ঢেউ', 'seo_title' => 'ঢেউ | Hatpakha - An online bengali english magazine']);
        $this->get($post->url())->assertSee('<title>ঢেউ | হাতপাখা</title>', false);

        $this->assertSame('Diwali Delight', Post::withoutOldBrand('Diwali Delight | HATPAKHA | An online magazine'));
        $this->assertSame('Mahalaya - Birendra Krishna Bhadra', Post::withoutOldBrand('Mahalaya - Birendra Krishna Bhadra | Hatpakha - An online magazine'));
        $this->assertSame('X', Post::withoutOldBrand('X | An Bengali - English Online Magazine [ ISSN: 2321-3035 ]'));
        // A real title is never cut.
        $this->assertSame('Rain - An online diary', Post::withoutOldBrand('Rain - An online diary'));
        $this->assertSame('Hatpakha Puja Special', Post::withoutOldBrand('Hatpakha Puja Special'));
    }

    public function test_new_titles_replace_only_the_old_ones(): void
    {
        $old = $this->makePost(['slug' => 'vidyasagar-the-social-reformer', 'status' => 'published', 'seo_title' => 'Ishwar Chandra Vidyasagar – The Social Reformer | Hatpakha']);
        $edited = $this->makePost(['slug' => 'mahalaya-birendra-krishna-bhadra', 'status' => 'published', 'seo_title' => 'Edited in the desk']);

        $migration = require database_path('migrations/2026_11_25_000100_search_titles_for_top_pieces.php');
        $migration->up();

        $this->assertSame('Ishwar Chandra Vidyasagar: Life and Social Reforms', $old->fresh()->seo_title);
        $this->assertStringContainsString('Widow Remarriage Act of 1856', $old->fresh()->seo_description);
        $this->assertSame('Edited in the desk', $edited->fresh()->seo_title);
        foreach ($migration::TITLES as $t) {
            $this->assertLessThanOrEqual(60, mb_strlen($t['title']));
            $this->assertLessThanOrEqual(155, mb_strlen((string) $t['description']));
        }
    }
}
