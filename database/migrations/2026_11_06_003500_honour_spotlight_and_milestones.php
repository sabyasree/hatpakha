<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D78: the home page spotlight (a featured piece and one exact line from it) and writers' milestones. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->foreignId('honour_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('honour_quote', 300)->nullable();   // copied exactly from that piece
        });
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 32);                       // first | pieces_10 | pieces_25 | … | years_5 | years_10 …
            $table->string('label', 120);                     // what readers see, e.g. "১০০তম লেখা"
            $table->string('status', 16)->default('proposed'); // proposed | approved | dismissed
            $table->timestamp('reached_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['author_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
        Schema::table('authors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('honour_post_id');
            $table->dropColumn('honour_quote');
        });
    }
};
