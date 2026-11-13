<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D166 (owner, 2026-11-13): "আমার পড়া". For signed-in readers who keep it on (on by default, a switch turns it off):
 * which pieces they opened and how far they read, so a piece opens where they stopped. Only they see it; a year old is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_history', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('progress')->default(0);   // percent of the piece read, the furthest point
            $table->timestamp('read_at')->index();
            $table->primary(['user_id', 'post_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('reading_history')->default(true)->after('mail_opt_out');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_history');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('reading_history'));
    }
};
