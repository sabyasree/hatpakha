<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 3d/3e: AI review reports (editor-only advice), every AI call's cost, editable settings (rubric).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);          // anthropic | openai
            $table->string('model', 60);
            $table->string('status', 16);            // done | failed | skipped
            $table->json('report')->nullable();      // the structured review
            $table->unsignedTinyInteger('score')->nullable()->index();
            $table->string('error', 300)->nullable();
            // Blind provider test (3e): the editor scores reports without knowing the provider.
            $table->string('blind_run', 20)->nullable()->index();
            $table->char('blind_label', 1)->nullable();
            $table->unsignedTinyInteger('editor_score')->nullable();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('model', 60);
            $table->string('purpose', 30);           // review | tags | blind_test
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('ai_reviews');
    }
};
