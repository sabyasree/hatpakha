<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Issue;
use App\Models\User;
use App\Support\IssueBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D68: an issue as a book: reader view, EPUB for Google Play Books, print page for editors. */
class IssueBookTest extends TestCase
{
    use RefreshDatabase;

    private function issue(): Issue
    {
        $issue = Issue::create(['slug' => '2026-11', 'title' => 'হেমন্ত সংখ্যা', 'status' => 'published', 'published_at' => now(), 'editor_note' => 'হেমন্তের শুভেচ্ছা।']);
        $this->makePost(['status' => 'published', 'slug' => 'a', 'title' => 'নবান্ন', 'issue_id' => $issue->id, 'published_at' => now()->subDay(),
            'body_html' => '<p>ধানের গন্ধ<br>উঠোন জুড়ে</p><p><img src="/x.jpg"></p><script>alert(1)</script>']);

        return $issue;
    }

    public function test_reader_book_view_is_noindex_and_linked_from_the_issue(): void
    {
        $issue = $this->issue();
        $this->get('/sonkha/2026-11/')->assertSee('পত্রিকা খুলুন');   // 2026-11-12: the magazine
        $this->get('/sonkha/2026-11/boi/')->assertOk()->assertSee('নবান্ন')->assertSee('সূচি')->assertSee('noindex', false)->assertDontSee('alert(1)', false);
    }

    public function test_epub_is_valid_and_carries_the_bengali_font(): void
    {
        $path = IssueBook::epub($this->issue());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertSame('mimetype', $zip->getNameIndex(0), 'mimetype must come first');
        $this->assertSame('application/epub+zip', $zip->getFromName('mimetype'));
        $this->assertNotFalse($zip->locateName('OEBPS/fonts/NotoSerifBengali-Regular.ttf'));
        $opf = $zip->getFromName('OEBPS/content.opf');
        $this->assertStringContainsString('<dc:language>bn</dc:language>', $opf);
        $chapter = $zip->getFromName('OEBPS/c1.xhtml');
        $this->assertStringContainsString('নবান্ন', $chapter);
        $this->assertStringNotContainsString('<img', $chapter);
        $this->assertStringNotContainsString('<script', $chapter);
        $this->assertNotFalse(simplexml_load_string($chapter), 'Chapters are well-formed XHTML');
        $zip->close();
        @unlink($path);
    }

    public function test_print_page_is_for_editors_only(): void
    {
        $issue = $this->issue();
        $this->get('/issue-print/'.$issue->id.'/')->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => Role::Writer]))->get('/issue-print/'.$issue->id.'/')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Editor]))->get('/issue-print/'.$issue->id.'/')->assertOk()->assertSee('Save as PDF')->assertSee('নবান্ন');
    }
}
