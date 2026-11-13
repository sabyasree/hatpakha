<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D172: one line at the end of a serial episode about what comes next ("পরের পর্বে: …"), so readers come back. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->string('episode_teaser', 300)->nullable()->after('episode_summary'));
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('episode_teaser'));
    }
};
