<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Proofreader and plagiarism checker results (D50), shown to the editor before approval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('proof')->nullable()->after('seo_done_at');          // {edits, title_fix, note}
            $table->timestamp('proof_at')->nullable()->after('proof');
            $table->json('plagiarism')->nullable()->after('proof_at');        // {verdict, matches[], note}
            $table->timestamp('plagiarism_at')->nullable()->after('plagiarism');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['proof', 'proof_at', 'plagiarism', 'plagiarism_at']);
        });
    }
};
