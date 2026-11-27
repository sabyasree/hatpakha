<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D310: each day's tithi (Kolkata), from the published বিশুদ্ধ সিদ্ধান্ত page, checked against our own calculation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tithi_days', function (Blueprint $t) {
            $t->id();
            $t->date('day')->unique();
            $t->unsignedTinyInteger('tithi');               // 1-30 at sunrise
            $t->json('turns')->nullable();                // [[ends_at UTC, next tithi]...] during the day; [] for (সারাদিন)
            $t->string('status', 12)->default('verified');  // verified | held
            $t->string('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tithi_days');
    }
};
