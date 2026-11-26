<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D301: the "জেনে রাখুন" reference pages (/janun/…), written and checked by the desk, published on a schedule. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('janun_pages', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();            // the plan's key (App\Support\JanunPlan)
            $t->string('section', 20)->index();         // bhasha | puja | pathshala
            $t->string('kind', 10);                     // list | guide | study
            $t->string('slug', 120);
            $t->string('title', 200);
            $t->string('seo_title', 120)->nullable();
            $t->string('seo_description', 200)->nullable();
            $t->json('body')->nullable();               // the structured content (intro, entries, steps, mantras…)
            $t->json('check')->nullable();              // what the fact check found
            $t->string('status', 12)->default('planned')->index();   // planned | drafted | scheduled | published | held
            $t->timestamp('publish_at')->nullable()->index();
            $t->timestamp('checked_at')->nullable();
            $t->unsignedSmallInteger('tries')->default(0);
            $t->timestamps();
            $t->unique(['section', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('janun_pages');
    }
};
