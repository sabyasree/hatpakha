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
        config(['app.url' => 'https://hatpakha.com']);
        $post = $this->makePost(['slug' => 'a-story', 'format' => 'prose', 'body_html' => '<p>'.str_repeat('গল্পের লাইন। ', 50).'</p>']);
        $html = $this->get($post->url())->assertOk()->getContent();
        $this->assertStringContainsString('ca-pub-4627594058272769', $html);
        $this->assertSame(3, substr_count($html, 'class="hp-ad-pending"'), 'Article, second article and the wide-screen side ad');
        $this->assertStringContainsString('>বিজ্ঞাপন<', $html);
        $this->assertGreaterThan(strpos($html, 'গল্পের লাইন'), strpos($html, 'data-ad-slot="3"'), 'Never before or inside the text');
        $this->assertStringContainsString('min-[1440px]:block', $html, 'The side ad only on wide screens');

        $post->forceFill(['no_ads' => true])->save();
        $this->assertStringNotContainsString('adsbygoogle', $this->get($post->url())->getContent(), 'The editor can switch ads off for a piece');
    }

    public function test_home_and_lists_have_ads_but_children_forms_and_info_pages_never(): void
    {
        $this->slots();
        config(['app.url' => 'https://hatpakha.com']);
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
