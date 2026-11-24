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
        // D230 (owner: a piece that was out still sat under "সময় ঠিক করা"): the list does not wait for the ten-minute
        // job. Opening it turns every piece whose time has come into "published" there and then.
        rescue(fn () => Post::goLive(), null, false);
        return [
            'review' => Tab::make(__('অপেক্ষায়'))
                ->badge(fn () => Post::where('status', 'pending')->whereNotNull('submitted_by')->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNotNull('submitted_by')->reorder()->orderBy('submitted_at')),
            // D279: writers' serial episodes and new serials' first episodes, apart, so they are found at once
            'episodes' => Tab::make(__('পর্ব'))
                ->badge(fn () => Post::where('status', 'pending')->whereNotNull('submitted_by')->whereNotNull('serial_id')->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNotNull('submitted_by')->whereNotNull('serial_id')->reorder()->orderBy('submitted_at')),
            // D260 (owner: "no separate section to see those"); D280 (UX audit: twelve tabs, the last ones cut off): one tab
            // for every piece now with its writer, asked to agree to word changes (first) or to make changes
            'confirm' => Tab::make(__('লেখকের কাছে'))
                ->badge(fn () => Post::whereIn('status', ['confirm', 'changes'])->count() ?: null)->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['confirm', 'changes'])->reorder()->orderByRaw("case when status = 'confirm' then 0 else 1 end")->orderBy('reviewed_at')),
            // Owner (2026-11-14: "there is no section for scheduled ones"): pieces with a publishing time set, the next one first.
            'scheduled' => Tab::make(__('সময় ঠিক করা'))
                ->badge(fn () => Post::where('status', 'scheduled')->where('published_at', '>', now())->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'scheduled')->where('published_at', '>', now())->reorder()->orderBy('published_at')),
            'published' => Tab::make(__('প্রকাশিত'))->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now())),
            // Owner (2026-11-08): drafts had no tab; the AI desk drafts (D95) and classics (D101) land here, newest first.
            // The desk's own drafts. Writers' unsent drafts are theirs until they press "জমা দিন" (owner, 2026-11-12):
            // they live in their own quiet tab, never in the counts.
            'drafts' => Tab::make(__('খসড়া'))
                ->badge(fn () => Post::where('status', 'draft')->whereNull('submitted_by')->count() ?: null)->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')->whereNull('submitted_by')->reorder()->orderByDesc('updated_at')),
            // Owner (2026-11-12): the editorials, apart (they are also listed per issue in /desk → সম্পাদকীয়).
            'editorial' => Tab::make(__('সম্পাদকীয়'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('section', fn ($s) => $s->where('slug', 'editorial'))->reorder()->orderByDesc('updated_at')),
            // The old WordPress submissions, kept apart so they never bury new ones (D16); D280: one tab, the ones whose
            // writer said yes to the apology letter first (D104: these may be read and decided)
            'backlog' => Tab::make(__('পুরনো জমা'))
                ->badge(fn () => Post::where('status', 'pending')->whereNull('submitted_by')->where('relaunch_status', 'yes')->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->whereNull('submitted_by')->reorder()
                    ->orderByRaw("case when relaunch_status = 'yes' then 0 else 1 end")->orderBy('backlog_tier')->orderByDesc('submitted_at')),
            'writer_drafts' => Tab::make(__('লেখকের খসড়া'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')->whereNotNull('submitted_by')->reorder()->orderByDesc('updated_at')),
            'all' => Tab::make(__('সব')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'review';
    }
}
