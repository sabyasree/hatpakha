<?php

namespace App\Filament\Resources\Authors\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        $admin = fn () => auth()->user()?->hasRole(Role::Admin) ?? false;

        return $schema->components([
            TextInput::make('display_name')->label('ছাপার নাম')->required()->maxLength(120),
            TextInput::make('display_name_bn')->label('বাংলা বানানে নাম (লেখক নিজে দিলে)')->maxLength(120),
            // The URL is permanent: old links and Google depend on it (docs/SEO.md §1).
            TextInput::make('slug')->label('ঠিকানা')->prefix('/author/')->suffix('/')->required()->maxLength(191)
                ->unique(ignoreRecord: true)->disabledOn('edit')->helperText('একবার তৈরি হলে বদলানো যায় না।'),
            TextInput::make('city')->label('শহর')->maxLength(80),
            Textarea::make('bio')->label('পরিচয়')->rows(4)->maxLength(2000)->columnSpanFull(),
            Section::make('ধরন (শুধু অ্যাডমিন)')->columns(2)->visible($admin)->schema([
                Toggle::make('is_guest')->label('অতিথি লেখক (অ্যাকাউন্ট নেই)'),
                Toggle::make('is_desk')->label('হাতপাখা ডেস্কের বাইলাইন'),
            ]),
        ]);
    }
}
