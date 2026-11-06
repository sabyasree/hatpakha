<?php

namespace App\Console\Commands;

use App\Ai\Claude;
use App\Ai\CommentScreen;
use App\Ai\Illustrator;
use App\Ai\IssuePlanner;
use App\Ai\ReviewService;
use App\Enums\Role;
use App\Models\AiReview;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The AI editor's working shift (D44), run by the scheduler like an employee's routine.
 * It prepares; it never publishes, sends or deletes. The owner's pause button stops everything.
 *   inbox    (every 10 min) new submissions, comments, pictures for new pieces
 *   backlog  (hourly)       a few old pending pieces + a few pictures for old posts
 *   issue    (daily)        from the 20th: next month's issue proposal + editorial draft
 *   brief    (daily 08:00)  the owner's morning summary (dashboard; e-mail when real mail is set up)
 */
class AiShift extends Command
{
    protected $signature = 'hatpakha:ai-shift {task : inbox|backlog|issue|brief}';

    protected $description = "The AI editor's scheduled work";

    public function handle(ReviewService $service): int
    {
        Setting::put('ai.heartbeat', now()->toIso8601String());
        if ($this->argument('task') === 'brief') {
            return $this->brief();
        }
        if ($why = Claude::blocked()) {
            $this->line("Resting: {$why}.");

            return self::SUCCESS;
        }

        return match ($this->argument('task')) {
            'inbox' => $this->inbox($service),
            'backlog' => $this->backlog($service),
            'issue' => $this->issue(),
            default => self::INVALID,
        };
    }

    private function inbox(ReviewService $service): int
    {
        // New submissions the after-submit job missed (e.g. AI was paused then).
        $done = AiReview::where('status', 'done')->whereNull('blind_run')->where('report', 'like', '%"edits"%')->select('post_id');
        foreach (Post::where('status', 'pending')->whereNotNull('submitted_by')->whereNotIn('id', $done)->oldest('submitted_at')->limit(5)->get() as $p) {
            $service->run($p, null, 'editor');
        }
        foreach (Comment::with('post')->where('status', 'pending')->whereNull('ai_verdict')->oldest()->limit(20)->get() as $c) {
            CommentScreen::run($c);
        }
        if (! Illustrator::blocked()) {
            foreach (Illustrator::candidates()->where('status', 'pending')->whereNotNull('submitted_by')->whereNull('proposed_image_error')->limit(3)->get() as $p) {
                Illustrator::make($p);
            }
        }

        return self::SUCCESS;
    }

    private function backlog(ReviewService $service): int
    {
        $done = AiReview::where('status', 'done')->whereNull('blind_run')->where('report', 'like', '%"edits"%')->select('post_id');
        $tried = AiReview::whereIn('status', ['failed', 'skipped'])->where('created_at', '>=', now()->subDays(7))->select('post_id');
        foreach (Post::where('status', 'pending')->whereNull('submitted_by')->whereNotIn('id', $done)->whereNotIn('id', $tried)
            ->orderBy('backlog_tier')->orderByDesc('submitted_at')->limit(10)->get() as $p) {
            $service->run($p, null, 'editor');
        }
        // SEO expert (D46): 10 old published pieces an hour, filling only missing details.
        foreach (Post::published()->whereNull('seo_done_at')->orderByDesc('published_at')->limit(10)->get() as $p) {
            \App\Ai\SeoExpert::run($p, onlyMissing: true);
        }
        if (! Illustrator::blocked()) {
            foreach (Illustrator::candidates()->whereNull('proposed_image_error')->orderByDesc('published_at')->limit(10)->get() as $p) {
                Illustrator::make($p);
            }
        }

        return self::SUCCESS;
    }

    private function issue(): int
    {
        $owner = User::where('role', Role::Admin->value)->orderBy('id')->first();
        if ($owner && now('Asia/Kolkata')->day >= 20 && IssuePlanner::due()) {
            [, $err] = IssuePlanner::propose($owner);
            $this->line($err ?? 'Issue proposed.');
        }

        return self::SUCCESS;
    }

    private function brief(): int
    {
        $brief = \App\Ai\Brief::today();
        Setting::put('ai.brief', json_encode($brief, JSON_UNESCAPED_UNICODE));
        if (! in_array(config('mail.default'), ['log', 'array'], true)) {
            foreach (User::where('role', Role::Admin->value)->get() as $admin) {
                $admin->notify(new \App\Notifications\EditorBrief($brief));
            }
        }

        return self::SUCCESS;
    }
}
