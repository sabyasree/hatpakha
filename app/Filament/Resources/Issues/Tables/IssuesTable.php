<?php

namespace App\Filament\Resources\Issues\Tables;

use App\Filament\Resources\Issues\Schemas\IssueForm;
use App\Models\Issue;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->defaultSort('slug', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('সংখ্যা'))->weight('bold')->searchable()->description(fn (Issue $record) => '/sonkha/'.$record->slug.'/'),
                TextColumn::make('kind')->label(__('ধরন'))->badge()->formatStateUsing(fn ($state) => $state === 'festival' ? __('উৎসব') : __('মাসিক')),
                TextColumn::make('theme_key')->label(__('থিম'))->formatStateUsing(fn ($state) => __(IssueForm::THEMES[$state] ?? $state))->toggleable(),
                TextColumn::make('posts_count')->label(__('লেখা'))->numeric(),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()->formatStateUsing(fn ($state) => __(IssueForm::STATUS[$state] ?? $state))
                    ->color(fn ($state) => ['published' => 'success', 'proposed' => 'info'][$state] ?? 'warning'),
            ])
            ->recordActions([
                Action::make('view')->label(__('দেখুন'))->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Issue $record) => $record->url())->openUrlInNewTab()
                    ->visible(fn (Issue $record) => $record->status === 'published'),
                EditAction::make(),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\Issue::class)])]);
    }
}
