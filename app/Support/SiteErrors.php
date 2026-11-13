<?php

namespace App\Support;

use App\Models\SiteError;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * D191 (owner, 2026-11-14: "why do these things break; how to make sure they will not happen"): every error the site
 * reports is also kept in a small table, one row per kind of error with a count, so that
 *   - /desk → সাইটের ত্রুটি shows them in plain words (a red number in the menu when there is a new one),
 *   - the morning note to the owner says how many new ones there were,
 * instead of an error sitting unseen in a log file until someone runs into it.
 *
 * Kept: where (desk, site or a background job), the page's address without its query, the kind of error, its first
 * words with e-mail addresses masked, the file and line. Never the request's data, never a person. 30 days.
 * Recording must never itself break anything: every failure here is swallowed.
 */
final class SiteErrors
{
    private static bool $busy = false;

    public static function record(Throwable $e): void
    {
        if (self::$busy) {
            return;   // an error while recording an error
        }
        self::$busy = true;
        try {
            $base = base_path().DIRECTORY_SEPARATOR;
            // The first frame inside our own code says more than a line deep in the framework.
            $place = str_replace($base, '', $e->getFile()).':'.$e->getLine();
            if (str_starts_with($place, 'vendor')) {
                foreach ($e->getTrace() as $f) {
                    $file = str_replace($base, '', (string) ($f['file'] ?? ''));
                    if ($file !== '' && ! str_starts_with($file, 'vendor') && ! str_starts_with($file, 'storage')) {
                        $place = $file.':'.($f['line'] ?? 0);
                        break;
                    }
                }
            }
            $request = app()->runningInConsole() ? null : request();
            $path = $request ? '/'.ltrim((string) $request->path(), '/') : null;
            // A Livewire call comes from the page the person is on.
            if ($request && str_starts_with((string) $path, '/livewire') && ($ref = $request->headers->get('referer'))) {
                $path = (string) parse_url($ref, PHP_URL_PATH) ?: $path;
            }
            $area = $request === null ? 'job' : (str_starts_with((string) $path, '/desk') ? 'desk' : 'site');
            $message = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '…@…', (string) $e->getMessage());
            $message = preg_replace('/\(Connection: .*$/s', '', (string) $message);   // SQL and its values are not kept
            $hash = sha1($e::class.'|'.$place.'|'.$area);
            $now = now();
            $updated = DB::table('site_errors')->where('hash', $hash)->update(['count' => DB::raw('count + 1'), 'last_at' => $now, 'path' => $path ? mb_substr($path, 0, 200) : null]);
            if (! $updated) {
                DB::table('site_errors')->insertOrIgnore(['hash' => $hash, 'area' => $area, 'what' => mb_substr(class_basename($e), 0, 160), 'message' => mb_substr(trim((string) $message), 0, 300),
                    'place' => mb_substr($place, 0, 200), 'path' => $path ? mb_substr($path, 0, 200) : null, 'count' => 1, 'first_at' => $now, 'last_at' => $now]);
            }
        } catch (Throwable) {
            // the database may be the thing that is down
        } finally {
            self::$busy = false;
        }
    }

    /** Kinds of error first seen in the last day, and how many times all errors happened in it. @return array{new: int, times: int} */
    public static function lastDay(): array
    {
        return rescue(fn () => [
            'new' => SiteError::where('first_at', '>=', now()->subDay())->count(),
            'times' => (int) SiteError::where('last_at', '>=', now()->subDay())->count(),
        ], ['new' => 0, 'times' => 0], false);
    }

    public static function unseen(): int
    {
        return (int) rescue(fn () => SiteError::whereNull('seen_at')->where('last_at', '>=', now()->subDays(7))->count(), 0, false);
    }

    public static function prune(): int
    {
        return SiteError::where('last_at', '<', now()->subDays(30))->delete();
    }

    /** In plain words, for the owner. */
    public static function where(SiteError $e): string
    {
        return ['desk' => __('ডেস্কে'), 'site' => __('সাইটে'), 'job' => __('পেছনের কাজে')][$e->area] ?? $e->area;
    }
}
