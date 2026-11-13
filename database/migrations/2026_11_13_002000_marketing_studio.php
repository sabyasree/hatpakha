<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D182 (owner: "I want fully automatic… there is no reels creation"): the marketing plan's posts carry a format
 * (link, card, carousel, reel), what the studio should draw, when they go out, and what readers did with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $t) {
            $t->string('format', 12)->default('link');          // link | card | carousel | reel
            $t->string('plan_key', 60)->nullable()->unique();    // "2026-11-14:riddle": the planner never makes one twice
            $t->json('design')->nullable();                      // what the studio draws: {slides: [...], reel: {...}}
            $t->json('media')->nullable();                       // what it drew: {images: [...], video, cover}
            $t->timestamp('slot_at')->nullable()->index();       // when it should go out
            $t->text('comment')->nullable();                     // our own first comment (a riddle's answer)
            $t->timestamp('comment_at')->nullable();
            $t->string('fb_comment_id', 80)->nullable();
            $t->string('ig_container', 80)->nullable();          // a reel Instagram is still processing
            $t->json('metrics')->nullable();                     // reach, comments, shares… read back from the networks
            $t->timestamp('metrics_at')->nullable();
        });
        // The owner asked for fully automatic posting (2026-11-13). The switch stays on the marketing page.
        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(['key' => 'facebook.auto'], ['value' => '1']);
        }
    }

    public function down(): void
    {
        Schema::table('social_posts', fn (Blueprint $t) => $t->dropColumn(['format', 'plan_key', 'design', 'media', 'slot_at', 'comment', 'comment_at', 'fb_comment_id', 'ig_container', 'metrics', 'metrics_at']));
    }
};
