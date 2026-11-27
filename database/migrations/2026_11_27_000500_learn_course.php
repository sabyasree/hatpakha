<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D321: বাংলা শিখি: recordings uploaded at the desk, worksheet downloads counted by day, a reading level on children's pieces. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_audio', function (Blueprint $t) {
            $t->id();
            $t->string('text', 120)->unique();          // the letter, word or sentence it says, exactly as in the course
            $t->string('path');                          // under public/media/learn-audio/
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('learn_downloads', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('file', 80);                      // e.g. first-letters-1-a4
            $t->unsignedInteger('count')->default(0);    // a total only: no address, no cookie, nothing about the person
            $t->unique(['day', 'file']);
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->unsignedTinyInteger('reading_unit')->nullable();   // ছোটদের pieces: readable after this unit of বাংলা শিখি
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_audio');
        Schema::dropIfExists('learn_downloads');
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('reading_unit'));
    }
};
