<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Admin-only. Never exposes password hashes, legacy hashes or 2FA secrets (docs/SECURITY.md §2).
 * Passwords are set by the user themselves (reset link), not typed by an admin.
 */
class UserForm
{
    public static function roles(): array
    {
        return collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('নাম')->required()->maxLength(190),
            TextInput::make('email')->label('ইমেল')->email()->required()->unique(ignoreRecord: true)
                ->helperText(fn ($operation) => $operation === 'create' ? 'নতুন অ্যাকাউন্টে পাসওয়ার্ড বানানোর লিংক এই ঠিকানায় যাবে।' : null),
            Select::make('role')->label('ভূমিকা')->required()->default(Role::Reader->value)->options(self::roles())
                ->disabled(fn (?User $record) => $record?->is(auth()->user()))
                ->helperText('সম্পাদক বা তার উপরে হলে /desk খোলা যায় (২-ধাপ যাচাই বাধ্যতামূলক)। প্রতিটি বদল লগে থাকে।'),
            TextInput::make('spam_reason')->label('স্প্যাম চিহ্ন (কারণ)')->maxLength(120)
                ->helperText('খালি রাখলে স্বাভাবিক অ্যাকাউন্ট। চিহ্নিত অ্যাকাউন্ট মুছে ফেলা হয় না।'),
            Section::make('তথ্য')->columns(2)->visibleOn('edit')->schema([
                Placeholder::make('legacy')->label('পুরনো ওয়ার্ডপ্রেস ভূমিকা')
                    ->content(fn (?User $record) => $record?->legacy_role ?: '—'),
                Placeholder::make('last_login')->label('শেষ লগইন')
                    ->content(fn (?User $record) => $record?->last_login_at?->diffForHumans() ?? 'কখনও না'),
                Placeholder::make('twofa')->label('২-ধাপ যাচাই')
                    ->content(fn (?User $record) => match (true) {
                        filled($record?->app_authentication_secret) => 'অথেন্টিকেটর অ্যাপ',
                        (bool) $record?->hasEmailAuthentication() => 'ইমেল কোড',
                        default => 'চালু হয়নি',
                    }),
                Placeholder::make('verified')->label('ইমেল যাচাই')
                    ->content(fn (?User $record) => $record?->email_verified_at ? 'হয়েছে' : 'হয়নি'),
            ]),
        ]);
    }
}
