<?php

namespace App\Console\Commands;

use App\Ai\ReviewService;
use App\Models\AiReview;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * The AI editor's batch work (D42). Prepares packages; never publishes or changes a live page.
 *   --backlog   old WordPress pieces still pending: report + copy-edits + decision + note + metadata
 *   --images    cover illustrations for pieces without a picture (D43), waiting for the owner's approval
 * Prints the cost estimate first; there is no spending cap (D37), the owner's pause button still applies.
 */
class AiEditor extends Command
{
    protected $signature = 'hatpakha:ai-editor {--backlog} {--images} {--limit=500} {--dry : Only show what would be done and the estimate}';

    protected $description = 'Let the AI editor prepare work for the owner (backlog packages, cover illustrations)';

    public function handle(ReviewService $service): int
    {
        if ($this->option('backlog')) {
            $this->backlog($service);
        }
        if ($this->option('images')) {
            $this->images();
        }
        if (! $this->option('backlog') && ! $this->option('images')) {
            $this->warn('Nothing to do: pass --backlog and/or --images.');
        }

        return self::SUCCESS;
    }

    private function backlog(ReviewService $service): void
    {
        // Pending pieces with no finished editor package yet (packages have "edits").
        $done = AiReview::where('status', 'done')->whereNull('blind_run')->where('report', 'like', '%"edits"%')->pluck('post_id');
        $posts = Post::where('status', 'pending')->whereNotIn('id', $done)->orderBy('backlog_tier')->orderByDesc('submitted_at')
            ->limit((int) $this->option('limit'))->get();
        $est = $posts->sum(fn (Post $p) => ReviewService::cost((string) config('ai.anthropic.review_model'),
            ['in' => (int) (mb_strlen(strip_tags((string) $p->body_html)) / 2) + 3000, 'out' => 2500, 'cache_read' => 0, 'cache_write' => 0]));
        $this->info(sprintf('Backlog: %d pieces to prepare. Estimated cost: $%.2f.', $posts->count(), $est));
        if ($this->option('dry') || $posts->isEmpty()) {
            return;
        }
        $bar = $this->output->createProgressBar($posts->count());
        $ok = $failed = 0;
        foreach ($posts as $post) {
            $r = $service->run($post, null, 'editor');
            $r->status === 'done' ? $ok++ : $failed++;
            if ($r->status === 'skipped' && str_contains((string) $r->error, 'paused')) {
                $this->newLine();
                $this->warn('AI is paused by an admin: stopping.');
                break;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Backlog: {$ok} prepared, {$failed} not prepared (too short, failed or skipped).");
    }

    private function images(): void
    {
        $this->call('hatpakha:ai-images', array_filter(['--limit' => $this->option('limit'), '--dry' => $this->option('dry')]));
    }
}
