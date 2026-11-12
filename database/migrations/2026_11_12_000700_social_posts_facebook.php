<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Facebook Page posting (owner, 2026-11-12): when a post is due, what Facebook answered. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $t) {
            $t->timestamp('fb_scheduled_at')->nullable()->after('posted_by');
            $t->string('fb_post_id', 80)->nullable()->after('fb_scheduled_at');
            $t->string('fb_error', 300)->nullable()->after('fb_post_id');
            $t->index('fb_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $t) {
            $t->dropIndex(['fb_scheduled_at']);
            $t->dropColumn(['fb_scheduled_at', 'fb_post_id', 'fb_error']);
        });
    }
};
