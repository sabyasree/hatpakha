<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D303: the first day of each West Bengal month, calculated and then checked against two published calendars. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bangla_months', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');            // Bengali year, e.g. 1433
            $t->unsignedTinyInteger('month');            // 1 = বৈশাখ
            $t->date('starts_on');                       // calculated
            $t->string('status', 12)->default('computed');   // computed | verified | held
            $t->json('sources')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['year', 'month']);
            $t->index(['status', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bangla_months');
    }
};
