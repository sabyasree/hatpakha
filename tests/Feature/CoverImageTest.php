<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** 3f: writer covers are sniffed, re-encoded (no EXIF/GPS, no payload), randomly named, removable. */
class CoverImageTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(int $w = 1200, int $h = 800, string $extra = ''): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 203, 27, 50));
        ob_start();
        imagejpeg($img);
        $bytes = ob_get_clean();
        // Fake APP1/EXIF-style segment with a GPS-looking string and a PHP payload right after SOI.
        $payload = "Exif\0\0GPSLatitude 22.57 <?php system(\$_GET['c']); ?>".$extra;
        $seg = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        $bytes = substr($bytes, 0, 2).$seg.substr($bytes, 2);
        $path = tempnam(sys_get_temp_dir(), 'cov');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }

    private function submit(User $user, array $extra = [], ?Post $post = null)
    {
        $section = Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'format' => 'poem']);

        return $this->actingAs($user)->post($post ? "/submit/{$post->id}" : '/submit', $extra + [
            'title' => 'নদীর গান', 'body' => str_repeat('নদী বয়ে যায় দূরে দূরে। ', 5), 'section_id' => $section->id, 'action' => 'draft',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('media/covers/*/*/*')) ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    public function test_cover_is_re_encoded_without_metadata_and_gets_webp_sizes(): void
    {
        $user = User::factory()->create();
        $this->submit($user, ['cover' => $this->jpeg()])->assertSessionHasNoErrors();
        $post = Post::sole();
        $this->assertMatchesRegularExpression('#^media/covers/\d{4}/\d{2}/[a-z0-9]{24}\.jpg$#', $post->featured_image_path);
        $this->assertSame(1200, $post->featured_image_width);
        $file = public_path($post->featured_image_path);
        $bytes = file_get_contents($file);
        $this->assertStringNotContainsString('GPSLatitude', $bytes);
        $this->assertStringNotContainsString('<?php', $bytes);
        $this->assertFileExists(preg_replace('/\.jpg$/', '.w960.webp', $file));
        $this->assertFileExists(public_path('media/.htaccess'));
        $this->assertStringContainsString('php_flag engine off', file_get_contents(public_path('media/.htaccess')));
    }

    public function test_non_images_and_tiny_images_are_refused(): void
    {
        $user = User::factory()->create();
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo 1; ?>');
        $this->submit($user, ['cover' => $fake])->assertSessionHasErrors('cover');
        $this->submit($user, ['cover' => $this->jpeg(400, 300)])->assertSessionHasErrors('cover');
        $this->assertNull(Post::first()?->featured_image_path);
    }

    public function test_writer_can_replace_and_remove_the_cover_and_old_files_go(): void
    {
        $user = User::factory()->create();
        $this->submit($user, ['cover' => $this->jpeg()]);
        $post = Post::sole();
        $first = public_path($post->featured_image_path);
        $this->submit($user, ['cover' => $this->jpeg(1600, 900)], $post);
        $this->assertFileDoesNotExist($first);
        $this->assertSame(1600, $post->fresh()->featured_image_width);
        $this->submit($user, ['remove_cover' => '1'], $post->fresh());
        $this->assertNull($post->fresh()->featured_image_path);
    }

    public function test_covers_saved_under_storage_are_moved_to_a_real_public_folder(): void
    {
        $post = $this->makePost(['slug' => 'old-cover']);
        @mkdir(storage_path('app/public/covers/2026/11'), 0755, true);
        copy($this->jpeg()->getRealPath(), storage_path('app/public/covers/2026/11/abcdefabcdefabcdefabcdef.jpg'));
        $post->forceFill(['proposed_image_path' => 'storage/covers/2026/11/abcdefabcdefabcdefabcdef.jpg'])->save();
        $this->artisan('hatpakha:move-covers')->assertSuccessful();
        $this->assertSame('media/covers/2026/11/abcdefabcdefabcdefabcdef.jpg', $post->fresh()->proposed_image_path);
        $this->assertFileExists(public_path('media/covers/2026/11/abcdefabcdefabcdefabcdef.jpg'));
        $this->assertFileDoesNotExist(storage_path('app/public/covers/2026/11/abcdefabcdefabcdefabcdef.jpg'));
    }
}
