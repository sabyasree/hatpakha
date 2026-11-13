<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D189: readers' comments under our Facebook and Instagram posts, and what the team answered. No names are kept. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $t->string('network', 10);                      // facebook | instagram
            $t->string('remote_id', 80)->unique();          // the comment's id on the network
            $t->text('text');
            $t->timestamp('commented_at')->nullable();
            $t->string('status', 10)->default('new');       // new | replied | skipped | flagged | failed | deleted
            $t->string('reason', 200)->nullable();          // why it was skipped or flagged (for the owner)
            $t->text('reply')->nullable();
            $t->string('reply_id', 80)->nullable();
            $t->timestamp('replied_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_comments');
    }
};
