<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Instagram posting (owner, 2026-11-12): what Instagram answered, and where the post is. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $t) {
            $t->string('ig_media_id', 80)->nullable()->after('fb_error');
            $t->string('ig_permalink', 300)->nullable()->after('ig_media_id');
            $t->string('ig_error', 300)->nullable()->after('ig_permalink');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', fn (Blueprint $t) => $t->dropColumn(['ig_media_id', 'ig_permalink', 'ig_error']));
    }
};
