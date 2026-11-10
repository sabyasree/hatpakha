<?php

namespace App\Filament\Resources\Serials\Pages;

use App\Ai\SerialWriter;
use App\Filament\Resources\Serials\SerialResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSerials extends ListRecords
{
    protected static string $resource = SerialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('propose')->label(__('নতুন ধারাবাহিক চাই'))->icon('heroicon-o-sparkles')
                ->modalDescription(__('ধারাবাহিক লেখক পুরো গল্পের পরিকল্পনা, প্রথম পর্ব আর ছবি তৈরি করবেন (কয়েক মিনিট লাগে)। সব খসড়া হিসেবে আসবে।'))
                ->schema([
                    Select::make('writer')->label(__('লেখক'))->required()
                        ->options(collect(SerialWriter::WRITERS)->map(fn ($w, $k) => __(\App\Ai\Team::AGENTS[$k][0] ?? $k))->all()),
                    Textarea::make('idea')->label(__('গল্পের ভাবনা (না দিলেও চলবে)'))->rows(2)->maxLength(600),
                ])
                ->action(function (array $data) {
                    \App\Ai\TeamJobs::queue($data['writer'], 'start', trim((string) ($data['idea'] ?? '')) ?: null);
                    Notification::make()->title(__('এআই দল কাজটি নিয়েছে'))->body(__('কয়েক মিনিটে এখানে দেখা যাবে।'))->success()->send();
                }),
        ];
    }
}
