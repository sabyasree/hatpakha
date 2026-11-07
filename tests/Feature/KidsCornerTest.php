<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\KidsAlphabet;
use App\Ai\KidsTeacher;
use App\Ai\Narrator;
use App\Enums\Role;
use App\Filament\Pages\KidsCorner;
use App\Models\Comment;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\Section;
use App\Models\User;
use App\Support\KidsClassics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** D101: ছোটদের হাতপাখা. The AI teacher, narrator and designer prepare; nothing shows until the owner approves. */
class KidsCornerTest extends TestCase
{
    use RefreshDatabase;

    private Section $kids;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => 'o', 'ai.openai.base_url' => 'https://api.openai.test/v1',
            'ai.google_tts.key' => 'g', 'ai.google_tts.url' => 'https://tts.google.test/v1/text:synthesize']);
        $this->kids = Section::where('slug', Section::KIDS)->firstOrFail();   // made by the migration
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        foreach ($this->files as $f) {
            @unlink(public_path(ltrim($f, '/')));
        }
        parent::tearDown();
    }

    private function fakeVoices(): void
    {
        Http::fake([
            'api.openai.test/*' => Http::response('ID3fake-mp3', 200),
            'tts.google.test/*' => Http::response(['audioContent' => base64_encode('ID3google-mp3')], 200),
        ]);
    }

    private function kidsPost(array $attrs = []): Post
    {
        return $this->makePost(array_merge(['section_id' => $this->kids->id, 'title' => 'খিচুড়ি', 'slug' => 'khichuri-test',
            'body_html' => '<p>হাঁস ছিল, সজারু, (ব্যাকরণ মানি না),<br>হয়ে গেল “হাঁসজারু” কেমনে তা জানি না।</p>'], $attrs));
    }

    public function test_section_menu_and_alphabet_page_exist(): void
    {
        $this->assertSame('ছোটদের হাতপাখা', $this->kids->name_bn);
        $this->get('/category/chhotoder/')->assertOk()->assertSee('সব বয়স')->assertSee('৬–৮ বছর')->assertSee('/bornomala/', false);
        $this->get('/bornomala/')->assertOk()->assertSee('স্বরবর্ণ')->assertSee('ব্যঞ্জনবর্ণ')->assertSee('অজগর')
            ->assertDontSee('data-say', false)->assertDontSee('যন্ত্রকণ্ঠ');
    }

    public function test_children_pieces_have_age_glossary_no_comments_and_no_ads(): void
    {
        config(['services.adsense.client' => 'ca-pub-1', 'services.adsense.slot_article' => '123']);
        $post = $this->kidsPost(['age_band' => '6-8', 'glossary' => [['word' => 'সজারু', 'bn' => 'কাঁটাওয়ালা প্রাণী', 'en' => 'porcupine'], ['word' => '', 'bn' => 'x']]]);
        $html = $this->get($post->url())->assertOk()->getContent();
        $this->assertStringContainsString('বয়স ৬–৮ বছর', $html);
        $this->assertStringContainsString('শব্দের মানে', $html);
        $this->assertStringContainsString('porcupine', $html);
        $this->assertStringNotContainsString('id="comments"', $html);
        $this->assertStringNotContainsString('adsbygoogle', $html, 'No ads, not even the AdSense script');

        $this->post(route('comment.store', $post), ['name' => 'পাঠক', 'body' => 'বাহ বেশ'])->assertNotFound();
        $this->assertSame(0, Comment::count());

        $other = $this->makePost(['slug' => 'grown-up']);
        $this->assertStringContainsString('adsbygoogle', $this->get($other->url())->getContent(), 'Other pages keep their ads');
    }

    public function test_age_filter_narrows_and_stays_out_of_google(): void
    {
        $this->kidsPost(['age_band' => '3-5', 'title' => 'ছোট্ট ছড়া', 'slug' => 'a1']);
        $this->kidsPost(['age_band' => '9-12', 'title' => 'বড়দের মতো গল্প', 'slug' => 'a2']);
        $this->get('/category/chhotoder/?age=3-5')->assertOk()->assertSee('ছোট্ট ছড়া')->assertDontSee('বড়দের মতো গল্প')->assertSee('noindex', false);
        $this->get('/category/chhotoder/?age=bad')->assertOk()->assertSee('বড়দের মতো গল্প');
    }

    public function test_ai_voice_is_always_labelled(): void
    {
        $post = $this->kidsPost(['audio_path' => '/media/audio/x.mp3', 'audio_status' => 'approved', 'audio_ai' => true]);
        $this->get($post->url())->assertSee('কণ্ঠ: হাতপাখার যন্ত্রকণ্ঠ</p>', false)->assertDontSee('লেখকের কণ্ঠে শুনুন');
    }

    public function test_teacher_sets_age_and_only_real_words(): void
    {
        $post = $this->kidsPost(['status' => 'draft', 'published_at' => null]);
        Claude::$fake = fn () => ['age' => '6-8', 'suitable' => false, 'note' => 'একটু ভয়ের',
            'words' => [['word' => 'সজারু', 'bn' => 'কাঁটাওয়ালা প্রাণী', 'en' => 'porcupine'], ['word' => 'অজানা শব্দ', 'bn' => 'নেই', 'en' => 'none']]];

        $this->assertNull(KidsTeacher::prepare($post));
        $post->refresh();
        $this->assertSame('6-8', $post->age_band);
        $this->assertSame(['সজারু'], array_column($post->glossary, 'word'), 'Words not in the piece are dropped');
        $this->assertSame('draft', $post->status, 'The teacher never publishes');
        $this->assertTrue(EditorTask::open('alert')->exists(), 'Unsuitable pieces are flagged to the owner');
    }

    public function test_narrator_makes_a_pending_labelled_reading_and_keeps_writer_audio(): void
    {
        $this->fakeVoices();
        $post = $this->kidsPost(['status' => 'draft', 'published_at' => null]);
        $this->assertNull(Narrator::forPost($post));
        $post->refresh();
        $this->files[] = $post->audio_path;
        $this->assertTrue($post->audio_ai);
        $this->assertSame('pending', $post->audio_status, 'The owner approves the reading');
        $this->assertFileExists(public_path(ltrim($post->audio_path, '/')));
        $this->assertSame('bn-IN-Standard-A', $post->audio_voice, "The owner's choice of Bengali voice (D103) is the default");
        $this->assertSame('ID3google-mp3', file_get_contents(public_path(ltrim($post->audio_path, '/'))));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'tts.google.test') && $r->hasHeader('X-Goog-Api-Key', 'g') && ! str_contains($r->url(), 'key=')
            && $r['voice']['name'] === 'bn-IN-Standard-A' && $r['voice']['languageCode'] === 'bn-IN' && str_contains($r['input']['text'], 'হাঁসজারু'));

        $own = $this->kidsPost(['slug' => 'own', 'audio_path' => '/media/audio/writer.mp3', 'audio_status' => 'approved', 'audio_ai' => false]);
        $this->assertNotNull(Narrator::forPost($own));
        $this->assertSame('/media/audio/writer.mp3', $own->fresh()->audio_path, "A writer's own reading is never replaced");
    }

    public function test_alphabet_shows_only_what_the_owner_approved(): void
    {
        $this->fakeVoices();
        $this->assertSame(count(KidsAlphabet::letters()), Narrator::alphabet());
        $state = KidsAlphabet::state();
        $this->files = array_column($state, 'audio');
        $this->assertTrue(EditorTask::open('kids_alphabet')->exists());
        $this->get('/bornomala/')->assertDontSee('data-say', false);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(KidsCorner::class)->assertOk()->assertSee('অজগর')->call('decide', 0, 'audio', true)->call('decide', 1, 'audio', false);
        $this->assertFileDoesNotExist(public_path(ltrim($state[1]['audio'], '/')), 'Removed recordings are deleted so they can be made again');

        auth()->logout();
        $page = collect(KidsAlphabet::forPage());
        $this->assertNotNull($page[0]['audio']);
        $this->assertNull($page[1]['audio']);
        $this->assertNull($page[2]['audio']);
        $this->get('/bornomala/')->assertSee('data-say="'.$state[0]['audio'].'"', false)->assertSee('হাতপাখার যন্ত্রকণ্ঠ');

        Livewire::actingAs($admin)->test(KidsCorner::class)->call('approveAll', 'audio');
        $this->assertFalse(EditorTask::open('kids_alphabet')->exists() && collect(KidsAlphabet::state())->contains(fn ($s) => ! empty($s['audio']) && empty($s['audio_ok'])));
    }

    public function test_editors_cannot_open_the_kids_desk(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(KidsCorner::canAccess());
    }

    public function test_classics_import_as_drafts_with_their_writers_and_is_idempotent(): void
    {
        $file = base_path('database/content/kids-classics.json');
        $n = count(json_decode(file_get_contents($file), true));
        $this->assertSame($n, KidsClassics::import($file));
        $this->assertSame(0, KidsClassics::import($file), 'Running again adds nothing');

        $p = Post::with('authors')->where('title', 'খিচুড়ি')->firstOrFail();
        $this->assertSame('draft', $p->status, 'Classics wait for the owner to check them against the source');
        $this->assertSame($this->kids->id, $p->section_id);
        $this->assertSame('সুকুমার রায়', $p->authors->first()->display_name);
        $this->assertStringContainsString('wikisource.org', (string) $p->ai_note);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $p->slug);
    }

    public function test_owner_picks_voices_by_ear_and_can_redo_old_recordings(): void
    {
        $this->fakeVoices();
        $this->assertSame(2 * count(Narrator::voices()), Narrator::makeSamples());
        $samples = Narrator::samples();
        $this->files = array_merge(array_values($samples['alphabet']), array_values($samples['stories']));
        $this->assertSame(0, Narrator::makeSamples(), 'Samples are made once');

        $post = $this->kidsPost(['status' => 'draft', 'published_at' => null]);
        Narrator::forPost($post);
        $this->files[] = $post->fresh()->audio_path;
        $this->assertSame('bn-IN-Standard-A', $post->fresh()->audio_voice);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(KidsCorner::class)->assertSee('কণ্ঠ বাছাই')->assertSee($samples['stories']['nova'], false)
            ->call('chooseVoice', 'stories', 'nova')->call('chooseVoice', 'alphabet', 'sage');
        $this->assertSame('nova', Narrator::voice('stories'));
        $this->assertSame('sage', Narrator::voice('alphabet'));
        $this->assertSame(['alphabet' => 0, 'stories' => 1], Narrator::outdated());

        $this->fakeVoices();
        Narrator::revoice();
        $post->refresh();
        $this->files[] = $post->audio_path;
        $this->files = array_merge($this->files, array_column(KidsAlphabet::state(), 'audio'));
        $this->assertSame('nova', $post->audio_voice);
        $this->assertSame('pending', $post->audio_status, 'A redone reading waits for approval again');
        Http::assertSent(fn ($r) => ($r['voice'] ?? null) === 'nova');
        Http::assertSent(fn ($r) => ($r['voice'] ?? null) === 'sage' && str_contains($r['input'], 'অজগর'));
        $this->assertSame(['alphabet' => 0, 'stories' => 0], Narrator::outdated());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        Narrator::setVoice('stories', 'not-a-voice');
    }

    public function test_long_text_is_split_within_googles_limit_and_missing_key_is_clear(): void
    {
        $this->fakeVoices();
        $long = str_repeat('একটি বেশ লম্বা বাক্য যা বারবার আসে আর থামে না। ', 120);   // one long paragraph
        [$mp3, $err] = Narrator::speak($long, null, 'bn-IN-Standard-A');
        $this->assertNull($err);
        $this->assertGreaterThan(1, count(Http::recorded()), 'Read in several parts');
        Http::assertNotSent(fn ($r) => strlen($r['input']['text'] ?? '') > 5000);

        config(['ai.google_tts.key' => null]);
        [, $err] = Narrator::speak('অ।', null, 'bn-IN-Standard-A');
        $this->assertStringContainsString('GOOGLE_TTS_KEY', $err);
    }
}
