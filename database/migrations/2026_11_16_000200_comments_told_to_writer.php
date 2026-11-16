<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** D217: writers are told of new comments on their pieces, once a day. Comments from before today are not sent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', fn (Blueprint $t) => $t->timestamp('writer_told_at')->nullable());
        DB::table('comments')->update(['writer_told_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $t) => $t->dropColumn('writer_told_at'));
    }
};
