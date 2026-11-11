<?php

namespace App\Support;

use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\EditorTask;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The site's health, week by week or month by month, in the desk (owner, 2026-11-12: "instead of email").
 * Every number is worked out from what the site already keeps; nothing is sent anywhere. Each part gets a plain
 * verdict (ঠিক আছে / একবার দেখুন / এখনই দেখুন) so the owner can see in one glance where to look.
 */
final class HealthReport
{
    public const OK = 'ok';

    public const LOOK = 'look';

    public const URGENT = 'urgent';

    /** @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon} from, to, and the period before (same length) */
    public static function range(string $period, int $back = 0): array
    {
        $now = now('Asia/Kolkata');
        if ($period === 'month') {
            $from = $now->copy()->startOfMonth()->subMonthsNoOverflow($back);
            $to = $from->copy()->endOfMonth();
            $pFrom = $from->copy()->subMonthNoOverflow();
            $pTo = $pFrom->copy()->endOfMonth();
        } else {
            $from = $now->copy()->startOfWeek(Carbon::MONDAY)->subWeeks($back);
            $to = $from->copy()->endOfWeek(Carbon::SUNDAY);
            $pFrom = $from->copy()->subWeek();
            $pTo = $to->copy()->subWeek();
        }
        if ($to->greaterThan($now)) {   // this week/month so far: compare with the same number of days before
            $days = $from->diffInDays($now);
            $to = $now->copy();
            $pTo = $pFrom->copy()->addDays((int) floor($days))->endOfDay();
        }

        return [$from, $to, $pFrom, $pTo];
    }

    public static function build(string $period = 'week', int $back = 0): array
    {
        $period = $period === 'month' ? 'month' : 'week';
        $back = max(0, min(24, $back));
        [$from, $to, $pFrom, $pTo] = self::range($period, $back);
        $utc = fn (Carbon $c) => $c->copy()->utc();

        // ---- Readers ----
        $reads = fn (Carbon $a, Carbon $b) => (int) DB::table('post_views')->whereBetween('day', [$a->toDateString(), $b->toDateString()])->sum('views');
        $now = $reads($from, $to);
        $before = $reads($pFrom, $pTo);
        $change = $before > 0 ? (int) round(100 * ($now - $before) / $before) : null;
        $src = DB::table('post_views')->whereBetween('day', [$from->toDateString(), $to->toDateString()])->selectRaw('sum(views) v, sum(search) s, sum(social) so, sum(internal) i')->first();
        $top = DB::table('post_views')->whereBetween('day', [$from->toDateString(), $to->toDateString()])->selectRaw('post_id, sum(views) v')->groupBy('post_id')->orderByDesc('v')->limit(5)->pluck('v', 'post_id');
        $topPosts = Post::whereIn('id', $top->keys())->get(['id', 'slug', 'title', 'status', 'published_at'])->keyBy('id');
        $readers = [
            'status' => $change !== null && $change <= -30 ? self::LOOK : self::OK,
            'reads' => $now, 'before' => $before, 'change' => $change,
            'search' => (int) ($src->s ?? 0), 'social' => (int) ($src->so ?? 0), 'internal' => (int) ($src->i ?? 0),
            'other' => max(0, (int) ($src->v ?? 0) - (int) ($src->s ?? 0) - (int) ($src->so ?? 0) - (int) ($src->i ?? 0)),
            'top' => $top->map(fn ($v, $id) => ['post' => $topPosts[$id] ?? null, 'n' => (int) $v])->filter(fn ($r) => $r['post'])->values()->all(),
        ];

        // ---- Publishing and the writers' queue ----
        $count = fn (string $action) => AuditLog::where('action', $action)->whereBetween('created_at', [$utc($from), $utc($to)])->count();
        $waiting = Post::where('status', 'pending');
        $oldest = (clone $waiting)->min('updated_at');
        $oldestDays = $oldest ? (int) Carbon::parse($oldest)->diffInDays(now()) : 0;
        $publishing = [
            'published' => Post::published()->whereBetween('published_at', [$utc($from), $utc($to)])->count(),
            'received' => $count('submission.received') + $count('submission.resubmitted'),
            'approved' => $count('submission.approved'), 'changes' => $count('submission.changes'), 'rejected' => $count('submission.rejected'),
            'waiting' => (clone $waiting)->count(), 'oldest_days' => $oldestDays,
        ];
        $publishing['status'] = $oldestDays > 21 ? self::URGENT : ($oldestDays > 10 ? self::LOOK : self::OK);

        // ---- Comments ----
        $pendingComments = Comment::where('status', 'pending')->count();
        $comments = [
            'new' => Comment::whereBetween('created_at', [$utc($from), $utc($to)])->count(),
            'pending' => $pendingComments,
            'status' => $pendingComments > 20 ? self::URGENT : ($pendingComments > 0 ? self::LOOK : self::OK),
        ];

        // ---- Letters (newsletter) ----
        $letters = Newsletter::where('status', 'sent')->whereBetween('sent_at', [$utc($from), $utc($to)])->get(['id', 'subject', 'sent', 'failed', 'recipients']);
        $sent = (int) $letters->sum('sent');
        $failed = (int) $letters->sum('failed');
        $bounced = Schema::hasColumn('newsletter_deliveries', 'bounce')
            ? DB::table('newsletter_deliveries')->whereIn('newsletter_id', $letters->pluck('id'))->whereNotNull('bounce')->count() : 0;
        $clicks = DB::table('newsletter_clicks')->whereIn('newsletter_id', $letters->pluck('id'))->distinct('delivery_id')->count('delivery_id');
        $failRate = $sent + $failed > 0 ? $failed / ($sent + $failed) : 0;
        $mail = [
            'letters' => $letters->count(), 'sent' => $sent, 'failed' => $failed, 'bounced' => $bounced, 'clicked' => $clicks,
            'drafts' => Newsletter::where('status', 'draft')->count(),
            'status' => $failRate > 0.2 ? self::URGENT : ($failRate > 0.05 || $bounced > 10 ? self::LOOK : self::OK),
        ];

        // ---- Phone notifications ----
        $push = DB::table('push_stats')->whereBetween('day', [$from->toDateString(), $to->toDateString()])->selectRaw('sum(sent) s, sum(opened) o')->first();
        $phones = ['sent' => (int) ($push->s ?? 0), 'opened' => (int) ($push->o ?? 0), 'phones' => DB::table('push_subscriptions')->count(), 'status' => self::OK];

        // ---- The AI team ----
        $cost = round((float) AiUsage::whereBetween('created_at', [$utc($from), $utc($to)])->sum('cost_usd'), 2);
        $failedJobs = DB::table('team_requests')->where('status', 'failed')->whereBetween('updated_at', [$utc($from), $utc($to)])->count();
        $doneJobs = DB::table('team_requests')->where('status', 'done')->whereBetween('updated_at', [$utc($from), $utc($to)])->count();
        $costBefore = round((float) AiUsage::whereBetween('created_at', [$utc($pFrom), $utc($pTo)])->sum('cost_usd'), 2);
        $ai = [   // D37: no spending cap; only a jump worth a look
            'cost' => $cost, 'before' => $costBefore, 'done' => $doneJobs, 'failed' => $failedJobs,
            'status' => $failedJobs > 0 || ($costBefore > 1 && $cost > 2 * $costBefore) ? self::LOOK : self::OK,
        ];

        // ---- The site itself ----
        $health = EditorTask::open('health')->latest()->first();
        $broken = count($health?->payload['problems'] ?? []);
        $queueFailed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '>=', $utc($from))->count() : 0;
        $backup = collect(glob(storage_path('app/private/backups/*.sql.gz')) ?: [])->map(fn ($f) => filemtime($f))->max();
        $backupHours = $backup ? (int) floor((time() - $backup) / 3600) : null;
        $ebooks = Issue::where('status', 'published')->whereNotNull('ebook_path')->get()->filter(fn ($i) => IssueEbook::waiting($i))->count();
        $site = [
            'broken' => $broken, 'checked_at' => $health?->created_at, 'queue_failed' => $queueFailed,
            'backup_hours' => $backupHours, 'ebooks' => $ebooks,
            'status' => ($backupHours === null || $backupHours > 48) && app()->environment('production') ? self::URGENT
                : (($broken > 0 || $queueFailed > 0 || $backupHours === null || $backupHours > 48) ? self::LOOK : self::OK),
        ];

