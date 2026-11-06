<?php

namespace App\Ai;

use App\Http\Controllers\SubmissionController;
use App\Models\AiReview;
use App\Models\AiUsage;
use App\Models\Post;

/**
 * Runs AI reviews with the cost guards from PLAN.md §4: off unless enabled + keyed, cheap pre-filter before
 * any call, a monthly USD cap, and every call's tokens and cost logged. Reports are advice for editors only.
 */
final class ReviewService
{
    public function reviewer(string $provider): Reviewer
    {
        return match ($provider) {
            'openai' => app(OpenAiReviewer::class),
            default => app(AnthropicReviewer::class),
        };
    }

    public function run(Post $post, ?string $provider = null, string $purpose = 'review', ?string $blindRun = null, ?string $label = null): AiReview
    {
        $provider ??= (string) config('ai.provider');
        $reviewer = $this->reviewer($provider);
        $base = ['post_id' => $post->id, 'provider' => $provider, 'model' => $reviewer->model() ?: '-', 'blind_run' => $blindRun, 'blind_label' => $label];

        if ($why = $this->blockedReason($post, $provider)) {
            return AiReview::create($base + ['status' => 'skipped', 'error' => $why]);
        }

        $outcome = $reviewer->review($post, SubmissionController::sections()->pluck('slug')->all());
        $this->logUsage($provider, $reviewer->model(), $purpose, $post->id, $outcome->usage);

        return AiReview::create($base + [
            'status' => $outcome->report ? 'done' : 'failed',
            'report' => $outcome->report,
            'score' => $outcome->report['quality_score'] ?? null,
            'error' => $outcome->error,
        ]);
    }

    /** Null when a call may go ahead; otherwise a plain reason (stored with the skipped review). */
    public function blockedReason(Post $post, string $provider): ?string
    {
        if (! config('ai.enabled')) {
            return 'AI is switched off (AI_ENABLED=false)';
        }
        if (blank(config("ai.{$provider}.key"))) {
            return "No API key for {$provider}";
        }
        $words = count(preg_split('/\s+/u', trim(strip_tags((string) $post->body_html)), -1, PREG_SPLIT_NO_EMPTY));
        if ($words < (int) config('ai.min_words')) {
            return "Too short for an AI review ({$words} words)";
        }
        if (AiUsage::spentThisMonthUsd() >= (float) config('ai.monthly_budget_usd')) {
            return 'Monthly AI budget reached';
        }

        return null;
    }

    public static function cost(string $model, array $u): float
    {
        $p = config("ai.prices.{$model}") ?? (str_starts_with($model, 'claude-') ? null : config('ai.prices.openai'));
        if (! $p) {
            return 0.0;
        }

        return ($u['in'] * $p['in'] + $u['out'] * $p['out'] + $u['cache_read'] * $p['cache_read'] + $u['cache_write'] * $p['cache_write']) / 1_000_000;
    }

    private function logUsage(string $provider, string $model, string $purpose, int $postId, array $u): void
    {
        if (array_sum($u) === 0) {
            return;
        }
        AiUsage::create([
            'provider' => $provider, 'model' => $model, 'purpose' => $purpose, 'post_id' => $postId,
            'input_tokens' => $u['in'], 'output_tokens' => $u['out'], 'cache_read_tokens' => $u['cache_read'], 'cache_write_tokens' => $u['cache_write'],
            'cost_usd' => self::cost($model, $u),
        ]);
    }
}
