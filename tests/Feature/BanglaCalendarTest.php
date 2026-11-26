<?php

namespace Tests\Feature;

use App\Ai\BanglaCalendarDesk;
use App\Ai\Claude;
use App\Ai\JanunDesk;
use App\Models\BanglaMonth;
use App\Support\BanglaDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D303: বাংলা ক্যালেন্ডার: Bangladesh by arithmetic, West Bengal calculated and checked, pages and links. */
class BanglaCalendarTest extends TestCase
{
    use RefreshDatabase;

    private array $answer = [];

    private array $published = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 06:00:00');   // 11:30 in India, a Thursday
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        JanunDesk::$resolve = fn ($host) => ['93.184.216.34'];
        Claude::$fake = fn ($purpose, $user) => $this->answer;
        BanglaDate::flush();
        Http::preventStrayRequests();
        $this->published = [];
        Http::fake(['https://myastrology.in/*' => fn ($r) => isset($this->published[$r->url()]) ? Http::response($this->published[$r->url()]) : Http::response('', 404)]);
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        JanunDesk::$resolve = null;
        BanglaDate::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_bangladesh_dates_are_exact(): void
    {
        $this->assertSame([1433, 1, 1], BanglaDate::bd(new \DateTime('2026-04-14')));
        $this->assertSame([1432, 12, 30], BanglaDate::bd(new \DateTime('2026-04-13')));
        $this->assertSame([1433, 9, 1], BanglaDate::bd(new \DateTime('2026-12-16')), 'বিজয় দিবস = ১ পৌষ');
        $this->assertSame([1433, 11, 8], BanglaDate::bd(new \DateTime('2027-02-21')), 'একুশে = ৮ ফাল্গুন');
        $this->assertSame([1433, 12, 12], BanglaDate::bd(new \DateTime('2027-03-26')));
        $this->assertSame([1434, 11, 16], BanglaDate::bd(new \DateTime('2028-02-29')), 'a leap-year ফাল্গুন has 30 days');
        $this->assertSame([1434, 12, 1], BanglaDate::bd(new \DateTime('2028-03-15')));
        $this->assertSame('২০২৬-১২-১৬', \App\Support\Bn::num(BanglaDate::bdStart(1433, 9)->toDateString()));
    }

    public function test_west_bengal_month_starts_are_calculated(): void
    {
        $this->assertSame('2024-04-14', BanglaDate::wbComputed(1431)[0]->toDateString(), 'পয়লা বৈশাখ ১৪৩১');
        $this->assertSame('2026-04-15', BanglaDate::wbComputed(1433)[0]->toDateString(), 'পয়লা বৈশাখ ১৪৩৩');
        $this->assertSame('2026-11-17', BanglaDate::wbComputed(1433)[7]->toDateString(), '১ অগ্রহায়ণ ১৪৩৩');
        foreach ([1432, 1433, 1434, 1435] as $y) {
            $s = BanglaDate::wbComputed($y);
            for ($i = 0; $i < 12; $i++) {
                $len = $s[$i]->diffInDays($s[$i + 1]);
                $this->assertTrue($len >= 29 && $len <= 32, "$y month ".($i + 1).": $len days");
            }
        }
    }

    private function pages(string ...$dates): void
    {
        $en = fn ($d) => CarbonImmutable::parse($d)->format('j F Y');
        Http::fake([
            'https://a.org/*' => Http::response('<table>'.implode('', array_map(fn ($d) => '<tr><td>'.$en($d).'</td></tr>', $dates)).'</table>'),
            'https://b.in/*' => Http::response('<ul>'.implode('', array_map(fn ($d) => '<li>'.CarbonImmutable::parse($d)->format('d/m/Y').'</li>', $dates)).'</ul>'),
            '*' => Http::response('', 404),
        ]);
    }

