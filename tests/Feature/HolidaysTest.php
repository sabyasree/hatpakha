<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\HolidayDesk;
use App\Ai\JanunDesk;
use App\Models\Holiday;
use App\Models\HolidayList;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D314: government holiday lists from the official notice; each date shown only when two pages show it with its name. */
class HolidaysTest extends TestCase
{
    use RefreshDatabase;

    private array $answer = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 06:00:00');
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        JanunDesk::$resolve = fn ($h) => ['93.184.216.34'];
        Claude::$fake = fn ($purpose, $user) => $this->answer;
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        JanunDesk::$resolve = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bangladesh(): array
    {
        $days = [['2027-02-21', 'শহীদ দিবস ও আন্তর্জাতিক মাতৃভাষা দিবস', 'Shaheed Day'], ['2027-03-26', 'স্বাধীনতা ও জাতীয় দিবস', 'Independence Day'], ['2027-04-14', 'বাংলা নববর্ষ', 'Bengali New Year'],
            ['2027-05-01', 'মে দিবস', 'May Day'], ['2027-05-20', 'বুদ্ধ পূর্ণিমা', 'Buddha Purnima'], ['2027-08-05', 'জুলাই গণঅভ্যুত্থান দিবস', 'July Uprising Day'], ['2027-08-24', 'জন্মাষ্টমী', 'Janmashtami'],
            ['2027-10-19', 'বিজয়া দশমী', 'Vijaya Dashami'], ['2027-12-16', 'বিজয় দিবস', 'Victory Day'], ['2027-12-25', 'যিশুখ্রিষ্টের জন্মদিন', 'Christmas'], ['2027-02-04', 'শবে বরাত', 'Shab-e-Barat'], ['2027-03-10', 'ঈদুল ফিতর', 'Eid ul-Fitr']];

        return $days;
    }

    public function test_a_list_from_the_notice_with_each_date_on_two_pages(): void
    {
        $days = $this->bangladesh();
        $page = '<ul>'.implode('', array_map(fn ($d) => '<li>'.\Carbon\Carbon::parse($d[0])->format('j F Y').': '.$d[2].'</li>', $days)).'</ul>';
        $page2 = '<ul>'.implode('', array_map(fn ($d) => '<li>'.\App\Support\Bn::num(\Carbon\Carbon::parse($d[0])->format('j')).' '.\App\Support\Bn::MONTHS[\Carbon\Carbon::parse($d[0])->month - 1].': '.$d[1].'</li>', array_slice($days, 0, 11))).'</ul>';
        Http::fake(['https://a.org/*' => Http::response($page), 'https://b.bd/*' => Http::response($page2), '*' => Http::response('', 404)]);
        $this->answer = ['published' => true, 'notice' => 'জনপ্রশাসন মন্ত্রণালয়ের প্রজ্ঞাপন, ৫ নভেম্বর ২০২৬', 'notice_url' => 'http://insecure.example/x',
            'holidays' => array_map(fn ($d) => ['date' => $d[0], 'name_bn' => $d[1], 'name_en' => $d[2], 'kind' => 'সাধারণ ছুটি', 'moon' => str_contains($d[2], 'Eid')], $days)
                + [99 => ['date' => '2026-12-25', 'name_bn' => 'গত বছরের', 'name_en' => 'Old', 'kind' => '', 'moon' => false]],
            'sources' => [['url' => 'https://a.org/l'], ['url' => 'https://b.bd/l']]];
        $list = HolidayList::firstOrCreate(['country' => 'bangladesh', 'year' => 2027]);
        // মে দিবস has no word of its own on the Bengali page ("মে" is the month, "দিবস" every holiday's): one page only, held
        $this->assertSame(10, HolidayDesk::find($list));
        $this->assertSame('held', Holiday::where('name', 'মে দিবস')->value('status'));
        $list->refresh();
        $this->assertTrue($list->isLive());
        $this->assertNull($list->notice_url, 'only https links');
        $this->assertSame('held', Holiday::where('name', 'ঈদুল ফিতর')->value('status'), 'only one page shows it');
        $this->assertSame(0, Holiday::where('name', 'গত বছরের')->count(), 'another year is ignored');

        $html = $this->get('/janun/holidays/bangladesh-2027/')->assertOk()
            ->assertSee('<title>Bangladesh Holiday List 2027 · সরকারি ছুটির তালিকা ২০২৭ (বাংলাদেশ)', false)
            ->assertSee('বিজয় দিবস')->assertSee('জনপ্রশাসন মন্ত্রণালয়ের প্রজ্ঞাপন')->assertSee('আরও ২টি দিন মিলিয়ে দেখা হচ্ছে')->assertDontSee('ঈদুল ফিতর')->getContent();
        $this->assertStringContainsString('লম্বা ছুটি', $html, 'a Thursday or Sunday touches the Bangladesh weekend');
        $this->get('/janun/holidays/2027/')->assertOk()->assertSee('/janun/holidays/bangladesh-2027/', false);
        $this->get('/janun/holidays/west-bengal-2027/')->assertNotFound();
        $this->assertStringContainsString('/janun/holidays/bangladesh-2027/', $this->get('/sitemap-pages.xml')->getContent());
    }

    public function test_no_list_until_the_notice_is_out(): void
    {
        $this->answer = ['published' => false];
        $list = HolidayList::firstOrCreate(['country' => 'west-bengal', 'year' => 2027]);
        $this->assertSame(0, HolidayDesk::find($list));
        $this->assertFalse($list->fresh()->isLive());
        $this->get('/janun/holidays/west-bengal-2027/')->assertNotFound();
        $this->get('/janun/holidays/2027/')->assertNotFound();
        // the daily job asks at most once a day
        $this->assertTrue(HolidayDesk::run(\Carbon\CarbonImmutable::now('Asia/Kolkata')));
        $this->assertFalse(HolidayDesk::run(\Carbon\CarbonImmutable::now('Asia/Kolkata')));
    }
}
