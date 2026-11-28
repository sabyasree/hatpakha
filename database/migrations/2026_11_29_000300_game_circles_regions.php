<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D331 (owner's list): a team can be private, a family's or friends' circle that is never on the public list; a
 * player can say where they play from, for the friendly contest between places; one-time festival keepsakes; the
 * talk after today's games; riddles sent by readers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_teams', function (Blueprint $t) {
            $t->boolean('private')->default(false);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('game_region', 40)->nullable();
        });
        Schema::create('game_keepsakes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('festival', 40);
            $t->unsignedSmallInteger('year');
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'festival', 'year']);
        });
        Schema::create('game_talk', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->string('body', 400);
            $t->string('status', 12)->default('new');   // new | shown | hidden
            $t->string('reason')->nullable();
            $t->timestamps();
            $t->index(['day', 'status']);
        });
        Schema::create('reader_riddles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('riddle', 300);
            $answer = $t->string('answer', 40);
            if (DB::getDriverName() === 'mysql') {
                $answer->collation('utf8mb4_bin');   // D327
            }
            $t->string('status', 12)->default('new');   // new | ok | no | used
            $t->string('reason')->nullable();
            $t->date('shown_on')->nullable()->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_riddles');
        Schema::dropIfExists('game_talk');
        Schema::dropIfExists('game_keepsakes');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('game_region'));
        Schema::table('game_teams', fn (Blueprint $t) => $t->dropColumn('private'));
    }
};
