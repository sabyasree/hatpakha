<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D314: government holiday lists (West Bengal, Bangladesh), from the official notice, each date checked on two pages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_lists', function (Blueprint $t) {
            $t->id();
            $t->string('country', 20);                 // west-bengal | bangladesh
            $t->unsignedSmallInteger('year');
            $t->string('notice')->nullable();          // the notification's number and date, as published
            $t->string('notice_url', 500)->nullable();
            $t->string('status', 12)->default('searching');   // searching | verified | held
            $t->string('note')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
            $t->unique(['country', 'year']);
        });
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->foreignId('holiday_list_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->string('name');
            $t->string('kind', 60)->nullable();        // the notice's own category (সাধারণ, নির্বাহী আদেশে, ঐচ্ছিক, …)
            $t->boolean('moon')->default(false);       // চাঁদ দেখা সাপেক্ষে
            $t->json('sources')->nullable();
            $t->string('status', 12)->default('verified');
            $t->timestamps();
            $t->index(['holiday_list_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('holiday_lists');
    }
};
