<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner (2026-11-12): each issue's e-book is built automatically and goes to readers only after the owner approves it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $t) {
            $t->string('ebook_path')->nullable();          // the latest build (private)
            $t->string('ebook_hash', 40)->nullable();      // what it was built from
            $t->timestamp('ebook_built_at')->nullable();
            $t->string('ebook_public_path')->nullable();   // the approved build readers download
            $t->timestamp('ebook_approved_at')->nullable();
            $t->foreignId('ebook_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $t) {
            $t->dropConstrainedForeignId('ebook_approved_by');
            $t->dropColumn(['ebook_path', 'ebook_hash', 'ebook_built_at', 'ebook_public_path', 'ebook_approved_at']);
        });
    }
};
