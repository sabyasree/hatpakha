<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D191: errors the site ran into, one row per kind of error, so the owner hears of them instead of finding them by accident. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_errors', function (Blueprint $t) {
            $t->id();
            $t->string('hash', 40)->unique();          // the same error from the same line is one row
            $t->string('area', 8);                     // desk | site | job
            $t->string('what', 160);                   // the kind of error (class name)
            $t->string('message', 300);                // first words; e-mail addresses masked
            $t->string('place', 200);                  // file:line inside the project
            $t->string('path', 200)->nullable();       // the page's address (no query string)
            $t->unsignedInteger('count')->default(1);
            $t->timestamp('first_at');
            $t->timestamp('last_at')->index();
            $t->timestamp('seen_at')->nullable();      // the owner pressed "seen"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_errors');
    }
};
