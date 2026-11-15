<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D210: the small fixes put into a piece (by the AI team or from the desk) are kept one by one, so each can be taken back. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_versions', fn (Blueprint $t) => $t->json('changes')->nullable());
    }

    public function down(): void
    {
        Schema::table('post_versions', fn (Blueprint $t) => $t->dropColumn('changes'));
    }
};
