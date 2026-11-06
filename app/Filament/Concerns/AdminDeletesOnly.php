<?php

namespace App\Filament\Concerns;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;

/** Deleting content needs the owner (CLAUDE.md §3): only admins see delete/restore buttons. */
trait AdminDeletesOnly
{
    protected static function isAdmin(): bool
    {
        return auth()->user()?->hasRole(Role::Admin) ?? false;
    }

    public static function canDelete(Model $record): bool { return static::isAdmin(); }

    public static function canDeleteAny(): bool { return static::isAdmin(); }

    public static function canForceDelete(Model $record): bool { return static::isAdmin(); }

    public static function canForceDeleteAny(): bool { return static::isAdmin(); }

    public static function canRestore(Model $record): bool { return static::isAdmin(); }

    public static function canRestoreAny(): bool { return static::isAdmin(); }
}
