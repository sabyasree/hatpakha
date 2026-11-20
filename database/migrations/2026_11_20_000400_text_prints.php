<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D248 (owner: "a declined piece must never come out under someone else's name"): a fingerprint of every piece's text
 * (hashes of runs of five words), so a new submission can be compared with everything ever sent, declined pieces
 * included. Filled for the pieces already here by `TextPrints::backfill()` (scheduler, a batch at a time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_prints', function (Blueprint $t) {
            $t->unsignedBigInteger('post_id');
            $t->unsignedInteger('hash');
            $t->primary(['post_id', 'hash']);
            $t->index('hash');
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->unsignedInteger('print_count')->nullable()->after('plagiarism_at');   // null: not fingerprinted yet
            $t->json('own_match')->nullable()->after('print_count');                  // [{post_id, title, writer, status, share}]
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_prints');
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['print_count', 'own_match']));
    }
};
