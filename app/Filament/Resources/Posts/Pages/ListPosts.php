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
            'backlog' => Tab::make(__('পুরনো জমা'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNull('submitted_by')->reorder()->orderBy('backlog_tier')->orderByDesc('submitted_at')),
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
