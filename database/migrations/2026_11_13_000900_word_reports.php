<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D179: players report a wrong word, clue or riddle; the fix is kept here (the lists in the code stay as they are). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_reports', function (Blueprint $t) {
            $t->id();
            $t->string('game', 8);                     // word | riddle
            $t->string('word', 60);                    // the answer as shown
            $t->string('reason', 12);                  // spelling | clue | hard | other
            $t->string('note', 300)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->char('sender_hash', 64);
            $t->string('status', 10)->default('new');  // new | kept | fixed | removed
            $t->string('outcome', 300)->nullable();    // what was decided, in a line
            $t->timestamps();
            $t->unique(['game', 'word', 'sender_hash']);
            $t->index(['status', 'game', 'word']);
        });
        Schema::create('word_fixes', function (Blueprint $t) {
            $t->id();
            $t->string('game', 8);
            $t->string('word', 60);                    // the entry in the list this changes
            $t->string('action', 8);                   // replace | remove
            $t->string('new_word', 60)->nullable();
            $t->string('new_clue', 400)->nullable();
            $t->string('reason', 300)->nullable();
            $t->date('from_day');                      // never changes a puzzle already being played
            $t->timestamps();
            $t->unique(['game', 'word']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_fixes');
        Schema::dropIfExists('word_reports');
    }
};
