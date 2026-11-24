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
    /** D275: a serial in an issue carries its episodes of the month, and the last one says where the rest is. */
    public function test_a_serial_in_an_issue_carries_its_month_of_episodes_and_where_the_rest_is(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-12-20 10:00:00');
        $issue = Issue::create(['slug' => '2026-12', 'title' => 'ডিসেম্বর সংখ্যা', 'status' => 'published', 'published_at' => '2026-12-01 03:30:00']);
        $serial = \App\Models\Serial::create(['title' => 'লাল কাচ', 'slug' => 'lal-kach', 'status' => 'active', 'day' => 6]);
        $ep = fn (int $n, string $at, array $more = []) => $this->makePost(['status' => 'published', 'slug' => 'lal-kach-'.$n, 'title' => 'লাল কাচ (পর্ব '.$n.'): দৃশ্য '.$n,
            'serial_id' => $serial->id, 'episode_no' => $n, 'published_at' => $at, 'body_html' => '<p>পর্ব '.$n.' এর লেখা</p>'] + $more);
        $ep(1, '2026-12-05 03:30:00', ['issue_id' => $issue->id]);
        $ep(2, '2026-12-12 03:30:00');
        $ep(3, '2026-12-19 03:30:00');
        $ep(4, '2026-12-26 03:30:00', ['status' => 'scheduled']);   // not out yet
        $this->makePost(['status' => 'published', 'slug' => 'kobita', 'title' => 'শীতের কবিতা', 'issue_id' => $issue->id, 'published_at' => '2026-12-02 03:30:00']);

        $pieces = IssueBook::pieces($issue);
        $this->assertSame(['শীতের কবিতা', 'লাল কাচ (পর্ব 1): দৃশ্য 1', 'লাল কাচ (পর্ব 2): দৃশ্য 2', 'লাল কাচ (পর্ব 3): দৃশ্য 3'], $pieces->pluck('title')->all());

        $book = $this->get('/sonkha/2026-12/boi/')->assertOk();
        $book->assertSee('ধারাবাহিক · লাল কাচ')->assertSee('পর্ব 3 এর লেখা', false)->assertSee('চলবে…')->assertSee('পর্ব ৪ থেকে বাকি গল্প পড়ুন হাতপাখার সাইটে। প্রতি শনিবার সকালে নতুন পর্ব।')->assertDontSee('আগামী সংখ্যায়')->assertSee('/dharabahik/lal-kach/', false)->assertDontSee('পর্ব 4 এর লেখা');
        // the issue page lists the serial once, with its episodes and where all of it is
        $page = $this->get('/sonkha/2026-12/')->assertOk()->assertSee('পর্ব ১–৩')->assertSee('সব পর্ব পড়ুন')->assertSee('২টি লেখা');

        // the next monthly issue closes December: January's episodes are not in December's book
        \Illuminate\Support\Carbon::setTestNow('2027-01-03 10:00:00');
        Issue::create(['slug' => '2027-01', 'title' => 'জানুয়ারি সংখ্যা', 'status' => 'published', 'published_at' => '2027-01-01 03:30:00']);
        $ep(5, '2027-01-02 03:30:00');
        $titles = IssueBook::pieces($issue->fresh())->pluck('title')->all();
        $this->assertNotContains('লাল কাচ (পর্ব 4): দৃশ্য 4', $titles, 'D278: three episodes at most in one issue');
        $this->assertNotContains('লাল কাচ (পর্ব 5): দৃশ্য 5', $titles);
        $this->get('/sonkha/2026-12/boi/')->assertSee('পর্ব ৪ থেকে বাকি গল্প পড়ুন হাতপাখার সাইটে।')->assertSee('সব পর্ব পড়ুন →')->assertSee('class="mag-qr"', false);
        // D276: on paper the place is written out in words, with a QR code; January's issue says where the start is
        $this->actingAs(User::factory()->create(['role' => Role::Editor]))->get('/issue-print/'.$issue->id.'/')->assertOk()
            ->assertSee('ধারাবাহিক: লাল কাচ (পর্ব ১–৩)')->assertSee('/dharabahik/lal-kach</a>', false)->assertSee('data:image/svg+xml;base64,', false);
        $jan = Issue::where('slug', '2027-01')->first();
        $this->makePost(['status' => 'published', 'slug' => 'lal-kach-6', 'title' => 'লাল কাচ (পর্ব 6): দৃশ্য 6', 'serial_id' => $serial->id, 'episode_no' => 6, 'published_at' => '2027-01-03 03:30:00', 'issue_id' => $jan->id]);
        // D278: January does not carry the serial again (December did); readers go on on the site
        $this->assertNotContains('লাল কাচ (পর্ব 6): দৃশ্য 6', IssueBook::pieces($jan->fresh())->pluck('title')->all());

        // the e-book says it too
        $path = IssueBook::epub($issue->fresh());
        $zip = new \ZipArchive();
        $zip->open($path);
        $all = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $all .= (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($path);
        $this->assertStringContainsString('ধারাবাহিক · লাল কাচ', $all);
        $this->assertStringContainsString('lal-kach', $all);
        \Illuminate\Support\Carbon::setTestNow();
    }
}