    public function test_months_are_shown_only_once_checked(): void
    {
        BanglaCalendarDesk::seed(1433);
        BanglaCalendarDesk::seed(1434);
        $this->assertNull(BanglaDate::wb(new \DateTime('2026-11-26')), 'nothing checked yet');
        $this->get('/janun/bangla-calendar/')->assertOk()->assertSee('১১ অগ্রহায়ণ ১৪৩৩')->assertSee('মিলিয়ে দেখা হচ্ছে');

        $computed = collect(BanglaDate::wbComputed(1433))->take(12)->map->toDateString()->all();
        $claimed = $computed;
        $claimed[3] = '2026-07-25';   // শ্রাবণ: a published calendar far from our calculation
        $claimed[5] = CarbonImmutable::parse($computed[5])->addDay()->toDateString();   // আশ্বিন: a day later (a সংক্রান্তি near midnight)
        $this->pages(...$claimed);
        $this->answer = ['months' => array_map(fn ($i) => ['month' => BanglaDate::MONTHS[$i], 'first_day' => $claimed[$i]], range(0, 11)),
            'sources' => [['url' => 'https://a.org/c'], ['url' => 'https://b.in/c'], ['url' => 'https://hatpakha.com/x']]];
        $this->assertSame(11, BanglaCalendarDesk::check(1433));
        $this->assertSame('held', BanglaMonth::where(['year' => 1433, 'month' => 4])->value('status'));
        $this->assertSame($claimed[5], BanglaMonth::where(['year' => 1433, 'month' => 6])->first()->starts_on->toDateString(), 'the published day is kept');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'hatpakha'));

        $this->assertSame([1433, 8, 10], BanglaDate::wb(new \DateTime('2026-11-26')));
        $this->assertNull(BanglaDate::wb(new \DateTime('2026-07-20')), 'আষাঢ়: its end (শ্রাবণ) is not checked');
        $this->assertNull(BanglaDate::wb(new \DateTime('2027-04-10')), 'চৈত্র: next বৈশাখ is not checked yet');
    }

    private function checkAll(): void
    {
        foreach ([1433, 1434] as $y) {
            BanglaCalendarDesk::seed($y);
        }
        BanglaMonth::query()->update(['status' => 'verified']);
        BanglaDate::flush();
    }

    public function test_the_pages(): void
    {
        $this->checkAll();
        $html = $this->get('/janun/bangla-calendar/')->assertOk()
            ->assertSee('১০ অগ্রহায়ণ ১৪৩৩')->assertSee('বাংলাদেশে ১১ অগ্রহায়ণ ১৪৩৩')->assertSee('বৃহস্পতিবার')->assertSee('হেমন্ত')
            ->assertSee('<title>বাংলা ক্যালেন্ডার ১৪৩৩: আজ বাংলা কত তারিখ', false)->getContent();
        $this->assertStringContainsString('data-cal-tab="wb"', $html);
        $this->assertStringContainsString('"BreadcrumbList"', $html);

        $this->get('/janun/bangla-calendar/1433/')->assertOk()->assertSee('বঙ্গাব্দ ১৪৩৩')->assertSee('১৫ এপ্রিল ২০২৬')->assertSee('১৪ এপ্রিল ২০২৬')
            ->assertSee('শহিদ দিবস ও আন্তর্জাতিক মাতৃভাষা দিবস')->assertSee('রবীন্দ্রজয়ন্তী (পঁচিশে বৈশাখ) (বাংলাদেশ)');
        $this->get('/janun/bangla-calendar/1433/poush/')->assertOk()->assertSee('পৌষ ১৪৩৩')->assertSee('বাংলাদেশের বিজয় দিবস')
            ->assertSee(BanglaDate::url(1433, 8), false)->assertSee(BanglaDate::url(1433, 10), false);
        $this->get('/janun/bangla-calendar/1432/')->assertNotFound();
        $this->get('/janun/bangla-calendar/1436/')->assertNotFound();
        $this->get('/janun/bangla-calendar/1433/january/')->assertNotFound();
        $this->get('/janun/bangla-calendar/1433/poush')->assertRedirect(BanglaDate::url(1433, 9));

        $sitemap = $this->get('/sitemap-pages.xml')->getContent();
        $this->assertStringContainsString(BanglaDate::url(1434, 12), $sitemap);
        $this->assertStringContainsString(BanglaDate::url(), $this->get('/llms.txt')->getContent());
    }

    public function test_home_page_and_footer(): void
    {
        $this->checkAll();
        cache()->flush();
        $this->get('/')->assertOk()->assertSee('আজ ১০ অগ্রহায়ণ ১৪৩৩ (পশ্চিমবঙ্গ)')->assertSee('· বাংলাদেশে ১১ অগ্রহায়ণ ১৪৩৩')->assertSee(BanglaDate::url(), false);
        $this->get('/janun/')->assertOk()->assertSee('বাংলা ক্যালেন্ডার');
    }

    public function test_the_desk_checks_once_an_hour(): void
    {
        $this->answer = ['months' => [], 'sources' => []];
        $now = CarbonImmutable::now('Asia/Kolkata');
        $this->assertTrue(BanglaCalendarDesk::run($now));
        $this->assertFalse(BanglaCalendarDesk::run($now));
        $this->assertSame(25, BanglaMonth::count(), 'this year and next, and the বৈশাখ after');
        $this->assertSame(0, BanglaMonth::where('status', 'verified')->count());
    }

    public function test_each_calendar_shows_its_own_month_on_the_days_they_differ(): void
    {
        $this->checkAll();
        Carbon::setTestNow('2026-08-16 06:00:00');   // Bangladesh 1 ভাদ্র, West Bengal still শ্রাবণ
        BanglaDate::flush();
        $this->assertSame([1433, 5, 1], BanglaDate::bd(new \DateTime('2026-08-16')));
        $html = $this->get('/janun/bangla-calendar/')->assertOk()->getContent();
        preg_match('/data-cal-panel="bd".*?<h2[^>]*>([^<]+)/s', $html, $bd);
        preg_match('/data-cal-panel="wb".*?<h2[^>]*>([^<]+)/s', $html, $wb);
        $this->assertStringContainsString('ভাদ্র ১৪৩৩', $bd[1]);
        $this->assertStringContainsString('শ্রাবণ ১৪৩৩', $wb[1]);
        $this->assertSame(2, preg_match_all('/role="cell"\s+aria-current="date"/', $html), 'today marked in both grids');
        $this->assertStringContainsString('data-cal-day', $html, 'days can be tapped (owner, 2026-11-27)');
        $this->assertStringContainsString('data-detail="রবিবার, ১৬ আগস্ট ২০২৬ · পশ্চিমবঙ্গে', $html, 'a Bangladesh day says the West Bengal date');
    }

    public function test_month_descriptions_carry_the_month_s_own_facts(): void
    {
        $this->checkAll();
        $a = $this->get('/janun/bangla-calendar/1433/ogrohayon/')->assertOk()->assertSee('Agrahayan 1433 Bengali calendar')->getContent();
        $b = $this->get('/janun/bangla-calendar/1433/poush/')->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]+)"/', $a, $da);
        preg_match('/<meta name="description" content="([^"]+)"/', $b, $db);
        $this->assertStringContainsString('পশ্চিমবঙ্গে ১৭ নভেম্বর', $da[1]);
        $this->assertStringContainsString('হেমন্ত', $da[1]);
        $this->assertStringContainsString('বাংলাদেশের বিজয় দিবস', html_entity_decode($db[1]));
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($da[1])));
        preg_match('/<meta name="description" content="([^"]+)"/', $this->get('/janun/bangla-calendar/')->getContent(), $di);
        $this->assertStringNotContainsString('১০ অগ্রহায়ণ', $di[1], 'no date in a snippet Google keeps for days');
    }

    public function test_home_line_says_whose_date_until_west_bengal_is_checked(): void
    {
        cache()->flush();
        $this->assertSame(['আজ ১১ অগ্রহায়ণ ১৪৩৩ (বাংলাদেশ)', ''], \App\Http\Controllers\BanglaCalendarController::todayLine());
    }

    public function test_a_month_matching_the_calculation_needs_one_page_a_different_day_needs_two(): void
    {
        BanglaCalendarDesk::seed(1433);
        BanglaCalendarDesk::seed(1434);
        $computed = collect(BanglaDate::wbComputed(1433))->take(12)->map->toDateString()->all();
        $claimed = $computed;
        $claimed[2] = CarbonImmutable::parse($computed[2])->addDay()->toDateString();
        // only one page, and it shows every claimed date
        Http::fake(['https://a.org/*' => Http::response('<p>'.implode(' ', array_map(fn ($d) => CarbonImmutable::parse($d)->format('j F Y'), $claimed)).'</p>'), '*' => Http::response('', 404)]);
        $this->answer = ['months' => array_map(fn ($i) => ['month' => BanglaDate::MONTHS[$i], 'first_day' => $claimed[$i]], range(0, 11)), 'sources' => [['url' => 'https://a.org/c']]];
        $this->assertSame(11, BanglaCalendarDesk::check(1433));
        $this->assertSame('held', BanglaMonth::where(['year' => 1433, 'month' => 3])->value('status'), 'a day away from the calculation needs two pages');
    }

    public function test_months_are_checked_against_the_published_panjika_page(): void
    {
        BanglaCalendarDesk::seed(1433);
        BanglaCalendarDesk::seed(1434);
        $page = fn ($name, $d, $w) => '<h1>'.$name.' ১৪৩৩</h1><p>১ '.$name.' ১৪৩৩ বঙ্গাব্দ শুরু '.$w.' '.$d.' — মোট ৩০ দিন।</p>';
        $this->published = [
            'https://myastrology.in/bangla-calendar/agrahayan-1433' => $page('অগ্রহায়ণ', '১৭ নভেম্বর', 'মঙ্গলবার'),
            'https://myastrology.in/bangla-calendar/shrabon-1433' => $page('শ্রাবণ', '১৮ জুলাই', 'শনিবার'),   // a day after our calculation (সংক্রান্তি 33 minutes before midnight)
            'https://myastrology.in/bangla-calendar/magh-1433' => $page('মাঘ', '২৫ জানুয়ারি', 'সোমবার'),   // far off: never taken
        ];
        $this->answer = ['months' => [], 'sources' => []];
        $before = \App\Http\Controllers\BanglaCalendarController::todayLine();   // cached: Bangladesh, nothing checked yet
        BanglaMonth::where(['year' => 1433, 'month' => 9])->update(['status' => 'verified']);   // the month after অগ্রহায়ণ, so today's date is known
        $this->assertSame(2, BanglaCalendarDesk::check(1433));
        $this->assertNotSame($before, \App\Http\Controllers\BanglaCalendarController::todayLine(), 'the home line changes at once, not after its hour in the cache');
        $this->assertSame('verified', BanglaMonth::where(['year' => 1433, 'month' => 8])->value('status'));
        $this->assertSame('2026-11-17', BanglaMonth::where(['year' => 1433, 'month' => 8])->first()->starts_on->toDateString());
        $this->assertSame('2026-07-18', BanglaMonth::where(['year' => 1433, 'month' => 4])->first()->starts_on->toDateString(), 'the published day is kept');
        $this->assertSame('held', BanglaMonth::where(['year' => 1433, 'month' => 10])->value('status'));

        // a confirmed month is never checked again, whatever a later page or search says
        $this->published = [];
        BanglaCalendarDesk::check(1433);
        $this->assertSame('verified', BanglaMonth::where(['year' => 1433, 'month' => 8])->value('status'));
    }

    public function test_festival_times_are_found_near_their_own_date_on_the_month_page(): void
    {
        // the real page's layout (2026-11-26): the date on one line, that day's times on the next, then the festival
        $day = fn ($d, $txt, $fest = '') => '<div><div>২৭ মাঘ'.$d.'</div><div>বুধবার · '.$txt.'</div>'.($fest ? '<div>🪔 '.$fest.'</div>' : '').'</div>';
        $this->published['https://myastrology.in/bangla-calendar/magh-1433'] = $day('৯ ফেব্রুয়ারি ২০২৭', 'শুক্লপক্ষ তৃতীয়া রাত ১১:৪০ পর্যন্ত')
            .$day('১০ ফেব্রুয়ারি ২০২৭', 'শুক্লপক্ষ চতুর্থী রাত ৩:০৪ (পরদিন) পর্যন্ত, তারপর পঞ্চমী')
            .$day('১১ ফেব্রুয়ারি ২০২৭', 'শুক্লপক্ষ পঞ্চমী রাত ৩:১৮ (পরদিন) পর্যন্ত, তারপর ষষ্ঠী', 'সরস্বতী পূজা')
            .$day('১২ ফেব্রুয়ারি ২০২৭', 'শুক্লপক্ষ ষষ্ঠী রাত ১১:৪০ (পরদিন) পর্যন্ত');
        $this->answer = ['events' => [['label' => 'পঞ্চমী তিথি', 'day' => '2027-02-11', 'start' => '2027-02-11 03:04', 'end' => '2027-02-12 03:18']], 'sources' => []];
        $this->assertSame(1, \App\Ai\FestivalDesk::find('saraswati', 2027));
        // the same times claimed for another day: they do not stand near that day on the page
        \App\Models\FestivalTime::query()->delete();
        $this->answer = ['events' => [['label' => 'পঞ্চমী তিথি', 'day' => '2027-02-11', 'start' => '2027-02-11 23:40', 'end' => '2027-02-12 23:40']], 'sources' => []];
        $this->assertSame(0, \App\Ai\FestivalDesk::find('saraswati', 2027));
    }

    public function test_a_country_s_own_days_show_only_in_its_own_calendar(): void
    {
        $this->checkAll();
        // 16 December 2026: ৩০ অগ্রহায়ণ in West Bengal, ১ পৌষ in Bangladesh; বিজয় দিবস belongs to Bangladesh only (owner, 2026-11-27)
        $g = \App\Http\Controllers\BanglaCalendarController::grids(1433, 8);
        $this->assertSame([], collect($g['wb']['days'])->firstWhere('n', 30)['events']);
        $g = \App\Http\Controllers\BanglaCalendarController::grids(1433, 9);
        $this->assertSame('বাংলাদেশের বিজয় দিবস', collect($g['bd']['days'])->firstWhere('n', 1)['events'][0][0]);
        $this->assertSame([], collect($g['wb']['days'])->filter(fn ($d) => $d['events'])->all(), 'nothing of Bangladesh in West Bengal\'s পৌষ');
        // the year page lists both, saying whose day it is
        $this->get('/janun/bangla-calendar/1433/')->assertSee('বাংলাদেশের বিজয় দিবস (বাংলাদেশ)')->assertSee('রবীন্দ্রজয়ন্তী (পঁচিশে বৈশাখ) (পশ্চিমবঙ্গ)');
    }

    public function test_checking_a_year_also_checks_the_next_first_of_boishakh(): void
    {
        BanglaCalendarDesk::seed(1434);
        $this->published['https://myastrology.in/bangla-calendar/baishakh-1435'] = '<p>১ বৈশাখ ১৪৩৫ বঙ্গাব্দ শুরু শুক্রবার ১৪ এপ্রিল — মোট ৩১ দিন।</p>';
        $this->answer = ['months' => [], 'sources' => []];
        BanglaCalendarDesk::check(1434);
        $this->assertSame('verified', BanglaMonth::where(['year' => 1435, 'month' => 1])->value('status'), 'so চৈত্র ১৪৩৪ can show West Bengal dates');
    }

    public function test_seo_headings_the_answer_sentence_and_the_next_puja(): void
    {
        $this->checkAll();
        \App\Models\FestivalTime::create(['festival' => 'saraswati', 'cycle' => 2027, 'label' => 'পঞ্চমী তিথি', 'day' => '2027-02-11',
            'starts_at' => '2027-02-10 21:34:00', 'ends_at' => '2027-02-11 21:48:00', 'status' => 'verified', 'sources' => ['https://a.org/p']]);
        $this->get('/janun/bangla-calendar/')->assertOk()->assertSee('<h1', false)->assertSee('আজ বাংলা কত তারিখ?')
            ->assertSee('পশ্চিমবঙ্গে <b>১০ অগ্রহায়ণ ১৪৩৩</b>, বাংলাদেশে <b>১১ অগ্রহায়ণ ১৪৩৩</b>', false)
            ->assertSee('পরের পুজো:')->assertSee('সরস্বতী পুজো')->assertSee('১১ ফেব্রুয়ারি ২০২৭');
        $this->get('/janun/bangla-calendar/1433/')->assertSee('<title>Bengali Calendar 2026-27 · বাংলা ক্যালেন্ডার ১৪৩৩', false);
        $this->get('/janun/bangla-calendar/1433/ogrohayon/')->assertSee('অগ্রহায়ণ ১৪৩৩ (নভেম্বর-ডিসেম্বর ২০২৬)')->assertSee('পরের পুজো:');
    }
}
