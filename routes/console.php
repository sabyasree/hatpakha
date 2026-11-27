<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// D271 (audit): the "already running" locks live in the database, not the file cache that every release empties;
// a job still running during a release could otherwise start twice (a letter batch, a post, a paid AI call).
Schedule::useCache('database');

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
Schedule::call(fn () => \App\Ai\Batch::run())->name('half-price AI batches')->hourlyAt(35)->withoutOverlapping(30);   // D296
Schedule::call($shift('chief'))->name('ai-shift chief')->hourlyAt(45)->withoutOverlapping(30);                                  // editor-in-chief checks (no AI)
Schedule::call($shift('brief'))->name('ai-shift brief')->dailyAt('08:00')->timezone('Asia/Kolkata');
// Newsletters (D58): after the owner's click, a small batch every minute until all are sent.
// D113: rewrites asked for by human readers' feedback, and turning feedback into standing rules
Schedule::call(fn () => \App\Ai\Rewriter::runQueued())->name('feedback rewrites')->everyMinute()->withoutOverlapping(30);
// D104: apology letters to writers of old unanswered submissions. Runs only on hatpakha.com, with real mail, after the owner approved the text.
// D119: what readers did yesterday (Google Analytics + Search Console), before the morning brief. No AI involved.
Schedule::call(fn () => \App\Support\ReaderInsights::refresh())->name('reader insights')->dailyAt('07:30')->timezone('Asia/Kolkata')->withoutOverlapping(30);
Schedule::call(fn () => \App\Models\Post::where('status', 'published')->where('updated_at', '>=', now()->subDays(2))->each(fn ($p) => \App\Support\BnSearch::index($p)))->name('search index')->dailyAt('03:10')->timezone('Asia/Kolkata');   // spelling-forgiving search: keys with the latest writers and tags
Schedule::call(function () {
    \App\Support\ReadingHistory::prune();
    \App\Support\SiteErrors::prune();   // D191: errors older than thirty days
})->name('old reading history')->dailyAt('03:45')->timezone('Asia/Kolkata');   // D166: a year old is deleted
Schedule::call(fn () => \App\Models\SentLetter::where('sent_at', '<', now()->subYears(2))->delete())->name('old letters')->dailyAt('03:40')->timezone('Asia/Kolkata');   // D161: copies of letters to writers are kept two years
Schedule::call(fn () => \App\Support\IssueEbook::buildDue())->name('ebooks')->dailyAt('04:20')->timezone('Asia/Kolkata')->withoutOverlapping(60);   // each issue's e-book, rebuilt when its pieces change; waits for the owner's approval
Schedule::call(fn () => \App\Support\PushNotifier::announceBatch())->name('push announcements')->everyMinute()->withoutOverlapping(10);   // D132: the owner's announcement, 200 phones a minute
Schedule::call(fn () => \App\Support\PushNotifier::daily())->name('push daily')->dailyAt('19:40')->timezone('Asia/Kolkata')->withoutOverlapping(30);   // D128: the day's new pieces, once, to phones that asked
Schedule::call(fn () => \App\Support\RelaunchLetters::send())->name('relaunch letters')->hourlyAt(50)->withoutOverlapping(30);
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
    \App\Support\Milestones::detect();      // D78: once a day, writers' milestones for the owner to approve (no AI)
})->name('follow notices')->everyTenMinutes()->withoutOverlapping(10);


// D92: nightly database backup (plain PHP, no child process), kept 7 nights in storage/app/private/backups.
Schedule::call(fn () => \Illuminate\Support\Facades\Artisan::call('hatpakha:backup-db', ['--label' => 'nightly', '--keep' => 7]))
    ->name('nightly db backup')->dailyAt('02:40')->timezone('Asia/Kolkata')->withoutOverlapping(60);

// Facebook Page (owner, 2026-11-12): posts the owner timed, every 5 minutes; automatic mode (a desk switch, off by default)
// posts one ready post at 10:05 and 19:35, good reading times in Bengal. Live site only (App\Support\FacebookPage::ready).
Schedule::call(fn () => \App\Support\FacebookPage::due())->name('facebook timed posts')->everyFiveMinutes()->withoutOverlapping(10);
// D182 (owner, 2026-11-13: "I want fully automatic"): the marketing plan. Each evening tomorrow's riddle card and reel
// are planned (again at dawn, harmlessly); every five minutes whatever is due goes out in its own format; the analyst
// reads the numbers each morning and writes the week's lessons on Sunday night. docs/MARKETING.md
Schedule::call(fn () => \App\Support\MarketingPlan::plan())->name('marketing plan tomorrow')->dailyAt('20:20')->timezone('Asia/Kolkata');
Schedule::call(fn () => \App\Support\MarketingPlan::plan(now('Asia/Kolkata')))->name('marketing plan today')->dailyAt('05:40')->timezone('Asia/Kolkata');
Schedule::call(fn () => \App\Support\MarketingPlan::run())->name('marketing posts')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::call(function () {
    \App\Support\MarketingStats::collect();
    \App\Support\MarketingAnalyst::visits();   // D183: visits each tagged post brought (Google Analytics)
})->name('marketing numbers')->dailyAt('07:10')->timezone('Asia/Kolkata')->withoutOverlapping(30);
Schedule::call(fn () => \App\Support\MarketingAnalyst::review())->name('marketing review')->weeklyOn(0, '21:30')->timezone('Asia/Kolkata');
Schedule::call(fn () => \App\Support\PushNotifier::streakSaver())->name('streak reminder')->dailyAt('20:45')->timezone('Asia/Kolkata')->withoutOverlapping(30);

