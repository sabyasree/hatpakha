<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D113 (owner): feedback from real human readers on AI-written pieces, applied to the same piece (a rewrite the owner
 * accepts or not) and/or as a standing rule for future AI writing (this section, this serial, or all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writing_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $t->string('giver', 120)->nullable();               // who read it (name or description), as the owner wrote it
            $t->text('body');                                    // the feedback, in the reader's words
            $t->boolean('apply_now')->default(false);           // rewrite this piece
            $t->boolean('apply_future')->default(false);        // a standing rule for future pieces
            $t->string('scope', 10)->nullable();                 // section | serial | all
            $t->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('serial_id')->nullable()->constrained()->nullOnDelete();
            $t->text('lesson')->nullable();                      // the rule as given to the AI writers (editable)
            $t->boolean('active')->default(true);
            $t->string('rewrite_status', 10)->nullable();        // queued | done | failed
            $t->string('rewrite_error', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->longText('rewrite_html')->nullable();
            $t->string('rewrite_title', 200)->nullable();
            $t->text('rewrite_note')->nullable();
            $t->string('rewrite_kind', 10)->nullable();          // proposed | previous
            $t->timestamp('rewrite_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['rewrite_html', 'rewrite_title', 'rewrite_note', 'rewrite_kind', 'rewrite_at']));
        Schema::dropIfExists('writing_feedback');
    }
};
