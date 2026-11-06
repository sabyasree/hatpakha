<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\EditorTask;
use App\Models\User;
use App\Support\AudioReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** D67: writers add their own reading; it is checked by content and plays only after the owner approves. */
class AudioReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_uploads_reading_owner_approves_then_readers_hear_it(): void
    {
        $writer = User::factory()->create();
        $author = Author::create(['user_id' => $writer->id, 'slug' => 'w', 'display_name' => 'W']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'kotha', 'published_at' => now()], $author);
        $mp3 = UploadedFile::fake()->createWithContent('reading.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x64".str_repeat("\x00", 413), 20));

        $this->actingAs($writer)->post('/account/audio/'.$post->id.'/', ['audio' => $mp3])->assertRedirect();
        $post->refresh();
        $this->assertSame('pending', $post->audio_status);
        $this->assertStringStartsWith('/media/audio/', $post->audio_path);
        $this->assertSame(1, EditorTask::where('type', 'audio')->count());
        $this->get('/kotha/')->assertDontSee('লেখকের কণ্ঠে শুনুন');

        AudioReading::approve($post);
        $this->get('/kotha/')->assertSee('লেখকের কণ্ঠে শুনুন')->assertSee($post->fresh()->audio_path);
        @unlink(public_path(ltrim($post->audio_path, '/')));
    }

    public function test_only_the_writer_and_only_real_audio(): void
    {
        $writer = User::factory()->create();
        $post = $this->makePost(['status' => 'published', 'slug' => 'k', 'published_at' => now()], Author::create(['user_id' => $writer->id, 'slug' => 'w2', 'display_name' => 'W2']));
        $this->actingAs(User::factory()->create())->post('/account/audio/'.$post->id.'/', ['audio' => UploadedFile::fake()->create('a.mp3', 10)])->assertForbidden();
        $this->actingAs($writer)->post('/account/audio/'.$post->id.'/', ['audio' => UploadedFile::fake()->createWithContent('evil.mp3', '<?php echo 1;')])->assertSessionHasErrors('audio');
        $this->assertNull($post->fresh()->audio_path);
    }
}
