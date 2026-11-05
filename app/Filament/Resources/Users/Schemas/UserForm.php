<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Admin-only. Never exposes password hashes, legacy hashes or 2FA secrets (docs/SECURITY.md §2).
 * Passwords are set by the user themselves (reset link / magic link), not typed by an admin.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('নাম')->required()->maxLength(190),
            TextInput::make('email')->label('ইমেল')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('ভূমিকা')->required()->default(Role::Reader->value)
                ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])),
        ]);
    }
}
