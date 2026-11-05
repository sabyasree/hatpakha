<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use SensitiveParameter;

/**
 * Eloquent provider that also accepts a migrated WordPress hash ONCE, then
 * re-hashes with Laravel's hasher and deletes the legacy hash (docs/SECURITY.md §2).
 */
class LegacyAwareUserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;
        if (! is_string($plain) || $plain === '') {
            return false;
        }

        if ($user->getAuthPassword()) {
            return parent::validateCredentials($user, $credentials);
        }

        $legacy = $user->legacy_password ?? null;
        if ($legacy && LegacyWordPressPassword::check($plain, $legacy)) {
            $user->forceFill([
                'password' => $plain,          // hashed by the model's "hashed" cast
                'legacy_password' => null,
            ])->save();

            return true;
        }

        return false;
    }
}
