<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Owner, 2026-11-10: accounts with at least one piece become writers; the rest stay readers. Only the
 * "reader" role is raised; editors, admins and trusted writers are never touched. Prints the newsletter
 * audience (counts only) to the deploy log.
 */
return new class extends Migration
{
    public function up(): void
    {
        $withPieces = User::where(fn ($q) => $q->whereNull('role')->orWhere('role', Role::Reader->value))
            ->where(fn ($q) => $q->whereHas('author', fn ($a) => $a->whereHas('posts', fn ($p) => $p->withTrashed()))
                ->orWhereIn('id', Post::withTrashed()->whereNotNull('submitted_by')->select('submitted_by')))
            ->pluck('id');
        foreach ($withPieces->chunk(500) as $ids) {
            User::whereIn('id', $ids)->update(['role' => Role::Writer->value]);
        }
        User::whereNull('role')->update(['role' => Role::Reader->value]);
        AuditLog::record('users.roles_from_posts', null, ['writers' => $withPieces->count()]);

        if (! app()->runningUnitTests()) {
            $b = \App\Support\NewsletterSender::breakdown();
            echo "\n  Writers from pieces: {$withPieces->count()}. Newsletter audience: ".json_encode($b)."\n";
        }
    }

    public function down(): void
    {
    }
};
