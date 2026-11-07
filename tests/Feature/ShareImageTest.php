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

    public function test_picture_only_piece_still_has_a_description(): void
    {
        $writer = Author::create(['slug' => 'piyali', 'display_name' => 'পিয়ালী কর্মকার']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'golap', 'title' => 'গোলাপ', 'published_at' => now(), 'excerpt' => null,
            'body_html' => '<figure><img src="/media/inline/4-1.webp" alt="" width="623" height="687"></figure>'], $writer);
        $this->assertSame('“গোলাপ”, পিয়ালী কর্মকার · কবিতা, হাতপাখা', $post->fresh()->metaDescription());
    }

    /** D93: pictures pasted as base64 in old WordPress pieces come back as safe WebP files, in order. */
    public function test_pasted_pictures_are_recovered_from_the_old_database(): void
    {
        config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        \Illuminate\Support\Facades\Schema::connection('legacy')->create('posts', function ($t) {
            $t->id('ID'); $t->string('post_type'); $t->longText('post_content');
        });
        $img = imagecreatetruecolor(40, 30);
        ob_start(); imagepng($img); $png = base64_encode(ob_get_clean());
        $id = \Illuminate\Support\Facades\DB::connection('legacy')->table('posts')->insertGetId(['post_type' => 'post',
            'post_content' => '<img src="data:image/png;base64,'.$png.'" width="40" height="30" /> <img src="data:image/png;base64,'.$png.'" width="40" height="30" />']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'scan', 'published_at' => now(), 'legacy_wp_id' => $id,
            'body_html' => '<figure><img alt="" width="40" height="30"></figure><figure><img alt="" width="40" height="30"></figure>']);

        $r = \App\Support\InlineImages::recover();
        foreach ([1, 2] as $n) {
            $this->made[] = public_path("media/inline/{$post->id}-{$n}.webp");
        }
        $this->assertSame(['posts' => 1, 'images' => 2, 'skipped' => null], $r);
        $body = $post->fresh()->body_html;
        $this->assertStringContainsString('src="/media/inline/'.$post->id.'-1.webp"', $body);
        $this->assertStringContainsString('src="/media/inline/'.$post->id.'-2.webp"', $body);
        $this->assertSame('image/webp', getimagesize(public_path("media/inline/{$post->id}-1.webp"))['mime']);
        $this->assertSame(['posts' => 0, 'images' => 0, 'skipped' => null], \App\Support\InlineImages::recover(), 'Safe to run again');
    }
}
