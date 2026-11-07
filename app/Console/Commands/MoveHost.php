<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Launch (D90): production starts as a copy of the staging database, so every saved "new.hatpakha.com"
 * (links the AI wrote into marketing posts, newsletters, honours, page and piece bodies…) becomes "hatpakha.com".
 * Looks at every text column of every table. Shows counts first (--dry); refuses to run on a site whose
 * APP_URL is not the new host, so it can never rewrite staging by mistake. Safe to run again.
 *
 *   php artisan hatpakha:move-host --dry
 *   php artisan hatpakha:move-host
 */
class MoveHost extends Command
{
    protected $signature = 'hatpakha:move-host {--from=new.hatpakha.com} {--to=hatpakha.com} {--dry : Only count, change nothing}';

    protected $description = 'Rewrite the old host name saved anywhere in the database (after copying staging to production)';

    // History (audit_logs) stays exactly as it was written.
    private const SKIP = ['audit_logs', 'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    private const TEXT = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json', 'clob', 'string'];

    public function handle(): int
    {
        $from = strtolower(trim((string) $this->option('from')));
        $to = strtolower(trim((string) $this->option('to')));
        $here = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($from === '' || $to === '' || $from === $to) {
            $this->error('Give two different host names.');

            return self::FAILURE;
        }
        if (! $this->option('dry') && $here !== $to) {
            $this->error("This site's APP_URL is {$here}, not {$to}. Run it on the new site only (or use --dry to look).");

            return self::FAILURE;
        }

        $total = 0;
        $rows = [];
        foreach (Schema::getTables() as $t) {
            $table = $t['name'];
            if (in_array($table, self::SKIP, true)) {
                continue;
            }
            foreach (Schema::getColumns($table) as $c) {
                if (! in_array(strtolower((string) $c['type_name']), self::TEXT, true)) {
                    continue;
                }
                $col = $c['name'];
                $n = DB::table($table)->where($col, 'like', '%'.$from.'%')->count();
                if (! $n) {
                    continue;
                }
                if (! $this->option('dry')) {
                    $q = DB::getQueryGrammar();
                    DB::table($table)->where($col, 'like', '%'.$from.'%')
                        ->update([$col => DB::raw('REPLACE('.$q->wrap($col).', '.DB::getPdo()->quote($from).', '.DB::getPdo()->quote($to).')')]);
                }
                $rows[] = [$table, $col, $n];
                $total += $n;
            }
        }
        $this->table(['table', 'column', 'rows'], $rows);
        $this->info(($this->option('dry') ? 'Would change ' : 'Changed ').$total." rows: {$from} → {$to}");
        if (! $this->option('dry') && $total) {
            \App\Models\AuditLog::record('site.host_moved', null, ['from' => $from, 'to' => $to, 'rows' => $total]);
            $this->line('Now run: php artisan optimize:clear');
        }

        return self::SUCCESS;
    }
}
