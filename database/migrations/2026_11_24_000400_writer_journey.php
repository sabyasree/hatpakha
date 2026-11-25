<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Writer features (owner, 2026-11-24): the first time an editor opens a sent piece in the desk (the writer's tracker
 * says "সম্পাদক পড়ছেন" only from then), and up to three pieces a writer pins on their page ("নির্বাচিত লেখা").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('desk_opened_at')->nullable()->after('reviewed_at');
        });
        Schema::table('authors', function (Blueprint $table) {
            $table->json('pinned_post_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('desk_opened_at'));
        Schema::table('authors', fn (Blueprint $table) => $table->dropColumn('pinned_post_ids'));
    }
};
