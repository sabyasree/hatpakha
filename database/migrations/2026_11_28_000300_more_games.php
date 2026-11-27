<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D328: the weekly magazine quiz (drafted by the AI from the week's pieces, approved at the desk), challenges between
 * friends (সাপ্তাহিক লড়াই) and teams (পাড়ার দল). The daily games need no tables: their words are content files and the
 * scores go to game_scores like the others.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_quizzes', function (Blueprint $t) {
            $t->id();
            $t->date('week')->unique();                     // the Sunday it opens (Kolkata)
            $t->string('status', 10)->default('draft');     // draft | approved
            $t->json('questions');                          // [{q, options[4], answer 0-3, post_id, why}]
            $t->string('note')->nullable();                 // why the AI could not draft, or the desk's note
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('game_challenges', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique();
            $t->date('week');                               // whose question set it uses
            $t->timestamps();
        });
        Schema::create('game_challenge_plays', function (Blueprint $t) {
            $t->id();
            $t->foreignId('game_challenge_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 40);
            $t->unsignedTinyInteger('score');
            $t->unsignedInteger('seconds');
            $t->boolean('started')->default(false);         // the one who made the link
            $t->string('sender_hash', 64);
            $t->timestamp('created_at')->nullable();
            $t->unique(['game_challenge_id', 'sender_hash']);
        });
        Schema::create('game_teams', function (Blueprint $t) {
            $t->id();
            $t->string('code', 12)->unique();
            $t->string('name', 60);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // who made it
            $t->timestamp('hidden_at')->nullable();         // taken off the boards by the desk
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('game_team_id')->nullable()->constrained('game_teams')->nullOnDelete();
            $t->timestamp('game_team_joined_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('game_team_id');
            $t->dropColumn('game_team_joined_at');
        });
        Schema::dropIfExists('game_teams');
        Schema::dropIfExists('game_challenge_plays');
        Schema::dropIfExists('game_challenges');
        Schema::dropIfExists('game_quizzes');
    }
};
