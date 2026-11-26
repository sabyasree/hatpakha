<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D302: tithi and puja times, found on the web and checked on two pages, shown in any time zone. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_times', function (Blueprint $t) {
            $t->id();
            $t->string('festival', 40);
            $t->unsignedSmallInteger('cycle');            // the Gregorian year of the festival
            $t->unsignedTinyInteger('position')->default(0);
            $t->string('label');                          // e.g. মহাষ্টমী
            $t->date('day')->nullable();                  // the day it is kept in Kolkata
            $t->dateTime('starts_at')->nullable();        // UTC; null for a day-only event
            $t->dateTime('ends_at')->nullable();
            $t->json('sources')->nullable();
            $t->string('status', 12)->default('verified');   // verified | held
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['festival', 'cycle', 'label']);
            $t->index(['status', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_times');
    }
};