// D175: পাঠকের কলম. Monday morning the week's theme opens and readers are invited; the AI reads entries through the
// week; Saturday evening it proposes the best ten. Readers' words go public only on the owner's click.
Schedule::call(fn () => \App\Support\Kolom::openWeek())->name('reader theme opens')->weeklyOn(1, '06:10')->timezone('Asia/Kolkata');
Schedule::call(fn () => \App\Support\Kolom::screen())->name('reader entries screened')->hourlyAt(25);
Schedule::call(fn () => \App\Support\Kolom::pickWeek())->name('reader entries picked')->weeklyOn(6, '18:00')->timezone('Asia/Kolkata');
Schedule::call(fn () => \App\Support\Kolom::autoPublish())->name('reader picks published')->weeklyOn(0, '08:00')->timezone('Asia/Kolkata');   // D176
Schedule::call(fn () => \App\Support\WordReports::review())->name('reported game words reviewed')->hourlyAt(40);   // D179
Schedule::call(fn () => \App\Support\TextPrints::backfill())->name('text fingerprints of older pieces')->everyFiveMinutes()->withoutOverlapping(10);   // D248
Schedule::call(fn () => \App\Support\GameTitles::award())->name('game titles of the month and the year')->dailyAt('00:20')->timezone('Asia/Kolkata');   // D243

// D188 (owner, 2026-11-14: "the team should handle everything automatically"): personal invitations to the regular
// writers for the ফিরে আসা সংখ্যা. Letters are written a few at a time, wait twelve hours in /desk → লেখকদের আমন্ত্রণ,
// then a few go out each hour of the Kolkata day; one reminder nine days on; what the writers send is marked for the issue.
Schedule::call(fn () => \App\Support\WriterInvites::draft())->name('writer invitations written')->everyTenMinutes()->withoutOverlapping(20);
Schedule::call(function () {
    \App\Support\WriterInvites::send();
    \App\Support\WriterInvites::remind();
    \App\Support\WriterInvites::match();
})->name('writer invitations sent')->hourlyAt(15)->withoutOverlapping(30);

// D189 (owner: "auto"): readers' comments under our Facebook and Instagram posts are read every half hour and the safe
// ones answered in the Kolkata day; the rest wait for the owner on the marketing page.
Schedule::call(function () {
    \App\Support\CommentReplies::fetch();
    \App\Support\CommentReplies::answer();
})->name('comment replies')->everyThirtyMinutes()->withoutOverlapping(20);

// D208 (owner: "do all these"): the jobs handed to the AI team (small fixes, covers, clear comments, the readers'
// letter, small monthly items, strong pieces). Each has its own switch in /desk → নিজে থেকে চলা কাজ.
Schedule::call(function () {
    rescue(fn () => \App\Models\Post::goLive(), null, true);   // D220: "scheduled" becomes "published" when its time comes
    \App\Support\Handover::run();
})->name('handed-over jobs')->everyTenMinutes()->withoutOverlapping(20);

// D217 (owner: "yes, add daily comment email for writers"): one e-mail a day to a writer whose pieces got new comments.
Schedule::call(function () {   // D271: one failing job never stops the others
    foreach ([[\App\Support\CommentNews::class, 'send'], [\App\Support\CommentNews::class, 'replies'], [\App\Support\PieceLive::class, 'send'], [\App\Support\ConfirmReminder::class, 'send'], [\App\Support\SerialReminders::class, 'send']] as $job) {
        rescue(fn () => $job(), null, true);
    }
})->name('comments told to writers')->everyTenMinutes()->withoutOverlapping(10);   // D217, D252: comments, replies, a piece going live

// D328, D330: Saturday morning the AI writes next week's পত্রিকা-কুইজ from the week's pieces, a second AI reading checks
// every question against its piece, and the checked quiz goes live by itself (owner: "fully automatic").
Schedule::call(fn () => \App\Support\WeeklyQuiz::draft())->name('weekly quiz draft')->weeklyOn(6, '10:15')->timezone('Asia/Kolkata')->withoutOverlapping(30);
Schedule::call(fn () => \App\Support\WeeklyQuiz::draft())->name('weekly quiz draft again')->weeklyOn(6, '19:15')->timezone('Asia/Kolkata')->withoutOverlapping(30);
// D330: the quiz goes live by itself once checked; a last try just after midnight on Sunday, for the week that has begun
Schedule::call(fn () => \App\Support\WeeklyQuiz::draft(\App\Support\WeeklyQuiz::week()))->name('weekly quiz last try')->weeklyOn(0, '00:05')->timezone('Asia/Kolkata')->withoutOverlapping(30);
// D330: অক্ষরজাল learns words from the magazine (a word in 8 or more pieces), counted from the next day
Schedule::call(fn () => \App\Support\Akshorjal::learn())->name('akshorjal learns words')->dailyAt('03:30')->timezone('Asia/Kolkata')->withoutOverlapping(60);
