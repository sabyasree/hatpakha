<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // D85 (owner: "instead of a new account, can admin view as a writer, one with many posts, as a demo").
            \Filament\Actions\Action::make('viewAs')->label(__('লেখকের চোখে দেখুন'))->icon('heroicon-o-eye')->color('gray')
                ->modalHeading(__('একজন লেখক বা পাঠকের চোখে সাইটটি দেখুন'))
                ->modalDescription(__('তাঁর অ্যাকাউন্ট আর সাইট ঠিক যেমন তিনি দেখেন, তেমন দেখাবে। শুধু দেখা যাবে: কিছু বদলানো, পাঠানো বা মুছে ফেলা যাবে না। ৩০ মিনিট পরে বা "বন্ধ করুন" টিপলে শেষ হবে। কে কবে দেখলেন তা লগে থাকে।'))
                ->schema([
                    \Filament\Forms\Components\Select::make('user')->label(__('কার চোখে'))->required()->searchable()
                        ->options(fn () => \App\Http\Middleware\ViewAs::choices())
                        ->getSearchResultsUsing(fn (string $search) => \App\Http\Middleware\ViewAs::choices($search))
                        ->default(fn () => array_key_first(\App\Http\Middleware\ViewAs::choices()))
                        ->helperText(__('সবচেয়ে বেশি লেখা যাঁর, তিনি প্রথমে।')),
                ])
                ->modalSubmitActionLabel(__('দেখুন'))
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin))
                ->action(function (array $data) {
                    $to = ($u = \App\Models\User::find($data['user'])) ? \App\Http\Middleware\ViewAs::start($u) : null;
                    $to ? $this->redirect($to) : \Filament\Notifications\Notification::make()->title(__('এই অ্যাকাউন্টটি দেখা যাবে না'))->danger()->send();
                }),
            CreateAction::make(),
        ];
    }
}
