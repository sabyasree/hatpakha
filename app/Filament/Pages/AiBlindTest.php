<?php

namespace App\Filament\Pages;

use App\Models\AiReview;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Phase 3e: score the two anonymous reports per piece (1–5). Which provider wrote A or B stays hidden
 * until every report in the run is scored; then the totals are revealed (D11).
 */
class AiBlindTest extends Page
{
    protected string $view = 'filament.pages.ai-blind-test';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'প্রশাসন';

    protected static ?string $navigationLabel = 'AI পরীক্ষা';

    protected static ?string $title = 'AI পরীক্ষা (অন্ধ তুলনা)';

    protected static ?int $navigationSort = 91;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(\App\Enums\Role::Admin) ?? false;
    }

    /** @var array<int,int|string|null> review id → score */
    public array $scores = [];

    public function mount(): void
    {
        foreach ($this->reviews() as $r) {
            $this->scores[$r->id] = $r->editor_score;
        }
    }

    public function run(): ?string
    {
        return AiReview::whereNotNull('blind_run')->latest('id')->value('blind_run');
    }

    public function reviews()
    {
        return ($run = $this->run()) ? AiReview::with('post.section')->where('blind_run', $run)->orderBy('post_id')->orderBy('blind_label')->get() : collect();
    }

    public function score(int $reviewId): void
    {
        $value = (int) ($this->scores[$reviewId] ?? 0);
        abort_unless($value >= 1 && $value <= 5, 422);
        $review = AiReview::whereKey($reviewId)->where('blind_run', $this->run())->firstOrFail();
        $review->forceFill(['editor_score' => $value, 'scored_by' => auth()->id()])->save();
        Notification::make()->title('স্কোর রাখা হয়েছে')->success()->send();
    }

    /** Null until every report is scored; then per-provider totals (the reveal). */
    public function result(): ?array
    {
        $reviews = $this->reviews()->where('status', 'done');
        if ($reviews->isEmpty() || $reviews->contains(fn ($r) => $r->editor_score === null)) {
            return null;
        }
        $out = [];
        foreach ($reviews->groupBy('provider') as $provider => $g) {
            $out[$provider] = ['model' => $g->first()->model, 'avg' => round($g->avg('editor_score'), 2), 'n' => $g->count()];
        }
        $wins = ['anthropic' => 0, 'openai' => 0, 'tie' => 0];
        foreach ($reviews->groupBy('post_id') as $pair) {
            $a = $pair->firstWhere('provider', 'anthropic')?->editor_score;
            $o = $pair->firstWhere('provider', 'openai')?->editor_score;
            $wins[$a === $o ? 'tie' : ($a > $o ? 'anthropic' : 'openai')]++;
        }
        AuditLog::record('ai.blind_test_revealed', null, ['run' => $this->run()] + $out);

        return ['providers' => $out, 'wins' => $wins];
    }
}
