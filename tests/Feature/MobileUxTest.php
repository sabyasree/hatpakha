<?php

namespace Tests\Feature;

use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fixes from the mobile UX review: things that must not quietly come back. */
class MobileUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_address_is_never_shown_as_a_name(): void
    {
        $this->assertSame('Bijoy Bose', (new Author(['display_name' => 'bijoy_bose_82@yahoo.com']))->publicName());
        $a = Author::create(['display_name' => 'mitra.das@gmail.com', 'slug' => 'mitra']);
        $this->makePost(['slug' => 'kobita', 'status' => 'published', 'published_at' => now()->subDay()], $a);
        $this->get('/kobita/')->assertOk()->assertDontSee('mitra.das@gmail.com')->assertSee('Mitra Das');
        $this->get('/feed/')->assertDontSee('mitra.das@gmail.com');
    }

    public function test_bengali_page_links_breadcrumbs_and_bottom_bar(): void
    {
        foreach (range(1, 30) as $i) {
            $this->makePost(['slug' => 'k'.$i, 'status' => 'published', 'published_at' => now()->subDays($i)]);
        }
        $this->get('/category/poetry/')->assertOk()->assertSee('পরের পাতা →')->assertSee('পাতা ১ / ২')->assertDontSee('Next &raquo;', false)
            ->assertSee('aria-current="page"', false)->assertSee('viewport-fit=cover', false);
        $html = $this->get('/k1/')->getContent();
        $this->assertDoesNotMatchRegularExpression('~›\s*</nav>~u', $html, 'No stray › at the end of the breadcrumbs');
    }

    public function test_poems_with_one_paragraph_per_line_are_set_tight(): void
    {
        // Imported poems (stored as they came from WordPress, without the formatter): one <p> per line.
        $post = $this->makePost(['slug' => 'lines', 'status' => 'published', 'published_at' => now()->subDay()]);
        \Illuminate\Support\Facades\DB::table('posts')->where('id', $post->id)->update(['body_html' => '<p>এক</p><p>দুই</p><p>তিন</p><p>চার</p><p>পাঁচ</p>']);
        $this->assertTrue($post->fresh()->poemLinesAreParagraphs());
        $this->get('/lines/')->assertSee('poem-tight', false);
        $stanzas = $this->makePost(['slug' => 'stanzas', 'body_html' => '<p>এক<br>দুই</p><p>তিন<br>চার</p>']);
        $this->assertFalse($stanzas->poemLinesAreParagraphs());
    }
}
