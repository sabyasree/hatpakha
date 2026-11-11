<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner (2026-11-11): giving work is a conversation; when unsure, the editor-in-chief asks back and the owner replies. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_requests', fn (Blueprint $t) => $t->json('conversation')->nullable()->after('instruction'));
    }

    public function down(): void
    {
        Schema::table('team_requests', fn (Blueprint $t) => $t->dropColumn('conversation'));
    }
};
