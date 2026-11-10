<?php

use Illuminate\Database\Migrations\Migration;

/** Owner (2026-11-08): "assign 3 AI writers to start writing serial stories with weekly episodes" (D108). */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        foreach (array_keys(\App\Ai\SerialWriter::WRITERS) as $writer) {
            $has = \App\Models\Serial::where('writer', $writer)->exists()
                || \App\Models\TeamRequest::where('agent', $writer)->where('job', 'start')->whereIn('status', ['queued', 'running'])->exists();
            if (! $has) {
                \App\Ai\TeamJobs::queue($writer, 'start');
                echo "  queued: {$writer}".PHP_EOL;
            }
        }
    }

    public function down(): void {}
};
