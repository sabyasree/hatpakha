<?php

namespace App\Filament\Pages;

use App\Models\AiReview;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Phase 3e: one piece at a time, the two anonymous reports side by side, one question:
 * "which report helps you more as an editor?" (A / same / B). Which provider wrote A or B stays
 * hidden until every pair is judged; then the result is revealed (D11).
 * Stored as editor_score: better = 5, worse = 1, same = 3 for both.
 */
class AiBlindTest extends Page
{
    use \App\Filament\Concerns\TranslatedPageLabels;

    protected string $view = 'filament.pages.ai-blind-test';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'প্রশাসন';

    protected static ?string $navigationLabel = 'এআই পরীক্ষা';

    protected static ?string $title = 'এআই পরীক্ষা';

    protected static ?int $navigationSort = 91;

    /** The piece on screen. */
    public ?int $postId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(\App\Enums\Role::Admin) ?? false;
    }

    public function mount(): void
    {
        $this->postId = $this->pairs()->first(fn ($pair) => $pair->contains(fn ($r) => $r->editor_score === null))?->first()->post_id
            ?? $this->pairs()->first()?->first()->post_id;
    }

    public function run(): ?string
    {
        return AiReview::whereNotNull('blind_run')->latest('id')->value('blind_run');
    }

    /** Pairs where both reports were written, keyed by post id, in a stable order. */
    public function pairs(): Collection
    {
        if (! ($run = $this->run())) {
            return collect();
        }

        return AiReview::with('post.section')->where('blind_run', $run)->orderBy('post_id')->orderBy('blind_label')->get()
            ->groupBy('post_id')->filter(fn ($pair) => $pair->count() === 2 && $pair->every(fn ($r) => $r->status === 'done'));
    }

    public function failedCount(): int
    {
        return ($run = $this->run()) ? AiReview::where('blind_run', $run)->where('status', '!=', 'done')->distinct()->count('post_id') : 0;
    }

    public function go(int $postId): void
    {
        abort_unless($this->pairs()->has($postId), 404);
        $this->postId = $postId;
    }

    /** $choice: A, B or same. Saves and moves to the next piece not yet judged. */
    public function choose(string $choice): void
    {
        abort_unless(in_array($choice, ['A', 'B', 'same'], true), 422);
        $pair = $this->pairs()->get($this->postId);
        abort_unless($pair, 404);
        foreach ($pair as $review) {
            $score = $choice === 'same' ? 3 : ($review->blind_label === $choice ? 5 : 1);
            $review->forceFill(['editor_score' => $score, 'scored_by' => auth()->id()])->save();
        }
        $pairs = $this->pairs();
        $ids = $pairs->keys()->values();
        $next = $ids->slice($ids->search($this->postId) + 1)->merge($ids)
            ->first(fn ($id) => $pairs[$id]->contains(fn ($r) => $r->editor_score === null));
        $this->postId = $next ?? $this->postId;
    }

    /** What the editor picked for a pair: A, B, same or null. */
    public static function choiceOf(Collection $pair): ?string
    {
        $a = $pair->firstWhere('blind_label', 'A')?->editor_score;
        $b = $pair->firstWhere('blind_label', 'B')?->editor_score;

        return match (true) {
            $a === null || $b === null => null,
            $a === $b => 'same',
            default => $a > $b ? 'A' : 'B',
        };
    }

    /** Null until every pair is judged; then the reveal. */
    public function result(): ?array
    {
        $pairs = $this->pairs();
        if ($pairs->isEmpty() || $pairs->contains(fn ($pair) => self::choiceOf($pair) === null)) {
            return null;
        }
        $wins = ['anthropic' => 0, 'openai' => 0, 'same' => 0];
        foreach ($pairs as $pair) {
            $choice = self::choiceOf($pair);
            $wins[$choice === 'same' ? 'same' : $pair->firstWhere('blind_label', $choice)->provider]++;
        }
        $models = $pairs->flatten()->pluck('model', 'provider')->all();
        AuditLog::record('ai.blind_test_revealed', null, ['run' => $this->run(), 'wins' => $wins]);

        return ['wins' => $wins, 'models' => $models, 'total' => $pairs->count()];
    }
}
