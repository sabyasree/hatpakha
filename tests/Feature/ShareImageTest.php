<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Support\ShareImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D88: what a shared link shows on Facebook, WhatsApp, X… */
class ShareImageTest extends TestCase
{
    use RefreshDatabase;

    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function test_piece_without_a_big_photo_uses_its_section_card_and_a_clean_preview(): void
    {
        $writer = Author::create(['slug' => 'rina', 'display_name' => 'রিনা আচার্য']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'megh', 'title' => 'মেঘ', 'published_at' => now(), 'excerpt' => null,
            'body_html' => '<p>ভোর না হতেই কাজে,<br>সারাটা দিন কর্মক্ষেত্রে</p><p>নতুন স্তবক</p>'], $writer);
        $html = $this->get($post->url())->assertOk()->getContent();
        $this->assertStringContainsString('<meta property="og:image" content="'.asset('images/og/card-poetry.jpg').'">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="মেঘ | রিনা আচার্য">', $html);
        $this->assertStringContainsString('ভোর না হতেই কাজে, / সারাটা দিন কর্মক্ষেত্রে নতুন স্তবক', $html, 'Poem lines are never glued together');
        $post->forceFill(['seo_title' => 'মেঘ | রিনা'])->save();
        $this->assertSame('মেঘ | রিনা', $post->fresh()->shareTitle(), 'A title that already names the writer is not doubled');
        $this->assertFileExists(public_path('images/og/card-poetry.jpg'));
        $this->assertSame([1200, 630], array_slice(getimagesize(public_path('images/og/card-poetry.jpg')), 0, 2));
    }

    public function test_big_cover_becomes_a_small_1200x630_card_with_the_overlay(): void
    {
        @mkdir(public_path('media/covers'), 0755, true);
        $src = public_path('media/covers/test-share-'.uniqid().'.jpg');
        $img = imagecreatetruecolor(1600, 1200);
        imagefill($img, 0, 0, imagecolorallocate($img, 45, 170, 226));
        imagejpeg($img, $src);
        $this->made[] = $src;
        $post = $this->makePost(['status' => 'published', 'slug' => 'chhobi', 'published_at' => now(),
            'featured_image_path' => '/'.str_replace(public_path().'/', '', $src), 'featured_image_width' => 1600]);

        $url = ShareImage::forPost($post);
        $file = public_path(str_replace(asset(''), '', $url));
        $this->made[] = $file;
        $this->assertStringContainsString('/media/og/'.$post->id.'-', $url);
        $this->assertSame([1200, 630], array_slice(getimagesize($file), 0, 2));
        $this->assertLessThan(300 * 1024, filesize($file), 'Small enough for WhatsApp previews');
        $this->assertSame($url, ShareImage::forPost($post), 'Made once, then reused');
    }

    public function test_staging_lets_link_previews_in_but_not_search_engines(): void
    {
        config(['seo.indexable' => false]);
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString("User-agent: facebookexternalhit\nAllow: /", $robots);
        $this->assertStringContainsString("User-agent: WhatsApp\nAllow: /", $robots);
        $this->assertStringEndsWith("User-agent: *\nDisallow: /\n", $robots);
    }
}
