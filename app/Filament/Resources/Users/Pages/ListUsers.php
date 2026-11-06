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
            // Owner: "a test account, so I can see how a writer or reader sees everything". A normal writer
            // account with the e-mail marked as checked (staging sends no e-mail) and no newsletter mail.
            \Filament\Actions\Action::make('testAccount')->label(__('পরীক্ষার লেখক অ্যাকাউন্ট'))->icon('heroicon-o-user-plus')->color('gray')
                ->modalHeading(__('পরীক্ষার লেখক অ্যাকাউন্ট তৈরি করুন'))
                ->modalDescription(__('একজন সাধারণ লেখকের মতো অ্যাকাউন্ট: অ্যাডমিন বা সম্পাদকের কোনো ক্ষমতা নেই। ই-মেল যাচাই হয়েছে বলে ধরা হবে, চিঠি যাবে না। অন্য ব্রাউজারে বা ছদ্মবেশী (Incognito) জানালায় এই ই-মেল আর পাসওয়ার্ড দিয়ে লগ-ইন করুন, তাহলে আপনার অ্যাডমিন লগ-ইন বন্ধ করতে হবে না।'))
                ->schema([
                    \Filament\Forms\Components\TextInput::make('name')->label(__('নাম'))->required()->maxLength(80)->default('পরীক্ষার লেখক'),
                    \Filament\Forms\Components\TextInput::make('email')->label(__('ই-মেল'))->email()->required()->maxLength(190)
                        ->unique(\App\Models\User::class, 'email')->helperText(__('যেকোনো ঠিকানা, যেমন test+writer@আপনার-ডোমেন; এখানে কোনো চিঠি যায় না।')),
                    \Filament\Forms\Components\TextInput::make('password')->label(__('পাসওয়ার্ড'))->password()->revealable()->required()
                        ->rule(\Illuminate\Validation\Rules\Password::min(10)->letters()->numbers())->helperText(__('অন্তত ১০ অক্ষর, অক্ষর আর সংখ্যা দুই-ই।')),
                ])
                ->modalSubmitActionLabel(__('তৈরি করুন'))
                ->visible(fn () => auth()->user()?->hasRole(\App\Enums\Role::Admin))
                ->action(function (array $data) {
                    $u = new \App\Models\User;
                    $u->forceFill([
                        'name' => trim($data['name']), 'email' => strtolower(trim($data['email'])), 'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
                        'role' => \App\Enums\Role::Writer, 'email_verified_at' => now(), 'mail_opt_out' => true,
                    ])->save();
                    \App\Models\AuditLog::record('user.test_account', $u, ['by' => auth()->id()]);
                    \Filament\Notifications\Notification::make()->title(__('পরীক্ষার অ্যাকাউন্ট তৈরি হয়েছে'))
                        ->body(__('ছদ্মবেশী জানালায় :url খুলে এই ই-মেল আর পাসওয়ার্ড দিয়ে লগ-ইন করুন।', ['url' => \App\Support\Url::to('/login')]))->success()->persistent()->send();
                }),
            CreateAction::make(),
        ];
    }
}
