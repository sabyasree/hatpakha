<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\AuditLog;
use App\Support\WriterNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Owner (2026-11-12): writers shown by an old login get the name from their own profile: the name on their account here
 * (if they changed it), else the first and last name in their old WordPress profile. Everyone else keeps their name.
 * Only writers whose name is still a login are touched, so a name set in /desk or by the writer is never replaced.
 */
class ProfileNames extends Command
{
    protected $signature = 'hatpakha:profile-names {--dry : List the changes without saving}';

    protected $description = 'Writers shown by an old login name get the name from their own profile (or keep it)';

    public function handle(): int
    {
        $authors = Author::with('user')->whereNull('display_name_bn')->where('is_desk', false)->get()
            ->filter(fn (Author $a) => WriterNames::loginLike($a->display_name));
        $legacy = $this->legacyNames($authors->pluck('legacy_wp_user_id')->filter()->all());

        $changes = [];
        foreach ($authors as $a) {
            $account = $a->user?->name;
            $name = $account && ! WriterNames::loginLike($account) && mb_strtolower(trim($account)) !== mb_strtolower($a->display_name)
                ? WriterNames::tidy($account)
                : ($legacy[$a->legacy_wp_user_id] ?? null);
            $name = $name ? WriterNames::fromProfile($name, '', $a->display_name) : null;
            if ($name) {
                $changes[] = [$a->id, $a->display_name, $name];
                if (! $this->option('dry')) {
                    $a->forceFill(['display_name' => $name])->save();
                }
            }
        }

        $this->table(['#', 'আগে', 'এখন'], $changes);
        $this->info(($this->option('dry') ? 'Would change ' : 'Changed ').count($changes).' of '.$authors->count().' login names; the rest kept as they are.');
        if (! $this->option('dry') && $changes) {
            AuditLog::record('author.profile_names', null, ['changed' => count($changes), 'kept' => $authors->count() - count($changes)]);
        }

        return self::SUCCESS;
    }

    /** @return array<int,string> WordPress user id → "First Last" from their old profile (empty when the old database is not here) */
    private function legacyNames(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $rows = rescue(fn () => DB::connection('legacy')->table('usermeta')->whereIn('user_id', $ids)
            ->whereIn('meta_key', ['first_name', 'last_name'])->get(['user_id', 'meta_key', 'meta_value']), null, false);
        if ($rows === null) {
            $this->warn('The old WordPress database is not connected (LEGACY_DB_*): only names set on accounts here were used.');

            return [];
        }
        $out = [];
        foreach ($rows->groupBy('user_id') as $id => $meta) {
            $out[(int) $id] = trim(($meta->firstWhere('meta_key', 'first_name')->meta_value ?? '').' '.($meta->firstWhere('meta_key', 'last_name')->meta_value ?? ''));
        }

        return array_filter($out);
    }
}
