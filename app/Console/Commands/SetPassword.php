<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Set a user's password from the server (SSH only). The password is typed at a hidden prompt,
 * so it never appears on screen, in shell history or in logs (docs/SECURITY.md §2).
 */
class SetPassword extends Command
{
    protected $signature = 'hatpakha:password {email} {--reset-2fa : Also clear the authenticator app so 2FA is set up again at next login}';

    protected $description = 'Set a new password for a user (hidden prompt)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password (hidden)');
        if ($password !== (string) $this->secret('Type it again')) {
            $this->error('The two passwords do not match. Nothing changed.');

            return self::FAILURE;
        }

        $v = Validator::make(['password' => $password], ['password' => ['required', Password::min(12)->letters()->numbers()]]);
        if ($v->fails()) {
            $this->error($v->errors()->first('password').' Nothing changed.');

            return self::FAILURE;
        }

        $changes = ['password' => $password, 'legacy_password' => null, 'remember_token' => null];
        if ($this->option('reset-2fa')) {
            $changes += ['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null];
        }
        $user->forceFill($changes)->save();
        AuditLog::record('user.password_set', $user, ['via' => 'console', 'reset_2fa' => (bool) $this->option('reset-2fa')]);

        $this->info("Password updated for {$user->email}.".($this->option('reset-2fa') ? ' 2FA will be set up again at next login.' : ''));

        return self::SUCCESS;
    }
}
