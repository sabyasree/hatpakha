<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner + UX review (2026-11-11): each job shows its steps (received → who → working → ready), with times. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_requests', function (Blueprint $t) {
            $t->json('steps')->nullable()->after('attempts');
            $t->timestamp('seen_at')->nullable()->after('finished_at');   // a finished job stays "new" until opened
        });
    }

    public function down(): void
    {
        Schema::table('team_requests', fn (Blueprint $t) => $t->dropColumn(['steps', 'seen_at']));
    }
};
