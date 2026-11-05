<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/** Give an existing user a role, e.g. make the owner an admin after the import. Server/SSH only. */
class PromoteUser extends Command
{
    protected $signature = 'hatpakha:role {email} {role=admin : reader, writer, trusted_writer, editor, chief_editor or admin}';

    protected $description = 'Set the role of an existing user (by email)';

    public function handle(): int
    {
        $role = Role::tryFrom($this->argument('role'));
        if (! $role) {
            $this->error('Unknown role. Use one of: '.implode(', ', array_map(fn ($r) => $r->value, Role::cases())));

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email. Run the import first, or register on the site.');

            return self::FAILURE;
        }

        $old = $user->role?->value;
        $user->forceFill(['role' => $role])->save();
        AuditLog::record('user.role_changed', $user, ['from' => $old, 'to' => $role->value, 'via' => 'console']);

        $this->info("{$user->email}: {$old} → {$role->value}");
        if ($role->atLeast(Role::Editor)) {
            $this->line('They can now sign in at /desk/ and will be asked to set up 2FA (an authenticator app).');
        }

        return self::SUCCESS;
    }
}
