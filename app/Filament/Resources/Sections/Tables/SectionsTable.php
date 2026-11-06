<?php

namespace App\Filament\Resources\Sections\Tables;

use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name_bn')->label(__('বিভাগ'))->weight('bold')->searchable(['name_bn', 'name_en', 'slug'])
                    ->description(fn (Section $record) => '/category/'.$record->slug.'/'),
                TextColumn::make('format')->label(__('ধরন'))->badge()->formatStateUsing(fn ($state) => $state === 'poem' ? __('কবিতা') : __('গদ্য')),
                TextColumn::make('posts_count')->label(__('লেখা'))->numeric(),
                IconColumn::make('is_active')->label(__('সক্রিয়'))->boolean(),
            ])
            ->recordActions([
                Action::make('view')->label(__('দেখুন'))->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Section $record) => $record->url())->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }
}
