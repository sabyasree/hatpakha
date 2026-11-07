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

    /** D70: "Drop" and "Use this" can both be undone. */
    public function test_dropped_and_replaced_covers_can_be_brought_back(): void
    {
        $post = $this->makePost(['slug' => 'undo-cover']);
        $file = fn () => \App\Support\CoverImage::store($this->jpeg()->getRealPath());
        $a = $file();
        $post->forceFill(['featured_image_path' => $a['path'], 'featured_image_width' => $a['width'], 'featured_image_height' => $a['height']])->save();
        $b = $file();
        $post->forceFill(['proposed_image_path' => $b['path'], 'proposed_image_width' => $b['width'], 'proposed_image_height' => $b['height']])->save();

        \App\Support\CoverImage::discardProposed($post);
        $this->assertNull($post->fresh()->proposed_image_path);
        $this->assertFileExists(public_path($b['path']), 'A dropped drawing is kept');
        $this->assertTrue(\App\Support\CoverImage::restoreSpare($post->fresh()));
        $this->assertSame($b['path'], $post->fresh()->proposed_image_path, 'Drop undone');

        \App\Support\CoverImage::acceptProposed($post->fresh());
        $this->assertSame($b['path'], $post->fresh()->featured_image_path);
        $this->assertFileExists(public_path($a['path']), 'The replaced cover is kept');
        \App\Support\CoverImage::restoreSpare($post->fresh());
        $this->assertSame($a['path'], $post->fresh()->featured_image_path, 'Back to the previous cover');
        $this->assertSame($b['path'], \App\Support\CoverImage::spare($post->fresh())['path'], 'And it can go forward again');
    }

    public function test_every_cover_shows_on_the_piece_page_at_no_more_than_its_own_size(): void
    {
        // Owner (2026-11-07): covers under 1000px were hidden on 766 pieces.
        foreach ([960 => true, 450 => true] as $width => $_) {
            $post = $this->makePost(['slug' => 'cover-'.$width, 'format' => 'prose', 'featured_image_path' => 'wp-content/uploads/x-'.$width.'.jpg',
                'featured_image_width' => $width, 'featured_image_height' => 600]);
            $this->get($post->url())->assertOk()->assertSee('x-'.$width.'.jpg', false)->assertSee('max-w-full', false);
        }
        $this->assertTrue($this->makePost(['slug' => 'c600', 'featured_image_path' => 'a.jpg', 'featured_image_width' => 600])->hasCardImage());
        $this->assertFalse($this->makePost(['slug' => 'c450', 'featured_image_path' => 'b.jpg', 'featured_image_width' => 450])->hasLargeImage(), 'Too small for a sharp share card');
    }

    public function test_editor_replaces_a_cover_and_can_put_the_old_one_back(): void
    {
        // Owner (2026-11-07): "need option to replace the cover".
        $post = $this->makePost(['slug' => 'replace-cover', 'featured_image_path' => 'wp-content/uploads/old-cover.jpg', 'featured_image_width' => 960, 'featured_image_height' => 720]);
        \App\Support\CoverImage::replaceByEditor($post, $this->jpeg()->getRealPath());
        $post->refresh();
        $this->assertStringStartsWith('media/covers/', $post->featured_image_path);
        $this->assertFalse($post->image_is_ai);
        $this->assertSame('wp-content/uploads/old-cover.jpg', \App\Support\CoverImage::spare($post)['path'], 'The old WordPress cover is kept, never deleted');
        \App\Support\CoverImage::restoreSpare($post);
        $this->assertSame('wp-content/uploads/old-cover.jpg', $post->fresh()->featured_image_path);

        $this->actingAs(User::factory()->create(['role' => \App\Enums\Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->getRouteKey()])->assertSee('ছবি বদলান');
    }
}
