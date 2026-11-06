<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The graphic designer's proposed issue-cover artwork (D51); becomes cover_path when the owner approves the issue. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->string('proposed_cover_path')->nullable()->after('cover_path'));
    }

    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->dropColumn('proposed_cover_path'));
    }
};
