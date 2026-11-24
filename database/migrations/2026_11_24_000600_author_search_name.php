<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A4 (research 2026-11): search matched writers' names in PHP after loading every writer. The folded name
 * (App\Support\BnSearch::key of both names and the slug) is kept on the row so the database does the matching.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', fn (Blueprint $t) => $t->string('search_name', 500)->nullable());
        DB::table('authors')->orderBy('id')->select(['id', 'display_name', 'display_name_bn', 'slug'])->chunkById(500, function ($rows) {
            foreach ($rows as $a) {
                DB::table('authors')->where('id', $a->id)->update(['search_name' => \App\Models\Author::searchKey($a->display_name, $a->display_name_bn, $a->slug)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('authors', fn (Blueprint $t) => $t->dropColumn('search_name'));
    }
};
