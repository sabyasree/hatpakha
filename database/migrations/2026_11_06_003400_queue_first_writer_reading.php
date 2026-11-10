<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner asked (2026-11-06) to run the first reading of the honours shortlist now (D77, ~$0.40 for 20 writers).
 * This queues it exactly as the "এআই দিয়ে লেখা পড়ান" button does; the scheduler does it within a minute.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || DB::table('team_requests')->where('job', 'read_writers')->exists()) {
            return;
        }
        DB::table('team_requests')->insert([
            'agent' => 'editor', 'job' => 'read_writers', 'redo' => false, 'status' => 'queued',
            'requested_by' => DB::table('users')->where('role', 'admin')->orderBy('id')->value('id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
    }
};
