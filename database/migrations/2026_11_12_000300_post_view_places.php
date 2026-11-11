<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Writers' numbers (2026-11-12): where a piece is read (country, and city when Cloudflare sends it). Totals only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_view_places', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->string('place', 80);   // "City|CC" or "|CC"
            $t->unsignedInteger('views')->default(0);
            $t->unique(['post_id', 'place']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_view_places');
    }
};
