<?php

namespace App\Filament\Resources\Pages\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')->label(__('শিরোনাম'))->searchable(),
                TextColumn::make('slug')->label(__('ঠিকানা'))->formatStateUsing(fn ($state) => "/{$state}/"),
                TextColumn::make('status')->label(__('অবস্থা'))->badge()
                    ->formatStateUsing(fn ($state) => $state === 'published' ? __('প্রকাশিত') : __('খসড়া'))
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('show_in_footer')->label(__('ফুটার'))->boolean(),
                IconColumn::make('noindex')->label(__('গুগলে লুকানো'))->boolean(),
                TextColumn::make('updated_at')->label(__('শেষ বদল'))->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view')->label(__('দেখুন'))->url(fn ($record) => $record->url())->openUrlInNewTab(),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\Page::class)])]);
        // No delete: unpublish (খসড়া) instead. Deleting content needs the owner (CLAUDE.md §3).
    }
}
