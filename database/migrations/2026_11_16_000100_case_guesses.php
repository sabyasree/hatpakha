<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D213: readers guess who did it in a detective case, before its last episode. One guess a reader a case. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_guesses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('serial_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('suspect', 200);              // one of the case's people, by name
            $t->string('why', 600)->nullable();      // the reader's reasoning, shown to nobody but the desk
            $t->boolean('show_name')->default(true); // may their name be listed if they were right
            $t->timestamps();
            $t->unique(['serial_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_guesses');
    }
};
