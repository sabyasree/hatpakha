<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\LearnRecordings;
use App\Http\Controllers\LearnController;
use App\Models\LearnAudio;
use App\Models\User;
use App\Support\LearnCourse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * D317/D321/D322: বাংলা শিখি. The course only uses what it has taught; parent pages are indexed and may carry ads; the
 * children's screens and print pages have neither ads nor analytics and are not indexed; worksheets as PDF with a
 * download count that keeps nothing about the person; human recordings uploaded at the desk; English for non-readers.
 */
class LearnBengaliTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_word_uses_only_what_has_been_taught(): void
    {
        $this->assertSame([], LearnCourse::problems());
        $this->assertSame(['ম'], LearnCourse::unknown('মা', ['া']), 'the check really finds what is missing');
        $this->assertSame(['ি'], LearnCourse::unknown('দিদি', ['দ']));
        $this->assertSame([], LearnCourse::unknown('প্রজাপতি', ['প', 'জ', 'ত', 'া', 'ি', '্র']), 'a ro-phola cluster is read once ্র is taught');
        $this->assertSame(['প্র'], LearnCourse::unknown('প্রথম', ['প', 'থ', 'ম']));
        $this->assertSame([], LearnCourse::unknown('সূর্য', ['স', 'ূ', 'য', 'র্']), 'ref + the letter');
        $this->assertSame([], LearnCourse::unknown('চন্দ্র', ['চ', 'ন্দ', '্র']), 'a joined letter + a phola');
        $this->assertSame(['প্র', 'জা', 'প', 'তি'], LearnCourse::tiles('প্রজাপতি'));
        $this->assertSame(['ল', 'ক্ষ্মী'], LearnCourse::tiles('লক্ষ্মী'));
        $this->assertSame(['হ', 'ঠা', 'ৎ'], LearnCourse::tiles('হঠাৎ'));
        $this->assertGreaterThanOrEqual(60, LearnCourse::count());
    }

    public function test_every_lesson_is_complete(): void
    {
        foreach (LearnCourse::all() as [$slug, $n, $id]) {
            $l = LearnCourse::lesson($slug, $n);
            $this->assertTrue(! empty($l['words']) || ! empty($l['sentences']) || isset($l['story']), "$id has something to read");
            foreach ((array) ($l['words'] ?? []) as $w) {
                $this->assertCount(4, $w, "$id: {$w[0]}");
                $this->assertNotSame('', $w[1].$w[2], "$id: {$w[0]} has a reading and a meaning");
                $this->assertNotContains($w[1], ['mom'], 'no example that reads as an English word');
            }
            if (isset($l['story'])) {
                $this->assertArrayHasKey($l['story']['q'][2], $l['story']['q'][1]);
            }
        }
        foreach (LearnCourse::units() as $u) {
            $this->assertNotSame('', $u['tip']['bn'].$u['tip']['en'].$u['intro']);
        }
        $all = LearnController::lessons();
        foreach ($all['juktakkhor']['rows'] as [$c, $parts, $w]) {
            $this->assertStringContainsString($c, $w[0], "{$w[0]} has {$c}");
            $this->assertSame(\Normalizer::normalize(str_replace([' + ', ' '], ['্', ''], $parts)), \Normalizer::normalize($c), "{$c} is {$parts}");
        }
        $en = collect(LearnCourse::data()['sounds_en'])->flatMap(fn ($g) => $g);
        foreach (\App\Ai\KidsAlphabet::letters() as $l) {
            if (! in_array($l[0], ['ঞ'], true) || true) {
                $this->assertTrue($en->has($l[0]) || in_array($l[0], ['ঞ'], true), "English sound guide has {$l[0]}");
            }
        }
    }

    public function test_the_pages_open_with_the_right_indexing(): void
    {
        $this->get('/janun/learn-bengali/')->assertOk()->assertSee('বাংলা শিখি')->assertSee('"Course"', false)->assertSee('/janun/learn-bengali/start/', false)
            ->assertSee('/janun/learn-bengali/english/', false)->assertSee('index, follow', false);
        $this->get('/janun/learn-bengali/kar-1/')->assertOk()->assertSee('ই-কার')->assertSee('/janun/learn-bengali/pdf/kar-1-1-letter/', false)->assertSee('index, follow', false);
        $this->get('/janun/learn-bengali/english/')->assertOk()->assertSee('<html lang="en"', false)->assertSee('How each letter sounds')->assertSee('?ui=en', false);
        $this->get('/janun/learn-bengali/parents/')->assertOk()->assertSee('data-learn-parents', false);
        $this->get('/janun/learn-bengali/worksheets/')->assertOk()->assertSee('/janun/learn-bengali/pdf/chart-alphabet-a4/', false);
        $this->get('/janun/learn-bengali/kar/')->assertOk()->assertSee('আ-কার')->assertSee('data-barokhori', false);
        $this->get('/janun/learn-bengali/first-words/')->assertOk()->assertSee('অনেক মুসলমান পরিবারে পানি');
        foreach (['/janun/learn-bengali/start/', '/janun/learn-bengali/first-letters/1/', '/janun/learn-bengali/stories/1/', '/janun/learn-bengali/certificate/',
            '/janun/learn-bengali/first-letters/1/print/', '/janun/learn-bengali/first-letters/print/', '/janun/learn-bengali/charts/kar/print/'] as $u) {
            $this->get($u)->assertOk()->assertSee('noindex', false);
        }
        $this->get('/janun/learn-bengali/first-letters/1/print/?size=letter')->assertSee('size: letter', false);
        $this->get('/janun/learn-bengali/first-letters/print/')->assertSee('শিক্ষকের সেট')->assertSee('উত্তর');
        $this->get('/janun/learn-bengali/first-letters/9/')->assertNotFound();
        $this->get('/janun/learn-bengali/nothing/')->assertNotFound();
        $this->assertStringEndsWith('/janun/learn-bengali/kar/', (string) $this->get('/janun/learn-bengali/kar')->assertStatus(301)->headers->get('Location'));
        $map = $this->get('/sitemap-pages.xml')->getContent();
        $this->assertStringContainsString('/janun/learn-bengali/juktakkhor-1/', $map);
        $this->assertStringContainsString('/janun/learn-bengali/english/', $map);
        $this->assertStringNotContainsString('/janun/learn-bengali/first-letters/1/', $map, 'lessons are for children, not for search');
    }

    public function test_the_lesson_player_gets_its_data(): void
    {
        $d = LearnController::lessonData('kar-1', 1);
        $this->assertSame('sign', $d['new'][0]['kind']);
        $this->assertSame('দিদি', $d['new'][0]['ex']['w']);
        $this->assertSame(['দি', 'দি'], $d['words'][0]['tiles']);
        $this->assertNotEmpty($d['pool'], 'earlier words for wrong answers and review');
        $this->assertContains('া', $d['signs']);
        $this->assertNull($d['sticker']);
        $last = LearnController::lessonData('first-letters', 6);
        $this->assertSame(['🐟', 'ইলিশ'], $last['sticker']);
        $this->assertStringContainsString('/certificate/?unit=first-letters', $last['urls']['certificate']);
        $this->assertSame('joined', LearnController::lessonData('juktakkhor-1', 1)['new'][0]['kind']);
        $this->assertSame(['ন', 'ত'], LearnController::lessonData('juktakkhor-1', 1)['new'][0]['parts']);
        $this->assertStringStartsWith('Like ', LearnController::lessonData('first-letters', 1)['new'][0]['hint'], 'English sound for non-readers');
        $this->get('/janun/learn-bengali/first-letters/1/')->assertSee('id="learn-data"', false)->assertSee('data-ui', false)->assertSee('data-en="Grown-ups"', false);
    }

    public function test_ads_only_on_parent_pages_and_never_analytics_where_children_play(): void
    {
        config(['app.url' => 'https://hatpakha.com', 'services.adsense.client' => 'ca-pub-1234567890123456', 'services.google.analytics_id' => 'G-TEST1234']);
        $this->get('https://hatpakha.com/janun/learn-bengali/')->assertOk()->assertSee('googlesyndication', false);
        $this->get('https://hatpakha.com/janun/learn-bengali/english/')->assertOk()->assertSee('googlesyndication', false);
        foreach (['start/', 'first-letters/1/', 'certificate/', 'first-letters/1/print/'] as $u) {
            $this->get('https://hatpakha.com/janun/learn-bengali/'.$u)->assertOk()->assertDontSee('googlesyndication', false)->assertDontSee('googletagmanager', false);
        }
        $this->get('https://hatpakha.com/bornomala/')->assertDontSee('googlesyndication', false);
    }

    public function test_a_download_is_counted_without_anything_about_the_person(): void
    {
        $this->get('/janun/learn-bengali/pdf/first-letters-1-a4/')->assertRedirect();
        $this->get('/janun/learn-bengali/pdf/first-letters-1-a4/');
        $this->assertSame(2, (int) DB::table('learn_downloads')->where('file', 'first-letters-1-a4')->value('count'));
        $this->assertSame(['id', 'day', 'file', 'count'], array_keys((array) DB::table('learn_downloads')->first()));
        @mkdir(public_path('pdf/learn'), 0755, true);
        file_put_contents(public_path('pdf/learn/kar-1-1-letter.pdf'), '%PDF-1.4 test');
        try {
            $this->get('/janun/learn-bengali/pdf/kar-1-1-letter/')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        } finally {
            @unlink(public_path('pdf/learn/kar-1-1-letter.pdf'));
        }
        $this->get('/janun/learn-bengali/pdf/not-a-file/')->assertNotFound();
        $this->assertStringEndsWith('/janun/learn-bengali/kar-1/2/print/?size=letter', (string) $this->get('/janun/learn-bengali/pdf/kar-1-2-letter/')->headers->get('Location'), 'Letter stays Letter, no redirect chain');
        $this->assertStringEndsWith('/a/print/?size=letter', \App\Support\Url::to('/a/print?size=letter'));
        $this->assertCount(2 * (LearnCourse::count() + count(LearnCourse::units()) + 2), LearnController::pdfs());
    }

    public function test_recordings_are_uploaded_at_the_desk_and_play_in_the_lesson(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $data = str_repeat("\x00", 800);   // a real (silent) WAV file: the type is read from the bytes
        $mp3 = UploadedFile::fake()->createWithContent('ma.wav', 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8).'data'.pack('V', strlen($data)).$data);
        $key = LearnRecordings::key('মা');
        Livewire::test(LearnRecordings::class)->assertSee('মামা')->set("files.$key", $mp3)->assertHasNoErrors();
        $a = LearnAudio::where('text', 'মা')->first();
        $this->assertNotNull($a);
        $this->assertFileExists(public_path($a->path));
        $this->assertStringStartsWith('media/learn-audio/', $a->path);
        $this->assertStringEndsWith('.wav', $a->path);
        $this->assertArrayHasKey('মা', LearnController::lessonData('first-letters', 1)['audio']);
        Livewire::test(LearnRecordings::class)->set('files.'.LearnRecordings::key('মামা'), UploadedFile::fake()->createWithContent('x.mp3', '<?php echo 1;'));
        $this->assertNull(LearnAudio::where('text', 'মামা')->first(), 'not audio by its content: refused, whatever its name');
        Livewire::test(LearnRecordings::class)->call('remove', $key);
        $this->assertFileDoesNotExist(public_path($a->path));
        $this->assertSame(0, LearnAudio::count());
    }

    public function test_readers_cannot_open_the_recordings_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Writer]));
        $this->assertFalse(LearnRecordings::canAccess());
    }

    public function test_a_childrens_piece_with_a_reading_level_is_suggested_on_its_unit(): void
    {
        $p = $this->makePost(['title' => 'মেঘের গল্প', 'reading_unit' => 6]);
        $this->get('/janun/learn-bengali/kar-1/')->assertSee('মেঘের গল্প');
        $this->get('/janun/learn-bengali/first-letters/')->assertDontSee('মেঘের গল্প');
        unset($p);
    }
}
