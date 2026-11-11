<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner (2026-11-11): a request that failed for a passing reason is tried again by itself (up to 3 tries). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_requests', fn (Blueprint $t) => $t->unsignedTinyInteger('attempts')->default(0)->after('status'));
    }

    public function down(): void
    {
        Schema::table('team_requests', fn (Blueprint $t) => $t->dropColumn('attempts'));
    }
};
