<?php

use Illuminate\Support\Facades\Schedule;

/*
 * The AI editor's routine (D44). Needs one server cron line (docs/DEPLOYMENT.md):
 *   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 * Every task only prepares work; nothing is published or sent without the owner. Pause = rest.
 */
Schedule::command('hatpakha:ai-shift inbox')->everyTenMinutes()->withoutOverlapping(30)->runInBackground();
Schedule::command('hatpakha:ai-shift backlog')->hourlyAt(25)->withoutOverlapping(120)->runInBackground();
Schedule::command('hatpakha:ai-shift issue')->dailyAt('09:30')->timezone('Asia/Kolkata')->withoutOverlapping(60);
Schedule::command('hatpakha:ai-shift brief')->dailyAt('08:00')->timezone('Asia/Kolkata');
