<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The writer's own reading of their piece (D67): uploaded by the writer, shown only after the owner approves. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->string('audio_path')->nullable()->after('featured_image_alt');
            $t->string('audio_status', 10)->nullable()->after('audio_path');   // pending | approved
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['audio_path', 'audio_status']));
    }
};
