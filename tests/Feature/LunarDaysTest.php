<?php

namespace Tests\Feature;

use App\Models\TithiDay;
use App\Support\LunarDays;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D313: the year's পূর্ণিমা / অমাবস্যা / একাদশী from checked tithi days only. */
class LunarDaysTest extends TestCase
{
    use RefreshDatabase;

    /** Six full-moon fortnights: a day with পূর্ণিমা at sunrise and its start/end recorded on checked days. */
    private function moons(int $n, string $status = 'verified'): void
    {
        $base = CarbonImmutable::parse('2027-01-21 21:30', 'Asia/Kolkata');
        for ($i = 0; $i < $n; $i++) {
            $start = $base->addDays(29 * $i);
            $end = $start->addHours(20);
            TithiDay::create(['day' => $start->format('Y-m-d 00:00:00'), 'tithi' => 14, 'status' => $status, 'turns' => [[$start->utc()->toIso8601String(), 15]]]);
            TithiDay::create(['day' => $start->addDay()->format('Y-m-d 00:00:00'), 'tithi' => 15, 'status' => $status, 'turns' => [[$end->utc()->toIso8601String(), 16]]]);
        }
    }

    public function test_the_year_list(): void
    {
        Carbon::setTestNow('2026-11-27 06:00:00');
        $this->moons(6);
        $list = LunarDays::year('purnima', 2027);
        $this->assertCount(6, $list);
        $this->assertSame('2027-01-22', $list[0]['day']->toDateString(), 'the day it holds at sunrise');
        $html = $this->get('/janun/puja/purnima-2027/')->assertOk()
            ->assertSee('Purnima 2027 Dates &amp; Time · পূর্ণিমা ২০২৭', false)
            ->assertSee('পরের পূর্ণিমা: <b>২২ জানুয়ারি ২০২৭, শুক্রবার</b>', false)
            ->assertSee('রাত ৯:৩০')->getContent();
        $this->assertStringContainsString('"BreadcrumbList"', $html);
        $this->get('/janun/puja/amavasya-2027/')->assertNotFound();   // fewer than six known
        Carbon::setTestNow();
    }

    public function test_held_days_and_gaps_are_never_used(): void
    {
        $this->moons(6, 'held');
        $this->assertSame([], LunarDays::year('purnima', 2027));
        $this->get('/janun/puja/purnima-2027/')->assertNotFound();
        TithiDay::query()->delete();
        // a start without its end (the next day not checked): left out
        TithiDay::create(['day' => '2027-01-21 00:00:00', 'tithi' => 14, 'status' => 'verified', 'turns' => [[CarbonImmutable::parse('2027-01-21 21:30', 'Asia/Kolkata')->utc()->toIso8601String(), 15]]]);
        $this->assertSame([], LunarDays::year('purnima', 2027));
    }
}
