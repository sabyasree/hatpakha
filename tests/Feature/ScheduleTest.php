<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D57: shifts run in-process (Hostinger's cron PHP has no proc_open), and they really run. */
class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_shift_is_an_in_process_callback_and_runs(): void
    {
        $events = collect(app(Schedule::class)->events());
        $this->assertCount(20, $events);   // 12 shifts and jobs + the nightly backup (D92) + relaunch letters (D104) + reader insights (D119) + the daily phone note (D128) + push announcements (D132) + search index + ebooks
        $events->each(fn ($e) => $this->assertInstanceOf(CallbackEvent::class, $e, 'No child processes: '.$e->description));

        $chief = $events->first(fn ($e) => $e->description === 'ai-shift chief');
        $chief->run($this->app);
        $this->assertNotNull(Setting::get('ai.heartbeat'), 'The shift ran and the clock ticked');
    }
}
