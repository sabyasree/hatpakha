<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Privacy-friendly read counts (D54): daily totals per piece and source. No cookies, no IP, no user id. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_views', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('search')->default(0);
            $table->unsignedInteger('social')->default(0);
            $table->unsignedInteger('internal')->default(0);
            $table->primary(['post_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');
    }
};
