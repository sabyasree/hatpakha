<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D102: which machine voice read a piece, so the desk can offer to redo old ones after a voice change. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->string('audio_voice', 20)->nullable()->after('audio_ai'));
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('audio_voice'));
    }
};
