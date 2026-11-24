<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The one password rule for reader and writer accounts (docs/SECURITY.md §2). Owner decision 2026-11-24: at least
 * 8 characters, nothing else; a password found in known breaches is still refused. Desk accounts also need 2FA,
 * and the console command keeps a longer minimum for them.
 */
final class PasswordPolicy
{
    public const MIN = 8;

    public const DESK_MIN = 12;

    public static function rule(): Password
    {
        return Password::min(self::MIN)->uncompromised();
    }
}
