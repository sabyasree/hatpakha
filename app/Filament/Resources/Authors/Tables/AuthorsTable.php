<?php

namespace App\Filament\Resources\Authors\Tables;

use App\Models\Author;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_count', 'desc')
            ->columns([
                TextColumn::make('display_name')->label(__('নাম'))->searchable(['display_name', 'display_name_bn', 'slug'])->weight('bold')
                    ->description(fn (Author $record) => $record->display_name_bn ?: '/author/'.$record->slug.'/'),
                TextColumn::make('kind')->label(__('ধরন'))->badge()
                    ->state(fn (Author $record) => $record->is_desk ? __('ডেস্ক') : ($record->user_id ? __('অ্যাকাউন্ট') : __('অতিথি')))
                    ->color(fn ($state) => [__('ডেস্ক') => 'danger', __('অ্যাকাউন্ট') => 'success'][$state] ?? 'gray'),
                TextColumn::make('published_count')->label(__('প্রকাশিত'))->numeric()->sortable(),
                TextColumn::make('city')->label(__('শহর'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label(__('শেষ বদল'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('ধরন'))->options(['account' => __('অ্যাকাউন্ট'), 'guest' => __('অতিথি'), 'desk' => __('ডেস্ক')])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'account' => $query->whereNotNull('user_id'),
                        'guest' => $query->whereNull('user_id')->where('is_desk', false),
                        'desk' => $query->where('is_desk', true),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('view')->label(__('দেখুন'))->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Author $record) => $record->url())->openUrlInNewTab(),
                EditAction::make(),
                \App\Filament\Support\Deleting::row(),
            ])
            ->toolbarActions([\Filament\Actions\BulkActionGroup::make([\App\Filament\Support\Deleting::bulk(\App\Models\Author::class)])]);
    }
}
