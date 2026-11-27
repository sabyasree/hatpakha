<?php

namespace Tests\Feature;

use App\Ai\BanglaCalendarDesk;
use App\Ai\JanunDesk;
use App\Ai\TithiDesk;
use App\Models\BanglaMonth;
use App\Models\TithiDay;
use App\Support\BanglaDate;
use App\Support\Tithi;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D310: the tithi of the day: the published বিশুদ্ধ সিদ্ধান্ত page, shown only when our own calculation agrees. */
class TithiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_calculation_matches_published_times(): void
    {
        // published by the বিশুদ্ধ সিদ্ধান্ত month pages (myastrology.in, read 2026-11-27); ours within three minutes
        foreach ([['2027-02-11 06:14', 5, '2027-02-12 03:18'], ['2026-11-24 06:00', 15, '2026-11-24 20:22'], ['2026-12-24 06:00', 15, '2026-12-24 06:57'], ['2026-11-17 06:00', 8, '2026-11-18 06:05']] as [$at, $n, $end]) {
            $t = CarbonImmutable::parse($at, 'Asia/Kolkata');
            $this->assertSame($n, Tithi::at($t), $at);
            $this->assertLessThanOrEqual(5, abs(Tithi::endAfter($t)->diffInMinutes(CarbonImmutable::parse($end, 'Asia/Kolkata'), false)), $at);
        }
        $this->assertSame('পূর্ণিমা', Tithi::name(15));
        $this->assertSame('কৃষ্ণপক্ষ তৃতীয়া', Tithi::name(18));
        $this->assertSame(20, Tithi::number('কৃষ্ণপক্ষ', 'পঞ্চমী'));
    }

    public function test_a_month_page_is_read_and_checked(): void
    {
        Carbon::setTestNow('2027-02-10 01:00:00');
        JanunDesk::$resolve = fn ($h) => ['93.184.216.34'];
        $day = fn ($bn, $g, $w, $txt) => '<div><div>'.$bn.' মাঘ'.$g.' ফেব্রুয়ারি 2027</div><div>'.$w.' · '.$txt.'সূর্যোদয় সকাল 6:15 · সূর্যাস্ত সন্ধ্যা 5:26</div></div>';
        Http::preventStrayRequests();
        Http::fake(['https://myastrology.in/*' => Http::response(
            $day('২৭', '10', 'বুধবার', 'শুক্লপক্ষ চতুর্থী রাত 3:04 (পরদিন) পর্যন্ত, তারপর পঞ্চমী উত্তরভাদ্র ভোর 4:55 (পরদিন) পর্যন্ত, তারপর রেবতী')
            .$day('২৮', '11', 'বৃহস্পতিবার', 'শুক্লপক্ষ পঞ্চমী রাত 3:18 (পরদিন) পর্যন্ত, তারপর ষষ্ঠী রেবতী ভোর 5:42 (পরদিন) পর্যন্ত, তারপর অশ্বিনী')
            .$day('২৯', '12', 'শুক্রবার', 'শুক্লপক্ষ ষষ্ঠী রাত 9:00 পর্যন্ত, তারপর সপ্তমী অশ্বিনী ভোর 5:56 (পরদিন) পর্যন্ত, তারপর ভরণী'))]);   // 12 Feb: a wrong end time
        $this->assertSame(2, TithiDesk::fill('https://myastrology.in/bangla-calendar/magh-1433'));
        $this->assertSame('শুক্লপক্ষ পঞ্চমী, মধ্যরাতের পর ৩:১৮ পর্যন্ত (পরদিন), তারপর শুক্লপক্ষ ষষ্ঠী', TithiDay::whereDate('day', '2027-02-11')->first()->text());
        $held = TithiDay::whereDate('day', '2027-02-12')->first();
        $this->assertSame('held', $held->status, 'our calculation disagrees with 9pm');
        $this->assertStringContainsString('calculated', $held->note);

        // the calendar shows the checked tithi, today and in a tapped day
        foreach ([1433, 1434] as $y) {
            BanglaCalendarDesk::seed($y);
        }
        BanglaMonth::query()->update(['status' => 'verified']);
        BanglaDate::flush();
        Carbon::setTestNow('2027-02-11 01:00:00');
        $this->get('/janun/bangla-calendar/')->assertOk()->assertSee('আজকের তিথি: শুক্লপক্ষ পঞ্চমী')
            ->assertSee('তিথি: শুক্লপক্ষ চতুর্থী, মধ্যরাতের পর ৩:০৪ পর্যন্ত (পরদিন)', false)
            ->assertDontSee('ষষ্ঠী, রাত ৯:০০');
        JanunDesk::$resolve = null;
        Carbon::setTestNow();
        BanglaDate::flush();
    }

    public function test_a_week_already_stored_is_not_fetched_again(): void
    {
        Carbon::setTestNow('2026-11-27 01:00:00');
        foreach (range(0, 6) as $i) {
            TithiDay::create(['day' => CarbonImmutable::create(2026, 11, 27)->addDays($i)->format('Y-m-d 00:00:00'), 'tithi' => 18 + $i, 'turns' => [], 'status' => 'verified']);
            TithiDay::create(['day' => CarbonImmutable::create(2027, 1, 1)->addDays($i)->format('Y-m-d 00:00:00'), 'tithi' => 1 + $i, 'turns' => [], 'status' => 'verified']);
        }
        Http::preventStrayRequests();
        Http::fake();
        $this->assertFalse(TithiDesk::run(CarbonImmutable::now('Asia/Kolkata')), 'both weeks are there: no fetch (the last day counts on SQLite too)');
        Http::assertNothingSent();
        Carbon::setTestNow();
    }
}
