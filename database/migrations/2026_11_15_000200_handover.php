<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D208 (owner: "do all these"): jobs handed to the AI team. When a strong piece, or the readers' letter, goes by itself. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->timestamp('auto_publish_at')->nullable();   // selected by itself at this time, unless stopped
            $t->boolean('auto_stopped')->default(false);    // the owner said "not by itself" for this piece
        });
        Schema::table('newsletters', fn (Blueprint $t) => $t->timestamp('auto_send_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['auto_publish_at', 'auto_stopped']));
        Schema::table('newsletters', fn (Blueprint $t) => $t->dropColumn('auto_send_at'));
    }
};
