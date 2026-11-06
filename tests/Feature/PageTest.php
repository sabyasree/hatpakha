<?php

namespace Tests\Feature;

use App\Console\Commands\ImportWordPress;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\WpContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Old WordPress pages keep their URLs (docs/SEO.md §1) and revised copy is well-formed. */
class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_is_served_at_its_old_url_with_meta_and_footer_link(): void
    {
        Page::create(['slug' => 'about-hatpakha', 'title' => 'হাতপাখার কথা', 'body_html' => '<p>আমাদের কথা</p><script>x()</script>', 'show_in_footer' => true]);

        $res = $this->get('/about-hatpakha/')->assertOk()->assertSee('আমাদের কথা')->assertDontSee('x()', false);
        $res->assertSee('<link rel="canonical" href="'.url('/about-hatpakha').'/">', false);
        $res->assertSee('index, follow', false);
        $this->get('/about-hatpakha')->assertRedirect(url('/about-hatpakha').'/');
        $this->get('/')->assertSee(url('/about-hatpakha').'/', false);
    }

    public function test_unreviewed_and_draft_pages(): void
    {
        Page::create(['slug' => 'old-page', 'title' => 'Old', 'body_html' => '<p>x</p>', 'noindex' => true]);
        Page::create(['slug' => 'hidden', 'title' => 'Hidden', 'body_html' => '<p>x</p>', 'status' => 'draft']);

        $this->get('/old-page/')->assertOk()->assertSee('noindex, follow', false);
        $this->get('/hidden/')->assertNotFound();
        $this->get('/sitemap-pages.xml')->assertOk()->assertDontSee('old-page')->assertDontSee('hidden');
    }

    public function test_old_plugin_pages_redirect_home(): void
    {
        Redirect::create(['from_path' => '/login/', 'to_path' => '/', 'status_code' => 301]);
        $this->get('/login/')->assertRedirect(url('/').'/');
    }

    public function test_wordpress_shortcodes_are_converted(): void
    {
        $html = '[vc_row][vc_column][vc_custom_heading text="প্রশ্ন"][vc_toggle title="কীভাবে?"]এভাবে।[/vc_toggle][vc_raw_html]JTNDc2NyaXB0[/vc_raw_html][/vc_column][/vc_row]'
            ."\n".'[caption id="a" width="300"]<img src="http://hatpakha.com/wp-content/uploads/2016/03/a.jpg" alt="a"> পটচিত্র[/caption]'
            ."\n".'[embed]https://www.youtube.com/watch?v=87O20nT45QM&rel=0[/embed]'
            ."\n".'[gallery ids="1,2"] [Bio] লেখক';
        $out = WpContent::convert($html, fn ($ids) => array_map(fn ($i) => ['src' => "/wp-content/uploads/{$i}.jpg", 'alt' => ''], $ids));

        $this->assertStringContainsString('<h2>প্রশ্ন</h2>', $out);
        $this->assertStringContainsString('<h3>কীভাবে?</h3>', $out);
        $this->assertStringContainsString('<figcaption>পটচিত্র</figcaption>', $out);
        $this->assertStringContainsString('src="/wp-content/uploads/2016/03/a.jpg"', $out);
        $this->assertStringContainsString('src="/wp-content/uploads/2.jpg"', $out);
        $this->assertStringContainsString('[Bio] লেখক', $out, 'Ordinary bracketed text is kept.');
        foreach (['[vc_', 'JTNDc2NyaXB0', '[gallery', '[embed', '[caption'] as $gone) {
            $this->assertStringNotContainsString($gone, $out);
        }
    }

    public function test_youtube_link_paragraph_renders_as_nocookie_player_only_for_valid_ids(): void
    {
        $html = '<p><a href="https://www.youtube.com/watch?v=87O20nT45QM&amp;rel=0">x</a></p><p><a href="https://www.youtube.com/watch?v=bad&quot;onload">y</a></p>';
        $out = \App\Support\Embeds::render($html);

        $this->assertSame(1, substr_count($out, '<iframe'));
        // Sanitised links escape "=" as &#61;, and old posts use http:// and the Jetpack shortcode.
        $legacy = \App\Support\HtmlSanitizer::clean(\App\Support\WpContent::convert('[youtube=http://www.youtube.com/watch?v=wsxGrikfm2g&amp;rel=0]'));
        $this->assertStringContainsString('youtube-nocookie.com/embed/wsxGrikfm2g', \App\Support\Embeds::render($legacy));
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/87O20nT45QM', $out);
    }

    public function test_revised_page_files_have_front_matter_and_safe_html(): void
    {
        $files = glob(database_path('content/pages/*.html'));
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $page = ImportWordPress::revisedPage(basename($file, '.html'));
            $this->assertNotEmpty($page['title'] ?? null, basename($file).' needs a title');
            $this->assertNotEmpty($page['seo_description'] ?? null, basename($file).' needs an seo_description');
            $this->assertStringNotContainsString('<script', $page['body']);
            $this->assertDoesNotMatchRegularExpression('/\[[^\]]*(TODO|placeholder|lorem)/i', $page['body'], 'No placeholders on public pages.');
        }
    }
}
