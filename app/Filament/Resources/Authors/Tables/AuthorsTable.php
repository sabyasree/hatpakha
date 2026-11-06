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
                TextColumn::make('display_name')->label('নাম')->searchable(['display_name', 'display_name_bn', 'slug'])->weight('bold')
                    ->description(fn (Author $record) => $record->display_name_bn ?: '/author/'.$record->slug.'/'),
                TextColumn::make('kind')->label('ধরন')->badge()
                    ->state(fn (Author $record) => $record->is_desk ? 'ডেস্ক' : ($record->user_id ? 'অ্যাকাউন্ট' : 'অতিথি'))
                    ->color(fn ($state) => ['ডেস্ক' => 'danger', 'অ্যাকাউন্ট' => 'success'][$state] ?? 'gray'),
                TextColumn::make('published_count')->label('প্রকাশিত')->numeric()->sortable(),
                TextColumn::make('city')->label('শহর')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('শেষ বদল')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')->label('ধরন')->options(['account' => 'অ্যাকাউন্ট', 'guest' => 'অতিথি', 'desk' => 'ডেস্ক'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'account' => $query->whereNotNull('user_id'),
                        'guest' => $query->whereNull('user_id')->where('is_desk', false),
                        'desk' => $query->where('is_desk', true),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('view')->label('দেখুন')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Author $record) => $record->url())->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }
}
