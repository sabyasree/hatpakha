<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D252 (owner: "build A, B; a writer gets an e-mail when a comment is given; a reply option in comments; the commenter
 * gets to know of a reply; the e-mail link brings them straight to that comment").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->after('post_id')->constrained('comments')->cascadeOnDelete();   // a reply: one level deep
            $t->timestamp('reply_told_at')->nullable();   // the comment it answers: its writer has been told
        });
        Schema::table('posts', fn (Blueprint $t) => $t->timestamp('writer_live_told_at')->nullable());
        DB::table('posts')->update(['writer_live_told_at' => now()]);   // pieces already out are not announced again

        // B: a reader without an account says how a piece left them; once a phone (and a cap a network) a piece
        Schema::create('guest_feelings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->char('device', 64);   // a hash of the phone's own random id, never the id itself
            $t->char('net', 64);      // a hash of the address and the day, only to cap one network
            $t->string('feeling', 12);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['post_id', 'device']);
            $t->index(['post_id', 'net']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_feelings');
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('writer_live_told_at'));
        Schema::table('comments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
            $t->dropColumn('reply_told_at');
        });
    }
};
