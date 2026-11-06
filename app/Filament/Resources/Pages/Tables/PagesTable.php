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
                TextColumn::make('title')->label('শিরোনাম')->searchable(),
                TextColumn::make('slug')->label('ঠিকানা')->formatStateUsing(fn ($state) => "/{$state}/"),
                TextColumn::make('status')->label('অবস্থা')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'published' ? 'প্রকাশিত' : 'খসড়া')
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                IconColumn::make('show_in_footer')->label('ফুটার')->boolean(),
                IconColumn::make('noindex')->label('গুগলে লুকানো')->boolean(),
                TextColumn::make('updated_at')->label('শেষ বদল')->since()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view')->label('দেখুন')->url(fn ($record) => $record->url())->openUrlInNewTab(),
            ]);
        // No delete: unpublish (খসড়া) instead. Deleting content needs the owner (CLAUDE.md §3).
    }
}
