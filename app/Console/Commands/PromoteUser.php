<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/** Give an existing user a role, e.g. make the owner an admin after the import. Server/SSH only. */
class PromoteUser extends Command
{
    protected $signature = 'hatpakha:role {email? : The user to change} {role=admin : reader, writer, trusted_writer, editor, chief_editor or admin}
        {--list : Show everyone who can open /desk, plus old WordPress admins/editors}';

    protected $description = 'Set the role of an existing user (by email)';

    public function handle(): int
    {
        if ($this->option('list') || ! $this->argument('email')) {
            $rows = User::whereIn('role', [Role::Editor, Role::ChiefEditor, Role::Admin])
                ->orWhereIn('legacy_role', ['administrator', 'editor'])->orderBy('role')->get()
                ->map(fn (User $u) => [$u->email, $u->name, $u->role->value, $u->legacy_role ?? '—', $u->hasRole(Role::Editor) ? 'YES' : 'no']);
            $this->table(['Email', 'Name', 'Role now', 'WordPress role', '/desk access'], $rows->all());
            $this->line('Change with: php artisan hatpakha:role someone@example.com writer');

            return self::SUCCESS;
        }

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
