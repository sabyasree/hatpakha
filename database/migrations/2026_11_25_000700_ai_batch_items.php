<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D296: AI work that can wait a few hours goes to Anthropic's Message Batches at half price (App\Ai\Batch). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_batch_items', function (Blueprint $t) {
            $t->id();
            $t->string('job', 32)->index();          // proof | backlog_screen | seo_missing | topics
            $t->json('post_ids');                    // the piece(s) the answer is for
            $t->longText('request');                 // purpose, system, user, schema, model, max (json)
            $t->string('batch_id', 80)->nullable()->index();
            $t->string('status', 12)->default('queued')->index();   // queued | sent | done | failed
            $t->string('error', 200)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_batch_items');
    }
};
