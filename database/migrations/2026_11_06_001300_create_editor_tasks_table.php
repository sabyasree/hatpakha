<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI editor's prepared jobs waiting for the owner (D48): writer follow-ups and welcome notes,
 * weekly picks, newsletter drafts, monthly reports, site-health findings. Nothing is sent or shown
 * to readers until the owner acts on a task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editor_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();          // followup | welcome | pick | newsletter | report | health
            $table->string('status', 15)->default('open')->index();   // open | done | dismissed
            $table->nullableMorphs('subject');
            $table->string('title');
            $table->json('payload')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->timestamp('last_sent_at')->nullable()->after('consented_at');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn('last_sent_at');
        });
        Schema::dropIfExists('editor_tasks');
    }
};
