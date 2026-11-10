<?php

namespace App\Console\Commands;

use App\Ai\ReviewService;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Phase 3e blind provider test (PLAN.md §4, D11): the same real backlog pieces are reviewed by Claude and
 * OpenAI; the editor scores the two reports as "A" and "B" in /desk → AI পরীক্ষা without knowing which is which.
 * Estimates the cost first and refuses to run above --max-usd (owner budget: under ₹500).
 */
class AiBlindTest extends Command
{
    protected $signature = 'hatpakha:ai-blind-test {--count=30} {--max-usd=6 : Hard stop for the estimated cost (≈ ₹500)} {--force : Skip the confirmation question}';

    protected $description = 'Run the blind Claude vs OpenAI review test on real backlog pieces';

    public function handle(ReviewService $service): int
    {
        foreach (['anthropic', 'openai'] as $p) {
            if ($why = $service->blockedReason(new Post(['body_html' => str_repeat('শব্দ ', 100)]), $p)) {
                $this->error("Cannot run: {$why}.");

                return self::FAILURE;
            }
        }
        if (blank(config('ai.openai.review_model'))) {
            $this->error('Set OPENAI_REVIEW_MODEL (and OPENAI_PRICE_IN/OUT) in ENV_FILE first.');

            return self::FAILURE;
        }

        // Real backlog pieces (old WordPress pending), long enough to judge, tiers A and C first.
        $posts = Post::where('status', 'pending')->whereNull('submitted_by')->whereIn('backlog_tier', ['A', 'C'])
            ->whereRaw('LENGTH(body_html) > 1500')->inRandomOrder()->limit((int) $this->option('count'))->get();
        if ($posts->isEmpty()) {
            $this->error('No suitable backlog pieces found (run php artisan hatpakha:audit first).');

            return self::FAILURE;
        }

        // Rough estimate: ~1 token per 2 characters of Bengali text + rubric + ~1,500 output tokens, per provider.
        $estimate = 0.0;
        foreach ($posts as $p) {
            $u = ['in' => (int) (mb_strlen(strip_tags($p->body_html)) / 2) + 2500, 'out' => 1500, 'cache_read' => 0, 'cache_write' => 0];
            $estimate += ReviewService::cost((string) config('ai.anthropic.review_model'), $u) + ReviewService::cost((string) config('ai.openai.review_model'), $u);
        }
        $this->info(sprintf('%d pieces × 2 providers. Estimated cost: $%.2f (limit $%.2f).', $posts->count(), $estimate, (float) $this->option('max-usd')));
        if ($estimate > (float) $this->option('max-usd')) {
            $this->error('Estimate is above the limit. Use fewer pieces (--count) or raise --max-usd.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Run the blind test now?', false)) {
            return self::FAILURE;
        }

        $run = now()->format('Ymd-Hi').'-'.Str::lower(Str::random(4));
        $bar = $this->output->createProgressBar($posts->count());
        foreach ($posts as $post) {
            // Random A/B per piece, so position never gives the provider away.
            $labels = random_int(0, 1) ? ['anthropic' => 'A', 'openai' => 'B'] : ['anthropic' => 'B', 'openai' => 'A'];
            foreach ($labels as $provider => $label) {
                $service->run($post, $provider, 'blind_test', $run, $label);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Done. Score the reports in /desk → AI পরীক্ষা (run {$run}). Providers are revealed after every pair is scored.");

        return self::SUCCESS;
    }
}
