<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D77: the AI editor's reading of a sample of each shortlisted writer's work (for the honours shortlist only). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->decimal('quality_score', 3, 1)->nullable();
            $table->string('quality_note', 400)->nullable();
            $table->timestamp('quality_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('authors', fn (Blueprint $table) => $table->dropColumn(['quality_score', 'quality_note', 'quality_at']));
    }
};
