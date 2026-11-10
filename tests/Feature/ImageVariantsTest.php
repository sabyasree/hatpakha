<?php

namespace Tests\Feature;

use App\Support\Images;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImageVariantsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = public_path('wp-content/uploads/test-'.uniqid());
        File::makeDirectory($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_command_makes_webp_sizes_and_pages_serve_them(): void
    {
        $jpg = imagecreatetruecolor(1200, 800);
        imagejpeg($jpg, $this->dir.'/photo.jpg', 90);
        $png = imagecreatetruecolor(300, 200);
        imagesavealpha($png, true);
        imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
        imagepng($png, $this->dir.'/logo.png');
        $rel = 'wp-content/uploads/'.basename($this->dir);

        $post = $this->makePost(['slug' => 'with-images', 'featured_image_path' => "{$rel}/photo.jpg", 'featured_image_width' => 1200, 'featured_image_height' => 800,
            'body_html' => "<p>লেখা</p><figure><img src=\"/{$rel}/logo.png\" alt=\"লোগো\" width=\"300\" height=\"200\"></figure>"]);

        $this->artisan('hatpakha:images')->assertSuccessful();

        $this->assertFileExists($this->dir.'/photo.w480.webp');
        $this->assertFileExists($this->dir.'/photo.w960.webp');
        $this->assertFileExists($this->dir.'/photo.w1440.webp'); // original width, under the top name
        $this->assertSame(480, getimagesize($this->dir.'/photo.w480.webp')[0]);
        $this->assertFileExists($this->dir.'/logo.w480.webp');   // small image: one copy at its own size
        $this->assertFileDoesNotExist($this->dir.'/logo.w960.webp');

        $res = $this->get('/with-images/')->assertOk();
        $res->assertSee('<source type="image/webp" srcset="/'.$rel.'/logo.w480.webp 480w"', false);
        $res->assertSee('loading="lazy"', false);
        $res->assertSee('photo.w960.webp 960w', false);

        // Re-running skips everything already made.
        $this->artisan('hatpakha:images')->expectsOutputToContain('images used')->assertSuccessful();
    }

    public function test_body_images_are_rebuilt_safely(): void
    {
        $out = Images::renderBody('<img src="/x.jpg" alt="a&quot; onerror=&quot;alert(1)" width="10" height="10" onload="x()">');
        $this->assertStringNotContainsString('onload', $out);
        $this->assertStringNotContainsString('" onerror="', $out);
        $this->assertStringContainsString('loading="lazy"', $out);
        $this->assertNull(Images::relative('/wp-content/uploads/../../.env.jpg'));
        $this->assertNull(Images::relative('https://evil.example/wp-content/uploads/x.php'));
    }
}
