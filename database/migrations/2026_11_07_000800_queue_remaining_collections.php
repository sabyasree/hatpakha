<?php

use Illuminate\Database\Migrations\Migration;

/** Owner (D97, 2026-11-07): "All 7 now": queue the remaining seed collections for the archivist. */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        foreach (array_keys(require database_path('content/collection-seeds.php')) as $slug) {
            $exists = \App\Models\Collection::where('slug', $slug)->exists();
            $waiting = \App\Models\TeamRequest::where('agent', 'archivist')->where('job', 'themed')->where('instruction', $slug)
                ->whereIn('status', ['queued', 'running'])->exists();
            if (! $exists && ! $waiting) {
                \App\Ai\TeamJobs::queue('archivist', 'themed', $slug);
                echo "  queued: {$slug}".PHP_EOL;
            }
        }
    }

    public function down(): void {}
};
