<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        $seeds = collect(require database_path('content/collection-seeds.php'))->map(fn ($s) => $s['title'])->all();

        return [
            Action::make('propose')->label(__('প্রস্তাব চাই'))->icon('heroicon-o-sparkles')
                ->modalDescription(__('সংগ্রাহক পুরো আর্কাইভ খুঁজে ১২-২০টি লেখার তালিকা আর ভূমিকা তৈরি করবে (কয়েক মিনিট লাগে)। তারপর এখানে সাজিয়ে প্রকাশ করবেন।'))
                ->schema([
                    Select::make('seed')->label(__('তালিকা থেকে'))->options($seeds),
                    TextInput::make('theme')->label(__('অথবা নিজের বিষয়'))->maxLength(120)->helperText(__('যেমন: নদীর কবিতা')),
                ])
                ->action(function (array $data) {
                    $topic = $data['seed'] ?: trim((string) $data['theme']);
                    if ($topic === '') {
                        Notification::make()->title(__('একটি বিষয় বাছুন বা লিখুন'))->danger()->send();

                        return;
                    }
                    \App\Ai\TeamJobs::queue('archivist', 'themed', $topic);
                    Notification::make()->title(__('সংগ্রাহক কাজ শুরু করেছে'))->body(__('কয়েক মিনিটে প্রস্তাব এখানে আসবে।'))->success()->send();
                }),
        ];
    }
}
