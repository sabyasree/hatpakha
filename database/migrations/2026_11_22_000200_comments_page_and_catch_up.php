<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D253 (owner: "new notifications go by e-mail, but the last 7 days' comments need to be sent this time, as the feature
 * releases today. And in the profile they can see the comments and replies, and reply from there too").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('comments_seen_at')->nullable());   // "নতুন" on the account's মতামত page
        // once: the last seven days' published comments go to their writers again, in one e-mail a writer (CommentNews)
        DB::table('comments')->where('status', 'approved')->whereNull('parent_id')->where('created_at', '>=', now()->subDays(7))->update(['writer_told_at' => null]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('comments_seen_at'));
    }
};
