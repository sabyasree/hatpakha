<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Issue;
use App\Models\User;
use App\Support\IssueEbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Owner (2026-11-12): the issue as a real magazine (full screen, pages that turn), and its e-book approved before readers get it. */
class MagazineTest extends TestCase
{
    use RefreshDatabase;

    private function issue(): Issue
    {
        $issue = Issue::create(['slug' => '2026-11', 'title' => 'অগ্রহায়ণ সংখ্যা', 'status' => 'published', 'published_at' => now(), 'editor_note' => 'শীতের প্রথম হাওয়া।']);
        $a = Author::create(['slug' => 'mou', 'display_name' => 'মৌ', 'bio' => 'কবিতা লেখেন।']);
        $this->makePost(['status' => 'published', 'slug' => 'nodi', 'title' => 'নদীর ধারে', 'issue_id' => $issue->id, 'published_at' => now()->subDay()], $a);
        $this->makePost(['status' => 'published', 'slug' => 'kuasha', 'title' => 'কুয়াশা', 'issue_id' => $issue->id, 'published_at' => now()->subHour()], $a);

        return $issue;
    }

    public function test_the_magazine_has_cover_contents_sections_writers_and_back_page(): void
    {
        $this->issue();
        $html = $this->get('/sonkha/2026-11/boi/')->assertOk()->getContent();
        foreach (['data-kind="cover"', 'data-kind="inside"', 'data-kind="contents"', 'data-kind="opener"', 'data-kind="writers"', 'data-kind="back"', 'hp-magazine.js', 'data-mag-fullscreen', 'noindex'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        $this->assertStringContainsString('data-goto="piece-', $html, 'Contents link to the pieces');
        $this->assertStringContainsString('কবিতা লেখেন।', $html, 'The issue\'s writers with their own bios');
        $this->get('/sonkha/2026-11/')->assertOk()->assertSee('পত্রিকা খুলুন')->assertDontSee('ই-বুক নামান');
    }

    private function editorial(Issue $issue, string $status = 'published'): \App\Models\Post
    {
        $section = \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $post = $this->makePost(['status' => $status, 'slug' => 'sompadokiyo', 'title' => 'শীতের মুখে', 'section_id' => $section->id, 'format' => 'prose',
            'issue_id' => $issue->id, 'published_at' => now()->subMinutes(5)]);
        $issue->forceFill(['editorial_post_id' => $post->id])->save();

        return $post;
    }

    public function test_the_editorial_is_its_own_section_even_without_a_link(): void
    {
        $issue = $this->issue();
        // Made by hand or imported from WordPress: the editorial is a piece of the issue in the সম্পাদকীয় section, not linked.
        $section = \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $ed = $this->makePost(['status' => 'published', 'slug' => 'ed', 'title' => 'হেমন্তের কথা', 'section_id' => $section->id, 'format' => 'prose',
            'issue_id' => $issue->id, 'published_at' => now()->subDays(2)]);
        $this->assertSame($ed->id, $issue->liveEditorial()?->id);

        $html = $this->get('/sonkha/2026-11/boi/')->assertOk()->getContent();
        $this->assertStringContainsString('<h2>সম্পাদকীয়</h2>', $html, 'An opener page of its own');
        $this->assertStringContainsString('<li><h3>সম্পাদকীয়</h3></li>', $html, 'Its own heading in the contents');
        $this->assertSame(1, substr_count($html, 'data-anchor="piece-'.$ed->id.'"'), 'Shown once');
        $this->assertLessThan(strpos($html, 'নদীর ধারে</h2>'), strpos($html, 'হেমন্তের কথা</h2>'), 'Before the other pieces');
        $this->get('/sonkha/2026-11/')->assertSeeInOrder(['সম্পাদকীয়', 'হেমন্তের কথা', 'এই সংখ্যায়', 'সম্পাদকীয়', 'হেমন্তের কথা']);
        $this->assertSame('live', \App\Ai\IssueWatch::status($issue)['editorial']);
        $this->assertSame($ed->id, \App\Support\IssueBook::pieces($issue)->first()->id, 'First in the e-book too');
    }

    public function test_the_ebook_has_the_editorial_and_a_cover_and_stays_with_the_owner(): void
    {
        Storage::fake('local');
        $issue = $this->issue();
        $this->assertSame(0, IssueEbook::buildDue(), 'No editorial yet: no e-book');
        $editorial = $this->editorial($issue, 'draft');
        $this->assertSame(0, IssueEbook::buildDue(), 'Editorial still a draft: no e-book');
        $editorial->update(['status' => 'published']);
        $this->assertSame(1, IssueEbook::buildDue());
        $this->assertSame(0, IssueEbook::buildDue(), 'Nothing changed: not built again');
        $issue->refresh();
        Storage::disk('local')->assertExists($issue->ebook_path);

        // Readers read on the site only.
        $this->get('/sonkha/2026-11/ebook.epub')->assertNotFound();
        $this->get('/sonkha/2026-11/')->assertDontSee('ই-বুক নামান')->assertDontSee('ebook.epub');

        $zip = new \ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'e');
        file_put_contents($tmp, Storage::disk('local')->get($issue->ebook_path));
        $zip->open($tmp);
        foreach (['OEBPS/writers.xhtml', 'OEBPS/back.xhtml', 'OEBPS/cover.xhtml', 'OEBPS/images/cover.svg'] as $f) {
            $this->assertNotFalse($zip->locateName($f), $f);
        }
        $cover = $zip->getFromName('OEBPS/images/cover.svg');
        $this->assertStringContainsString('অগ্রহায়ণ সংখ্যা', $cover);
        $this->assertStringContainsString('হাতপাখা', $cover);
        $this->assertStringContainsString('properties="cover-image"', $zip->getFromName('OEBPS/content.opf'));
        $this->assertNotFalse(simplexml_load_string($cover), 'The cover is valid XML');
        $this->assertTrue(collect(range(0, $zip->numFiles - 1))->contains(fn ($k) => str_contains((string) $zip->getFromIndex($k), 'শীতের মুখে')), 'The editorial is inside');
        $zip->close();
        @unlink($tmp);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('শীতের মুখে')->assertSee('ই-বুক (শুধু আপনার জন্য)')->assertDontSee('অনুমোদন দিন');
    }

    public function test_the_month_never_misses_its_editorial_or_cover(): void
    {
        Storage::fake('local');
        \Illuminate\Support\Facades\Cache::flush();
        $this->travelTo(now('Asia/Kolkata')->startOfMonth()->addDays(24)->setTime(9, 0));   // the 25th
        $owner = User::factory()->create(['role' => Role::Admin]);
        \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $next = Issue::create(['slug' => now('Asia/Kolkata')->addMonthNoOverflow()->format('Y-m'), 'title' => 'পরের সংখ্যা', 'status' => 'proposed']);

        // AI off: nothing can be made, but the owner sees what is missing.
        $this->assertSame(['সম্পাদকীয় এখনও লেখা হয়নি', 'প্রচ্ছদ এখনও আঁকা হয়নি'], \App\Ai\IssueWatch::needs($next));
        $this->assertSame(0, \App\Ai\IssueWatch::run()['editorials']);

        // AI on: the editorial draft is written (once a day at most), and the owner is reminded on the 25th.
        \Illuminate\Support\Facades\Cache::flush();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        \App\Ai\Claude::$fake = fn ($purpose, $user, $schema) => ['title' => 'সম্পাদকীয়', 'paragraphs' => ['প্রিয় পাঠক,']];
        $done = \App\Ai\IssueWatch::run();
        $this->assertSame(1, $done['editorials']);
        $this->assertSame(1, $done['reminders']);
        $this->assertSame('draft', \App\Ai\IssueWatch::status($next->fresh())['editorial']);
        $this->assertSame(0, \App\Ai\IssueWatch::run()['reminders'], 'One reminder a day');
        \App\Ai\Claude::$fake = null;

        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $this->assertSame('১', \App\Filament\Pages\Ebooks::getNavigationBadge());
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('পরের সংখ্যা')->assertSee('খসড়া খুলুন')->assertSee('এখনই আঁকান');
        $this->assertContains('মাসের পত্রিকায় কাজ বাকি (প্রচ্ছদ বা সম্পাদকীয়)', collect(\App\Support\HealthReport::build()['todo'])->pluck('label')->all());

        // The designer's proposed cover becomes the cover when the owner picks it.
        $next->forceFill(['proposed_cover_path' => '/media/covers/x.jpg'])->save();
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('এই প্রচ্ছদ নিন')->call('useCover', $next->id);
        $this->assertSame('/media/covers/x.jpg', $next->fresh()->cover_path);
        $this->assertSame('set', \App\Ai\IssueWatch::status($next->fresh())['cover']);
    }
}
