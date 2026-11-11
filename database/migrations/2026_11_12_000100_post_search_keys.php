<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spelling-forgiving search (2026-11-12): folded keys of each piece's title/writers/tags and text. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->string('search_title', 1000)->nullable();
            $t->longText('search_body')->nullable();
        });
        \App\Models\Post::with('authors', 'tags')->where('status', 'published')->chunkById(200, function ($posts) {
            foreach ($posts as $p) {
                \App\Support\BnSearch::index($p);
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['search_title', 'search_body']));
    }
};