        // ---- What is waiting for a person (D42: nothing goes out without a click) ----
        $todo = array_values(array_filter([
            $publishing['waiting'] ? ['n' => $publishing['waiting'], 'label' => 'লেখা পড়ার অপেক্ষায়', 'url' => '/desk/posts?tableFilters[status][value]=pending'] : null,
            $pendingComments ? ['n' => $pendingComments, 'label' => 'মন্তব্য অনুমোদনের অপেক্ষায়', 'url' => '/desk/comments'] : null,
            $mail['drafts'] ? ['n' => $mail['drafts'], 'label' => 'চিঠির খসড়া পাঠানোর অপেক্ষায়', 'url' => '/desk/newsletters'] : null,
            $ebooks ? ['n' => $ebooks, 'label' => 'ই-বুক অনুমোদনের অপেক্ষায়', 'url' => '/desk/ebooks'] : null,
            $broken ? ['n' => $broken, 'label' => 'ভাঙা লিংক বা হারানো ছবি', 'url' => '/desk'] : null,
            $failedJobs ? ['n' => $failedJobs, 'label' => 'এআই দলের কাজ আটকে গেছে', 'url' => '/desk/ai-team'] : null,
        ]));

        $parts = compact('readers', 'publishing', 'comments', 'mail', 'phones', 'ai', 'site');
        $worst = collect($parts)->pluck('status');

        return $parts + [
            'period' => $period, 'back' => $back, 'from' => $from, 'to' => $to, 'pFrom' => $pFrom, 'pTo' => $pTo, 'todo' => $todo,
            'overall' => $worst->contains(self::URGENT) ? self::URGENT : ($worst->contains(self::LOOK) ? self::LOOK : self::OK),
        ];
    }
}
