<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marketing expert's ready-to-post drafts (D51). The owner copies and posts them; nothing is posted automatically. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);                       // piece | issue | weekly | spotlight | festival | contest
            $table->nullableMorphs('subject');
            $table->string('title');
            $table->json('texts');                            // {facebook, whatsapp, instagram, quote?, plan?}
            $table->string('status', 12)->default('ready');   // ready | posted | dismissed
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
