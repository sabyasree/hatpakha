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
                TextColumn::make('title')->label('সংখ্যা')->weight('bold')->searchable()->description(fn (Issue $record) => '/sonkha/'.$record->slug.'/'),
                TextColumn::make('kind')->label('ধরন')->badge()->formatStateUsing(fn ($state) => $state === 'festival' ? 'উৎসব' : 'মাসিক'),
                TextColumn::make('theme_key')->label('থিম')->formatStateUsing(fn ($state) => IssueForm::THEMES[$state] ?? $state)->toggleable(),
                TextColumn::make('posts_count')->label('লেখা')->numeric(),
                TextColumn::make('status')->label('অবস্থা')->badge()->formatStateUsing(fn ($state) => IssueForm::STATUS[$state] ?? $state)
                    ->color(fn ($state) => ['published' => 'success', 'proposed' => 'info'][$state] ?? 'warning'),
            ])
            ->recordActions([
                Action::make('view')->label('দেখুন')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Issue $record) => $record->url())->openUrlInNewTab()
                    ->visible(fn (Issue $record) => $record->status === 'published'),
                EditAction::make(),
            ]);
    }
}
