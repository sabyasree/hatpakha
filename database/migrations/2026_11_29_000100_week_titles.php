<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D329 (owner: "can we also add more smaller like সপ্তাহের সেরা too, so people will get engaged"): game titles get a
 * third kind, the week, whose period is the Sunday it began (2026-11-29), longer than a month's 2026-12.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_titles', function (Blueprint $t) {
            $t->string('period', 10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('game_titles', function (Blueprint $t) {
            $t->string('period', 7)->change();
        });
    }
};
