<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * The AI team's routine (D44). Needs one server cron line (docs/DEPLOYMENT.md):
 *   * * * * * /usr/bin/php /path/to/app/artisan schedule:run >> /path/to/app/storage/logs/cron.log 2>&1
 * Every task only prepares work; nothing is published or sent without the owner. Pause = rest.
 *
 * D57: each shift runs INSIDE the schedule:run process (Schedule::call), not as a child process.
 * Hostinger's PHP for cron has proc_open switched off, so Schedule::command() and runInBackground()
 * failed every time ("The Process class relies on proc_open"). Separate cron minutes still run
 * side by side, and withoutOverlapping() keeps a long shift from starting twice.
 */
$shift = fn (string $name) => fn () => Artisan::call('hatpakha:ai-shift', ['task' => $name]);

Schedule::call($shift('inbox'))->name('ai-shift inbox')->everyTenMinutes()->withoutOverlapping(30);
Schedule::call($shift('backlog'))->name('ai-shift backlog')->hourlyAt(25)->withoutOverlapping(120);
Schedule::call($shift('daily'))->name('ai-shift daily')->dailyAt('09:30')->timezone('Asia/Kolkata')->withoutOverlapping(60);    // issue (from the 20th) + follow-ups + marketing
Schedule::call($shift('weekly'))->name('ai-shift weekly')->weeklyOn(1, '08:30')->timezone('Asia/Kolkata')->withoutOverlapping(60); // Monday: picks, health, plan, certificates
Schedule::call($shift('newsletter'))->name('ai-shift newsletter')->weeklyOn(6, '18:00')->timezone('Asia/Kolkata');                // Saturday: Sunday newsletter draft
Schedule::call($shift('monthly'))->name('ai-shift monthly')->monthlyOn(1, '08:15')->timezone('Asia/Kolkata');                     // 1st: report, insights, collection, team review
Schedule::call($shift('chief'))->name('ai-shift chief')->hourlyAt(45)->withoutOverlapping(30);                                  // editor-in-chief checks (no AI)
Schedule::call($shift('brief'))->name('ai-shift brief')->dailyAt('08:00')->timezone('Asia/Kolkata');
// Newsletters (D58): after the owner's click, a small batch every minute until all are sent.
Schedule::call(fn () => \App\Support\NewsletterSender::batch())->name('newsletter batch')->everyMinute()->withoutOverlapping(10);
// Covers the owner asked for from /desk (D60): two a minute, inside this process.
Schedule::call(fn () => \App\Ai\Illustrator::drawRequested())->name('draw covers')->everyMinute()->withoutOverlapping(10);
// Work the owner gives the team by hand (D72), and the strategist's "new plan now" (D63): AI work takes longer
// than a web request may, so it is done here, a couple of requests a minute.
Schedule::call(function () {
    \App\Ai\Strategist::runIfRequested();
    \App\Ai\TeamJobs::runQueued();
})->name('team requests')->everyMinute()->withoutOverlapping(30);
// Followers hear about a writer's new piece (D64). Not an AI job: runs even while the AI team rests.
Schedule::call(function () {
    \App\Support\FollowNotifier::run();
    \App\Models\Certificate::autoIssue();   // D73: certificates for newly published pieces (no AI)
})->name('follow notices')->everyTenMinutes()->withoutOverlapping(10);

