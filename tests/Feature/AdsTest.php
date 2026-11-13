<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D111: AdSense only where it belongs: the live site, allowed page types, never children's or account pages. */
class AdsTest extends TestCase
{
    use RefreshDatabase;

    private function slots(): void
    {
        config(['services.adsense.slots' => ['home' => '1', 'home_2' => '2', 'article' => '3', 'article_2' => '4', 'sidebar' => '5', 'list' => '6']]);
    }

    public function test_ads_txt_names_hatpakhas_publisher(): void
    {
        $this->assertSame("google.com, pub-4627594058272769, DIRECT, f08c47fec0942fa0\n", file_get_contents(public_path('ads.txt')));
    }

    public function test_no_ads_off_the_live_site(): void
    {
        $this->slots();
        config(['app.url' => 'https://new.hatpakha.com']);
        $post = $this->makePost(['slug' => 'a-poem']);
        $this->assertStringNotContainsString('adsbygoogle', $this->get($post->url())->getContent(), 'Staging never shows real ads');
    }

    public function test_piece_page_has_labelled_lazy_spots_after_the_text_and_none_on_the_first_screen(): void
    {
        $this->slots();
        $this->asLiveSite();
        $post = $this->makePost(['slug' => 'a-story', 'format' => 'prose', 'body_html' => '<p>'.str_repeat('গল্পের লাইন। ', 50).'</p>']);
        $html = $this->get($post->url())->assertOk()->getContent();
        $this->assertStringContainsString('ca-pub-4627594058272769', $html);
        $this->assertSame(3, substr_count($html, 'class="hp-ad-pending"'), 'Article, second article and the wide-screen side ad');
        $this->assertStringContainsString('>বিজ্ঞাপন<', $html);
        $this->assertGreaterThan(strpos($html, 'গল্পের লাইন'), strpos($html, 'data-ad-slot="3"'), 'Never before or inside the text');
        $this->assertStringContainsString('min-[1280px]:block', $html, 'The side ad only on laptop-wide screens (D167: from 1280px, narrow)');
        $this->assertStringContainsString('w-[160px]', $html);
        $this->assertStringContainsString('min-[1500px]:w-[300px]', $html);

        $post->forceFill(['no_ads' => true])->save();
        $this->assertStringNotContainsString('adsbygoogle', $this->get($post->url())->getContent(), 'The editor can switch ads off for a piece');
    }

    public function test_long_stories_get_one_ad_near_the_middle_at_a_paragraph_end(): void
    {
        $this->slots();
        config(['services.adsense.slots.article_mid' => '7']);
        $this->asLiveSite();
        $paras = collect(range(1, 40))->map(fn ($i) => '<p>অনুচ্ছেদ '.$i.' '.str_repeat('গল্পের লাইন। ', 25).'</p>')->implode("\n");
        $post = $this->makePost(['slug' => 'long-story', 'format' => 'prose', 'body_html' => $paras]);
        $html = $this->get($post->url())->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-ad-slot="7"'), 'Exactly one mid-story ad');
        $mid = strpos($html, 'data-ad-slot="7"');
        $this->assertTrue(strpos($html, 'অনুচ্ছেদ 15 ') < $mid && $mid < strpos($html, 'অনুচ্ছেদ 26 '), 'Near the middle, never in the first screens');
        $this->assertSame(1, substr_count($html, 'অনুচ্ছেদ 20 '), 'The story itself is cut, not changed: every paragraph once');
        $this->assertMatchesRegularExpression('#</p></div>\s*<aside class="my-8 !mb-2 !mt-10" aria-label="বিজ্ঞাপন">#', $html, 'Between whole paragraphs');
        $this->assertStringContainsString('লেখাটি চলছে', $html, 'Readers are told the story goes on after the ad');

        $short = $this->makePost(['slug' => 'short-story', 'format' => 'prose', 'body_html' => '<p>'.str_repeat('গল্পের লাইন। ', 300).'</p>']);
        $this->assertStringNotContainsString('data-ad-slot="7"', $this->get($short->url())->getContent(), 'Short pieces: none');
        $poem = $this->makePost(['slug' => 'long-poem', 'format' => 'poem', 'body_html' => $paras]);
        $this->assertStringNotContainsString('data-ad-slot="7"', $this->get($poem->url())->getContent(), 'Poems: never');
        $post->forceFill(['no_ads' => true])->save();
        $this->assertStringNotContainsString('data-ad-slot="7"', $this->get($post->url())->getContent(), '"No ads" piece: none');
    }

    public function test_long_lists_get_an_ad_every_ten_and_long_years_one_partway(): void
    {
        $this->slots();
        $this->asLiveSite();
        $author = \App\Models\Author::create(['slug' => 'busy', 'display_name' => 'Busy Writer']);
        foreach (range(1, 24) as $i) {
            $this->makePost(['slug' => 'p'.$i, 'title' => 'লেখা '.$i, 'published_at' => now()->setDate(2016, 1 + intdiv($i - 1, 3), 5)], $author);
        }
        $page = $this->get('/author/busy/')->assertOk()->getContent();
        $this->assertGreaterThanOrEqual(4, substr_count($page, 'data-ad-slot="6"'), 'Phone list every 10, grid every 6, and one at the end');
        $year = $this->get('/archive/2016/')->assertOk()->getContent();
        $this->assertSame(1, substr_count($year, 'data-ad-slot="6"'), 'One ad partway down a long year');
        $this->assertNull(\App\Http\Controllers\ArchiveController::adAfter(collect([1 => 3, 2 => 4])), 'Short years: none');
        $this->assertSame(2, \App\Http\Controllers\ArchiveController::adAfter(collect([1 => 6, 2 => 6, 3 => 6])));
    }

    public function test_home_and_lists_have_ads_but_children_forms_and_info_pages_never(): void
    {
        $this->slots();
        $this->asLiveSite();
        $this->assertStringContainsString('data-ad-slot="1"', $this->get('/')->getContent());

        $kids = Section::where('slug', Section::KIDS)->first();
        $kidsPost = $this->makePost(['slug' => 'kids-rhyme', 'section_id' => $kids->id]);
        foreach ([$kidsPost->url(), '/bornomala/', '/category/chhotoder/', '/login/', '/register/'] as $url) {
            $this->assertStringNotContainsString('adsbygoogle', $this->get($url)->getContent(), $url);
        }
        $this->actingAs(User::factory()->create(['role' => Role::Writer]));
        $this->assertStringNotContainsString('adsbygoogle', $this->get('/account/')->getContent(), 'No ads on the account pages');
    }
}
