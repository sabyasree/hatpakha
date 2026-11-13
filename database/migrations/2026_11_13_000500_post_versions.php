<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D171: a copy of the piece each time the writer sends it and each time the editor sends it back, so the desk can see what changed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);                 // submitted | sent_back | resubmitted
            $table->string('title', 400);
            $table->longText('body_html');
            $table->text('note')->nullable();           // the editor's note (sent_back) or the writer's (resubmitted)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['post_id', 'id']);
        });

        // Pieces already sent back before this existed: keep their text as it stands now (the writer has usually not
        // touched it yet), so the desk can see what changes when it comes back.
        foreach (\Illuminate\Support\Facades\DB::table('posts')->where('status', 'changes')->whereNull('deleted_at')->get(['id', 'title', 'body_html', 'review_note', 'reviewed_at', 'reviewed_by']) as $p) {
            \Illuminate\Support\Facades\DB::table('post_versions')->insert([
                'post_id' => $p->id, 'kind' => 'sent_back', 'title' => (string) $p->title, 'body_html' => (string) $p->body_html,
                'note' => $p->review_note ? mb_substr((string) $p->review_note, 0, 2000) : null, 'user_id' => $p->reviewed_by,
                'created_at' => $p->reviewed_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_versions');
    }
};
