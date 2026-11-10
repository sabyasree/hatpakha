<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI SEO expert (D46): its fields on each piece. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('seo_keyphrase', 120)->nullable()->after('seo_description');
            $table->string('featured_image_alt', 200)->nullable()->after('featured_image_height');
            $table->string('share_text', 300)->nullable()->after('seo_keyphrase');      // WhatsApp / Facebook text
            $table->json('related_ids')->nullable()->after('share_text');               // "আরও পড়ুন" picks
            $table->json('seo_proposal')->nullable()->after('related_ids');             // when set to "ask me first"
            $table->timestamp('seo_done_at')->nullable()->after('seo_proposal');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['seo_keyphrase', 'featured_image_alt', 'share_text', 'related_ids', 'seo_proposal', 'seo_done_at']);
        });
    }
};
