<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D227: comments under posts the owner put on the page by hand (and boosted) are read too; such a comment has no site post. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_comments', function (Blueprint $t) {
            $t->unsignedBigInteger('social_post_id')->nullable()->change();
            $t->text('post_text')->nullable();   // the words of the owner's own post, so the answer fits it
        });
    }

    public function down(): void
    {
        Schema::table('social_comments', fn (Blueprint $t) => $t->dropColumn('post_text'));
    }
};
