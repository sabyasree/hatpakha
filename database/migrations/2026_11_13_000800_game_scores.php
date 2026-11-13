<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D177: the day's result of a signed-in player, for the leaderboard and the game badges. One row per game per day. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('game', 8);              // word | riddle | cross
            $t->date('day');                    // the Kolkata date of the puzzle
            $t->unsignedSmallInteger('points'); // 0-100
            $t->unsignedInteger('seconds');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['user_id', 'game', 'day']);
            $t->index(['day', 'points']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->boolean('board_hidden')->default(false)->after('reading_history'));
    }

    public function down(): void
    {
        Schema::dropIfExists('game_scores');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('board_hidden'));
    }
};
