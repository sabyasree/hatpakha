<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D188: personal invitations to the regular writers for the ফিরে আসা সংখ্যা, one per writer, with what became of each. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_invites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('author_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->nullable()->constrained()->nullOnDelete();   // the piece of theirs the letter speaks of
            $t->string('subject', 160);
            $t->text('body');
            $t->string('status', 10)->default('drafted');   // drafted | held | sent | failed
            $t->timestamp('send_after')->nullable();        // a drafted letter waits until then, so it can be read or stopped
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('reminded_at')->nullable();
            $t->string('answer', 8)->nullable();            // yes | later | no
            $t->timestamp('answered_at')->nullable();
            $t->foreignId('piece_id')->nullable()->constrained('posts')->nullOnDelete();   // what they sent after the letter
            $t->timestamps();
            $t->index(['status', 'send_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_invites');
    }
};
