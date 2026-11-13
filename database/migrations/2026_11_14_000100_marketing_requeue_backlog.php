<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner (2026-11-14): on the first night, two weeks of older ready posts were all given the same evening. They go back
 * to waiting; the plan now takes one older post an evening, after that day's own post. Planned posts (riddle, reel)
 * and anything already sent are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('social_posts')->where('status', 'ready')->whereNull('plan_key')->whereNull('fb_post_id')->whereNull('ig_media_id')->whereNull('ig_container')
            ->where('kind', '!=', 'kolom')->where('slot_at', '>', now())->update(['slot_at' => null]);
    }

    public function down(): void
    {
    }
};
