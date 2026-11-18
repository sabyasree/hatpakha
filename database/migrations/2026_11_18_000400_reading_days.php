<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D237: the days a signed-in reader read something, for "টানা N দিন পড়ছেন". Only for readers who keep their reading history on. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_days', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('day');   // the reader's day in Kolkata
            $t->primary(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_days');
    }
};
