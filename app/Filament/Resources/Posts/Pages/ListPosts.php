<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /** "অপেক্ষায়" is the review queue: oldest submission first (later: sorted by AI score, 3d). */
    public function getTabs(): array
    {
        return [
            'review' => Tab::make(__('অপেক্ষায়'))
                ->badge(fn () => Post::where('status', 'pending')->whereNotNull('submitted_by')->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNotNull('submitted_by')->reorder()->orderBy('submitted_at')),
            // The 424 old WordPress submissions, kept apart so they never bury new ones (tiers: D16, Phase 2).
            // D104: old submissions whose writer said yes to the apology letter: these may be read and decided.
            'backlog_yes' => Tab::make(__('পুরনো: লেখক রাজি'))
                ->badge(fn () => Post::where('status', 'pending')->whereNull('submitted_by')->where('relaunch_status', 'yes')->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNull('submitted_by')->where('relaunch_status', 'yes')->reorder()->orderBy('relaunch_answered_at')),
            'backlog' => Tab::make(__('পুরনো জমা'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNull('submitted_by')->reorder()->orderBy('backlog_tier')->orderByDesc('submitted_at')),
            // Owner (2026-11-08): drafts had no tab; the AI desk drafts (D95) and classics (D101) land here, newest first.
            'drafts' => Tab::make(__('খসড়া'))
                ->badge(fn () => Post::where('status', 'draft')->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')->reorder()->orderByDesc('updated_at')),
            // Owner (2026-11-12): the editorials, apart (they are also listed per issue in /desk → সম্পাদকীয়).
            'editorial' => Tab::make(__('সম্পাদকীয়'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('section', fn ($s) => $s->where('slug', 'editorial'))->reorder()->orderByDesc('updated_at')),
            'changes' => Tab::make(__('বদল চাওয়া'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'changes')),
            'published' => Tab::make(__('প্রকাশিত'))->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['published', 'scheduled'])),
            'all' => Tab::make(__('সব')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'review';
    }
}
