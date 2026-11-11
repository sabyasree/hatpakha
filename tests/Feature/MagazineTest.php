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

    public function test_the_ebook_is_built_by_itself_and_reaches_readers_only_after_approval(): void
    {
        Storage::fake('local');
        $issue = $this->issue();
        $this->get('/sonkha/2026-11/ebook.epub')->assertNotFound();

        $this->assertSame(1, IssueEbook::buildDue());
        $this->assertSame(0, IssueEbook::buildDue(), 'Nothing changed: not built again');
        $issue->refresh();
        $this->assertTrue(IssueEbook::waiting($issue));
        Storage::disk('local')->assertExists($issue->ebook_path);
        $this->get('/sonkha/2026-11/ebook.epub')->assertNotFound('Not for readers until approved');

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $this->assertSame('১', \App\Filament\Pages\Ebooks::getNavigationBadge());
        \Livewire\Livewire::test(\App\Filament\Pages\Ebooks::class)->assertSee('অনুমোদনের অপেক্ষায়')->call('approve', $issue->id);
        auth()->logout();
        $this->get('/sonkha/2026-11/ebook.epub')->assertOk()->assertHeader('Content-Type', 'application/epub+zip');
        $this->get('/sonkha/2026-11/')->assertSee('ই-বুক নামান');

        // The EPUB has the writers and the back page.
        $zip = new \ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'e');
        file_put_contents($tmp, Storage::disk('local')->get($issue->fresh()->ebook_public_path));
        $zip->open($tmp);
        $this->assertNotFalse($zip->locateName('OEBPS/writers.xhtml'));
        $this->assertNotFalse($zip->locateName('OEBPS/back.xhtml'));
        @unlink($tmp);

        // A piece changes: a new build waits; readers keep the approved one meanwhile.
        $this->travel(1)->minutes();
        \App\Models\Post::where('slug', 'kuasha')->first()->update(['title' => 'ঘন কুয়াশা']);
        $this->assertSame(1, IssueEbook::buildDue());
        $this->assertTrue(IssueEbook::waiting($issue->fresh()));
        $this->get('/sonkha/2026-11/ebook.epub')->assertOk();
    }
}
