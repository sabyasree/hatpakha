<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D315: festival greetings, one set per occasion: our own lines and, when checked word for word, one poet's line. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greeting_sets', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 40)->unique();
            $t->string('status', 12)->default('planned');   // planned | drafted | live | held
            $t->json('body')->nullable();                     // intro, groups[{key, lines[{bn, en}]}], poet{text, poet, work, urls}, faq
            $t->json('check')->nullable();                    // what the check removed and why
            $t->unsignedTinyInteger('tries')->default(0);
            $t->string('seo_description')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('greeting_sets');
    }
};
