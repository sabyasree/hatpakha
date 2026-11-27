<?php

namespace Tests\Feature;

use App\Ai\BanglaCalendarDesk;
use App\Models\BanglaMonth;
use App\Models\FestivalTime;
use App\Support\BanglaDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D308: one page per festival per year, and one list per year, from checked times only; they appear by themselves. */
class FestivalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 06:00:00');
        foreach ([1433, 1434] as $y) {
            BanglaCalendarDesk::seed($y);
        }
        BanglaMonth::query()->update(['status' => 'verified']);
        BanglaDate::flush();
    }

    protected function tearDown(): void
    {
        BanglaDate::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function time(string $festival, string $label, ?string $start, ?string $end, string $day, int $cycle = 2027, string $status = 'verified'): void
    {
        FestivalTime::create(['festival' => $festival, 'cycle' => $cycle, 'label' => $label, 'day' => $day, 'status' => $status, 'sources' => ['https://a.org/p'],
            'starts_at' => $start ? CarbonImmutable::parse($start, 'Asia/Kolkata')->utc() : null, 'ends_at' => $end ? CarbonImmutable::parse($end, 'Asia/Kolkata')->utc() : null]);
    }

    public function test_a_festival_page_from_checked_times(): void
    {
        $this->time('saraswati', 'পঞ্চমী তিথি', '2027-02-11 03:04', '2027-02-12 03:18', '2027-02-11');
        $html = $this->get('/janun/puja/saraswati-puja-2027/')->assertOk()
            ->assertSee('<title>Saraswati Puja 2027 Date &amp; Time · সরস্বতী পুজো ২০২৭', false)
            ->assertSee('সরস্বতী পুজো ২০২৭: তারিখ, তিথি ও সময়')
            ->assertSee('১১ ফেব্রুয়ারি ২০২৭, বৃহস্পতিবার')
            ->assertSee('পশ্চিমবঙ্গে <b>২৮ মাঘ ১৪৩৩</b>', false)
            ->assertSee('লন্ডন')->assertSee('টরন্টো')->assertSee('সিডনি')
            ->assertSee('প্রশ্নোত্তর')->assertSee('সরস্বতী পুজো ২০২৭ কবে?')
            ->getContent();
        // the New York start: 3:04 IST = 16:34 the day before in New York (EST)
        $this->assertStringContainsString('১০ ফেব্রুয়ারি ২০২৭, বুধবার, বিকেল ৪:৩৪', $html);
        $this->assertStringContainsString('"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('"Event"', $html, 'no Event markup for a holiday');
    }

    public function test_no_page_until_half_the_events_are_checked_and_never_for_held_ones(): void
    {
        foreach (['মহালয়া (অমাবস্যা)', 'ষষ্ঠী', 'মহাসপ্তমী'] as $i => $l) {
            $this->time('durga', $l, '2027-10-0'.($i + 1).' 10:00', '2027-10-0'.($i + 2).' 11:00', '2027-10-0'.($i + 1));
        }
        $this->time('durga', 'মহাষ্টমী', '2027-10-08 10:00', '2027-10-09 11:00', '2027-10-08', 2027, 'held');
        $this->get('/janun/puja/durga-puja-2027/')->assertNotFound();   // 3 of 7
        FestivalTime::where('label', 'মহাষ্টমী')->update(['status' => 'verified']);
        $this->get('/janun/puja/durga-puja-2027/')->assertOk();
        $this->get('/janun/puja/durga-puja-2099/')->assertNotFound();
        $this->get('/janun/puja/something-2027/')->assertNotFound();
    }

    public function test_the_year_list_links_and_sitemap(): void
    {
        $this->time('saraswati', 'পঞ্চমী তিথি', '2027-02-11 03:04', '2027-02-12 03:18', '2027-02-11');
        $this->get('/janun/puja/2027/')->assertNotFound();   // one festival is not a list
        $this->time('boishakh', 'পয়লা বৈশাখ (পশ্চিমবঙ্গ)', null, null, '2027-04-15');
        $this->get('/janun/puja/2027/')->assertOk()->assertSee('পুজো-পার্বণ ২০২৭: তারিখের তালিকা')
            ->assertSee('/janun/puja/saraswati-puja-2027/', false)->assertSee('/janun/puja/poila-boishakh-2027/', false);
        $this->get('/janun/puja/poila-boishakh-2027/')->assertOk()->assertSee('পয়লা বৈশাখ ২০২৭')->assertSee('১ বৈশাখ ১৪৩৪');
        $sitemap = $this->get('/sitemap-pages.xml')->getContent();
        $this->assertStringContainsString('/janun/puja/saraswati-puja-2027/', $sitemap);
        $this->assertStringContainsString('/janun/puja/2027/', $sitemap);
        // linked from the puja part, the times page and the calendar's "next puja"
        $this->get('/janun/puja/')->assertOk()->assertSee('/janun/puja/saraswati-puja-2027/', false)->assertSee('/janun/puja/2027/', false);
        $this->get('/janun/puja/pujor-somoy/')->assertSee('/janun/puja/saraswati-puja-2027/', false);
        $this->get('/janun/bangla-calendar/')->assertSee('/janun/puja/saraswati-puja-2027/', false);
        // a past year's page stays once shown
        Carbon::setTestNow('2028-06-01 06:00:00');
        $this->get('/janun/puja/saraswati-puja-2027/')->assertOk();
    }
}
