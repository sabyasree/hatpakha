<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D231: under a piece a reader says how it left them (one of five), in place of the bare heart; the month's "পাঠকের পছন্দ". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_likes', fn (Blueprint $t) => $t->string('feeling', 12)->nullable());   // null: a heart from before
        Schema::table('posts', function (Blueprint $t) {
            $t->json('feeling_counts')->nullable();
            $t->string('readers_choice', 7)->nullable()->index();   // "2026-11": readers' choice of that month
        });
    }

    public function down(): void
    {
        Schema::table('post_likes', fn (Blueprint $t) => $t->dropColumn('feeling'));
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['feeling_counts', 'readers_choice']));
    }
};
