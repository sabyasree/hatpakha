<?php

namespace App\Filament\Resources\Authors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('display_name')
                    ->searchable(),
                TextColumn::make('display_name_bn')
                    ->searchable(),
                TextColumn::make('avatar_path')
                    ->searchable(),
                TextColumn::make('cover_path')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                IconColumn::make('is_guest')
                    ->boolean(),
                IconColumn::make('is_desk')
                    ->boolean(),
                TextColumn::make('claimed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('legacy_wp_user_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('legacy_coauthor_term_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('published_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
