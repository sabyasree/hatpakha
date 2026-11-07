<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database backup in plain PHP (D92): no mysqldump, because Hostinger's PHP cannot start other programs
 * (proc_open is off for cron). Writes a gzipped .sql into storage/app/private/backups (never web-visible),
 * keeps the newest N. Runs before every deploy's migrations and nightly from the scheduler.
 * Restore: gunzip < file.sql.gz | mysql -u USER -p DATABASE   (or hPanel → phpMyAdmin → Import).
 */
class BackupDatabase extends Command
{
    protected $signature = 'hatpakha:backup-db {--keep=7 : How many backups to keep} {--label=manual : Word in the file name, e.g. predeploy}';

    protected $description = 'Back up the database to storage/app/private/backups (gzipped SQL)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            $this->warn('Backups are for the MySQL database only; skipped.');

            return self::SUCCESS;
        }
        $dir = storage_path('app/private/backups');
        @mkdir($dir, 0700, true);
        $label = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $this->option('label'))) ?: 'manual';
        $file = $dir.'/db-'.now()->format('Ymd-His').'-'.$label.'.sql.gz';
        $tmp = $file.'.part';
        $gz = gzopen($tmp, 'wb6');
        if (! $gz) {
            $this->error("Cannot write {$tmp}");

            return self::FAILURE;
        }
        $pdo = DB::getPdo();
        $w = fn (string $s) => gzwrite($gz, $s);
        $w("-- Hatpakha database backup ".now()->toIso8601String()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $rows = 0;
        try {
            $db = DB::getDatabaseName();
            foreach (Schema::getTables() as $t) {
                if (($t['schema'] ?? $db) !== $db) {
                    continue;   // only this site's database, never others on the same server (e.g. the old WordPress one)
                }
                $table = $t['name'];
                $create = (array) DB::selectOne('SHOW CREATE TABLE `'.str_replace('`', '``', $table).'`');
                $w("DROP TABLE IF EXISTS `{$table}`;\n".(array_values($create)[1] ?? '').";\n\n");
                $cols = array_column(Schema::getColumns($table), 'name');
                $query = DB::table($table);
                $iter = in_array('id', $cols, true) ? $query->lazyById(500) : $query->orderByRaw('1')->lazy(500);
                $batch = [];
                foreach ($iter as $row) {
                    $batch[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row)).')';
                    $rows++;
                    if (count($batch) >= 200) {
                        $w("INSERT INTO `{$table}` VALUES ".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    $w("INSERT INTO `{$table}` VALUES ".implode(",\n", $batch).";\n");
                }
                $w("\n");
            }
            $w("SET FOREIGN_KEY_CHECKS=1;\n-- end\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($tmp);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
        gzclose($gz);
        rename($tmp, $file);
        @chmod($file, 0600);

        $keep = max(1, (int) $this->option('keep'));
        $all = glob($dir.'/db-*-'.$label.'.sql.gz') ?: [];   // each kind (nightly, predeploy…) keeps its own
        rsort($all);
        foreach (array_slice($all, $keep) as $old) {
            @unlink($old);
        }
        $this->info(sprintf('Backed up %d rows to %s (%.1f MB)', $rows, $file, filesize($file) / 1048576));

        return self::SUCCESS;
    }
}
