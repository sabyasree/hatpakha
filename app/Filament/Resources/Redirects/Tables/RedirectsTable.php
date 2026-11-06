<?php

namespace App\Filament\Resources\Redirects\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('hits', 'desc')
            ->columns([
                TextColumn::make('from_path')->label(__('পুরনো ঠিকানা'))->searchable()->limit(60)->tooltip(fn ($state) => $state),
                TextColumn::make('status_code')->label('')->badge()->color(fn ($state) => (int) $state === 410 ? 'danger' : 'info'),
                TextColumn::make('to_path')->label(__('নতুন ঠিকানা'))->searchable()->limit(60)->placeholder(__('সরানো হয়েছে')),
                TextColumn::make('hits')->label(__('ভিজিট'))->numeric()->sortable(),
                TextColumn::make('updated_at')->label(__('শেষ বদল'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status_code')->label(__('ধরন'))->options([301 => __('৩০১'), 410 => __('৪১০')]),
            ])
            ->recordActions([EditAction::make()]);
    }
}
