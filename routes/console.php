<?php

use Illuminate\Support\Facades\Schedule;

/*
 * The AI editor's routine (D44). Needs one server cron line (docs/DEPLOYMENT.md):
 *   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 * Every task only prepares work; nothing is published or sent without the owner. Pause = rest.
 */
Schedule::command('hatpakha:ai-shift inbox')->everyTenMinutes()->withoutOverlapping(30)->runInBackground();
Schedule::command('hatpakha:ai-shift backlog')->hourlyAt(25)->withoutOverlapping(120)->runInBackground();
Schedule::command('hatpakha:ai-shift daily')->dailyAt('09:30')->timezone('Asia/Kolkata')->withoutOverlapping(60);   // issue (from the 20th) + follow-ups
Schedule::command('hatpakha:ai-shift weekly')->weeklyOn(1, '08:30')->timezone('Asia/Kolkata')->withoutOverlapping(60); // Monday: picks + site health
Schedule::command('hatpakha:ai-shift newsletter')->weeklyOn(6, '18:00')->timezone('Asia/Kolkata');                     // Saturday: Sunday newsletter draft
Schedule::command('hatpakha:ai-shift monthly')->monthlyOn(1, '08:15')->timezone('Asia/Kolkata');                       // 1st: last month's report
Schedule::command('hatpakha:ai-shift chief')->hourlyAt(45)->withoutOverlapping(30);                                  // editor-in-chief checks (no AI)
Schedule::command('hatpakha:ai-shift brief')->dailyAt('08:00')->timezone('Asia/Kolkata');
