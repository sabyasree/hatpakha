<?php

namespace App\Console\Commands;

use App\Enums\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * After staging is refreshed from a production backup (D92): take out what staging must not hold or use.
 *  - readers' and writers' e-mail addresses → userN@example.invalid (staff keep theirs, to log in);
 *    "view as writer" still works; nobody can be mailed from staging;
 *  - newsletter subscribers' addresses the same way; contact-form messages deleted;
 *  - sessions, password-reset links and queued jobs cleared.
 * Refuses to run on the live site (SEO_INDEXABLE=true) or where APP_URL is hatpakha.com.
 */
class StagingScrub extends Command
{
    protected $signature = 'hatpakha:staging-scrub {--force : Do not ask}';

    protected $description = 'Remove private data from a staging copy of the production database';

    public function handle(): int
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if (config('seo.indexable') || in_array($host, ['hatpakha.com', 'www.hatpakha.com'], true)) {
            $this->error('This looks like the live site. Staging only.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Scrub private data on {$host}?")) {
            return self::FAILURE;
        }
        $cat = fn (string $pre) => DB::raw(DB::getDriverName() === 'sqlite' ? "'{$pre}' || id || '@example.invalid'" : "CONCAT('{$pre}', id, '@example.invalid')");
        $staff = array_map(fn (Role $r) => $r->value, array_filter(Role::cases(), fn (Role $r) => $r->atLeast(Role::Editor)));
        $users = DB::table('users')->whereNotIn('role', $staff)->where('email', 'not like', '%@example.invalid')
            ->update(['email' => $cat('user'), 'remember_token' => null]);
        $subs = Schema::hasTable('newsletter_subscribers')
            ? DB::table('newsletter_subscribers')->where('email', 'not like', '%@example.invalid')->update(['email' => $cat('sub')])
            : 0;
        $msgs = Schema::hasTable('contact_messages') ? DB::table('contact_messages')->delete() : 0;
        foreach (['sessions', 'password_reset_tokens', 'jobs', 'failed_jobs'] as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->delete();
            }
        }
        $this->info("Scrubbed: {$users} user e-mails, {$subs} subscriber e-mails, {$msgs} contact messages; sessions and reset links cleared.");

        return self::SUCCESS;
    }
}
