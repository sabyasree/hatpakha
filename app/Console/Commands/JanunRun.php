<?php

namespace App\Console\Commands;

use App\Ai\AiSwitch;
use App\Ai\BanglaCalendarDesk;
use App\Ai\FestivalDesk;
use App\Ai\JanunDesk;
use App\Models\BanglaMonth;
use App\Models\FestivalTime;
use App\Models\JanunPage;
use App\Models\Setting;
use App\Support\BanglaDate;
use App\Support\FestivalPlan;
use App\Support\Handover;
use App\Support\JanunPlan;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The first round of the জেনে রাখুন jobs at once, instead of one an hour (owner, 2026-11-26: "can we run the 1st set
 * of everything now"): the West Bengal months, every festival's times, and the first pages drafted, checked and given
 * their dates. The same checks as the hourly job; nothing is published early (pages still go out Mon/Wed/Fri 10am).
 * Run by the deploy workflow when a release's title has [run-janun].
 */
class JanunRun extends Command
{
    protected $signature = 'hatpakha:janun-run {--pages=3 : how many পাতা to draft and check now}';

    protected $description = 'জেনে রাখুন: the first round now (calendar months, festival times, the first pages); same checks, nothing published early';

    public function handle(): int
    {
        if (! config('ai.enabled') || blank(config('ai.anthropic.key'))) {
            $this->error('The AI is not set up on this site (AI_ENABLED / ANTHROPIC key).');

            return self::FAILURE;
        }
        if (AiSwitch::paused() || ! Handover::on('janun')) {
            $this->error('The AI is paused, or the জেনে রাখুন switch is off (/desk → নিজে থেকে চলা কাজ).');

            return self::FAILURE;
        }
        $now = CarbonImmutable::now('Asia/Kolkata');
        JanunDesk::seed();

        $this->line('1. বাংলা ক্যালেন্ডার (West Bengal months)');
        [$year] = BanglaDate::bd($now);
        foreach ([$year, $year + 1] as $y) {
            BanglaCalendarDesk::seed($y);
        }
        foreach ([$year, $year + 1] as $y) {
            if (BanglaMonth::where('year', $y)->where('status', '!=', 'verified')->exists()) {
                Setting::put("calendar.bn.tries.{$y}", 0);   // a run by hand starts the hourly tries afresh
                $ok = BanglaCalendarDesk::check($y);
                $this->line("   {$y}: {$ok} of 12 months confirmed");
                foreach (BanglaMonth::where('year', $y)->where('status', 'held')->orderBy('month')->get() as $m) {
                    $this->line('      held '.BanglaDate::EN[$m->month - 1].' (calculated '.$m->starts_on->toDateString().'): '.$m->note);
                }
            } else {
                $this->line("   {$y}: already confirmed");
            }
        }

        $this->line('1b. তিথি (the published month pages checked by our calculation)');
        foreach ([$now, $now->addDays(35)] as $d) {
            if ($url = BanglaCalendarDesk::sourceUrlFor(CarbonImmutable::create($d->year, $d->month, $d->day))) {
                $this->line('   '.basename($url).': '.\App\Ai\TithiDesk::fill($url).' days confirmed');
            }
        }

        $this->line('2. Festival times');
        foreach (FestivalDesk::years($now) as $y) {
            foreach (array_keys(FestivalPlan::FESTIVALS) as $key) {
                if (FestivalDesk::complete($key, $y)) {
                    continue;
                }
                Setting::put("festival.tries.{$key}.{$y}", 0);
                $kept = rescue(fn () => FestivalDesk::find($key, $y), 0, false);
                $held = FestivalTime::where('festival', $key)->where('cycle', $y)->where('status', 'held')->get()->map(fn ($t) => $t->label.': '.$t->note)->implode('; ');
                $this->line("   {$key} {$y}: {$kept} time(s) confirmed".($held ? " (held: {$held})" : ''));
            }
        }

        $this->line('3. জেনে রাখুন pages');
        $order = array_keys(JanunPlan::PAGES);
        $todo = JanunPage::whereIn('status', ['planned', 'drafted'])->where('tries', '<', 3)->get()
            ->sortBy(fn ($p) => array_search($p->key, $order, true))->take((int) $this->option('pages'));
        foreach ($todo as $p) {
            if ($p->status === 'planned' && ! JanunDesk::draft($p)) {
                $this->line("   {$p->key}: the draft failed (tried again by the hourly job)");

                continue;
            }
            JanunDesk::check($p->fresh(), $now);
            $p->refresh();
            if ($p->status === 'drafted') {
                $this->line("   {$p->key}: drafted; the check's web search did not answer (the hourly job checks it again)");

                continue;
            }
            $this->line("   {$p->key}: {$p->status}".($p->publish_at ? ' → goes live '.$p->publish_at->timezone('Asia/Kolkata')->format('D j M, g:ia') : ''));
        }
        $this->info('Done. Results: /janun/bangla-calendar/, /janun/puja/pujor-somoy/ (once two festivals are confirmed), and the audit log in /desk.');

        return self::SUCCESS;
    }
}
