<?php

namespace App\Filament\Resources\Contests\Pages;

use App\Filament\Resources\Contests\ContestResource;
use App\Support\Calls;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListContests extends ListRecords
{
    protected static string $resource = ContestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // লেখার ডাক (2026-11-24): the editor's wish list on the public calls page; empty = the block is hidden
            Action::make('wishlist')->label(__('সম্পাদক যা খুঁজছেন'))->icon('heroicon-o-light-bulb')->color('gray')
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::ChiefEditor) ?? false)
                ->modalHeading(__('লেখার ডাক: সম্পাদক যা খুঁজছেন'))
                ->modalDescription(__('প্রতি লাইনে একটি কথা। /lekhar-dak/ পাতায় তালিকা হয়ে দেখা যাবে। ফাঁকা রাখলে অংশটি লুকোনো থাকে।'))
                ->fillForm(fn () => ['items' => Calls::wishlistText()])
                ->schema([Textarea::make('items')->label(__('তালিকা'))->rows(8)->maxLength(Calls::WISHLIST_MAX)
                    ->placeholder(__('যেমন: পাহাড়ের ছোট ভ্রমণকথা'))])
                ->action(function (array $data) {
                    Calls::saveWishlist((string) ($data['items'] ?? ''));
                    Notification::make()->title(__('সেভ হয়েছে'))->success()->send();
                }),
            Action::make('callsPage')->label(__('লেখার ডাক পাতা'))->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => \App\Support\Url::to('/lekhar-dak'), shouldOpenInNewTab: true),
            CreateAction::make(),
        ];
    }
}
