<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D243: the games' titles (মাসের সেরা, বছরের সেরা): the first three of a finished month or year, kept for good. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_titles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 5);              // month | year
            $t->string('period', 7);            // 2026-12 | 2027
            $t->unsignedTinyInteger('place');   // 1-3
            $t->unsignedInteger('points');
            $t->unsignedSmallInteger('days');   // days played (month) or months counted (year)
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['kind', 'period', 'user_id']);
            $t->index(['kind', 'period', 'place']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_titles');
    }
};
