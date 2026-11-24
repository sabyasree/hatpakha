<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Issue;
use App\Models\Section;
use App\Models\Serial;
use App\Support\IssueBook;
use App\Support\IssueCover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** D277: the issue cover, two styles, the same everywhere; only real pieces and writers, never the desk. */
class IssueCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_style_follows_the_kind_unless_the_owner_picks_one(): void
    {
        $monthly = Issue::create(['slug' => '2026-12', 'title' => 'ডিসেম্বর সংখ্যা', 'status' => 'published', 'published_at' => '2026-12-01 03:30:00']);
        $special = Issue::create(['slug' => 'phire-asha', 'title' => 'ফিরে আসা সংখ্যা', 'status' => 'published', 'published_at' => '2026-12-14 03:30:00']);
        $festival = Issue::create(['slug' => '2026-10', 'kind' => 'festival', 'title' => 'শারদীয়া সংখ্যা', 'status' => 'published', 'published_at' => '2026-10-01 03:30:00']);
        $this->assertSame(['fan', 'full', 'full'], [IssueCover::style($monthly), IssueCover::style($special), IssueCover::style($festival)]);
        $special->forceFill(['cover_style' => 'fan'])->save();
        $this->assertSame('fan', IssueCover::style($special));

        $c = IssueCover::data($monthly);
        $this->assertSame(['ডিসেম্বর', 'মাসিক সংখ্যা', '১৫', '১২', 'বর্ষ ১৫ · সংখ্যা ১২'], [$c['main'], $c['label'], $c['vol'], $c['no'], $c['ear']]);
        $c = IssueCover::data($special->fresh());
        $this->assertSame(['ফিরে আসা', 'বিশেষ সংখ্যা', null, 'ডিসেম্বর ২০২৬'], [$c['main'], $c['label'], $c['no'], $c['monthYear']]);
    }

    public function test_cover_lines_put_real_writers_first_show_a_serial_once_and_never_the_desk(): void
    {
        Carbon::setTestNow('2026-12-20 10:00:00');
        $issue = Issue::create(['slug' => '2026-12', 'title' => 'ডিসেম্বর সংখ্যা', 'status' => 'published', 'published_at' => '2026-12-01 03:30:00']);
        $desk = Author::create(['slug' => 'desk', 'display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        $mita = Author::create(['slug' => 'mita', 'display_name' => 'মিতা সেন']);
        $story = Section::firstOrCreate(['slug' => 'short-stories'], ['name_bn' => 'গল্প']);
        $serial = Serial::create(['title' => 'লাল কাচ', 'slug' => 'lal-kach', 'status' => 'active', 'day' => 6]);
        $this->makePost(['slug' => 'desk-1', 'title' => 'ডেস্কের লেখা', 'issue_id' => $issue->id, 'published_at' => '2026-12-02 03:30:00'], $desk);
        foreach ([1, 2] as $n) {
            $this->makePost(['slug' => 'ep-'.$n, 'title' => 'লাল কাচ (পর্ব '.$n.'): দৃশ্য', 'serial_id' => $serial->id, 'episode_no' => $n,
                'issue_id' => $n === 1 ? $issue->id : null, 'published_at' => '2026-12-0'.(4 + $n).' 03:30:00', 'section_id' => $story->id], $mita);
        }
        $this->makePost(['slug' => 'kobita', 'title' => 'বৃষ্টির দুপুরে ফিরে আসা একটা খুব লম্বা নামের গল্প', 'issue_id' => $issue->id, 'published_at' => '2026-12-03 03:30:00'], $mita);

        $lines = IssueCover::data($issue, IssueBook::pieces($issue))['lines'];
        $this->assertSame(['বৃষ্টির দুপুরে ফিরে আসা একটা খুব লম্বা নামের গল্প', 'লাল কাচ', 'ডেস্কের লেখা'], array_column($lines, 't'), 'The lead, the serial by its name once, then the desk piece last');
        $this->assertSame(['মিতা সেন', 'মিতা সেন', ''], array_column($lines, 'w'), 'The desk is never named on the cover');

        // the magazine and the issue page draw it; the desk never appears as a writer on it
        $book = $this->get('/sonkha/2026-12/boi/')->assertOk()->assertSee('hp-cv fan k-monthly', false)->assertSee('ধারাবাহিক');
        $cover = substr($book->getContent(), strpos($book->getContent(), 'hp-cv'), 6000);
        $this->assertStringNotContainsString('হাতপাখা ডেস্ক', explode('</section>', $cover)[0]);
        $this->get('/sonkha/2026-12/')->assertOk()->assertSee('hp-cv fan k-monthly', false);
        $this->get('/sonkha/')->assertOk()->assertSee('hp-cv', false);

        // a full-picture cover with a long lead keeps two lines; the e-book cover is well-formed SVG
        $issue->forceFill(['cover_style' => 'full'])->save();
        $this->assertCount(2, IssueCover::data($issue, IssueBook::pieces($issue))['lines']);
        $svg = IssueBook::coverSvg($issue, IssueBook::pieces($issue), null);
        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringNotContainsString('হাতপাখা ডেস্ক', $svg);
    }

    public function test_the_cover_keeps_bengali_digits_when_the_desk_is_in_english(): void
    {
        $issue = Issue::create(['slug' => '2026-12', 'title' => 'ডিসেম্বর সংখ্যা', 'status' => 'published', 'published_at' => '2026-12-01 03:30:00']);
        app()->setLocale('en');
        \App\Support\Bn::$latin = true;
        try {
            $c = IssueCover::data($issue);
            $this->assertSame(['১৫', '২০২৬', 'বর্ষ ১৫ · সংখ্যা ১২'], [$c['vol'], $c['year'], $c['ear']]);
        } finally {
            \App\Support\Bn::$latin = false;
        }
    }

    public function test_the_barcode_is_the_issns(): void
    {
        // 977 + 2321303 + 00 + check digit 9: the standard ISSN barcode (EAN-13)
        $this->assertSame('9772321303009', IssueCover::barcodeDigits());
        $this->assertStringContainsString('<rect x="0" y="0" width="1" height="40"/>', IssueCover::barcodeSvg(), 'Starts with the guard bars');
    }
}
