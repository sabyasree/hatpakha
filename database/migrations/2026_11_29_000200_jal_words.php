<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D330 (game designers: "a real word turned down is the most frustrating moment"): words অক্ষরজাল learns from the
 * magazine itself, every night, with no one to tend the list. A word counts from the day after it was added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jal_words', function (Blueprint $t) {
            $t->id();
            $word = $t->string('word', 40);
            if (DB::getDriverName() === 'mysql') {
                $word->collation('utf8mb4_bin');   // D327: Bengali compared letter for letter
            }
            $word->unique();
            $t->unsignedInteger('posts');
            $t->date('added_on');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jal_words');
    }
};
