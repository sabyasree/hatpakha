<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D245 (owner): the desk can keep a player whose scores look wrong off the games' boards and rolls. Reversible. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('game_blocked_at')->nullable()->after('board_hidden'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('game_blocked_at'));
    }
};
