<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Writing contests (D53): entries come through the normal submit form; the owner picks winners. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('theme')->nullable();
            $table->text('rules')->nullable();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 12)->default('draft');   // draft | open | judging | done
            $table->json('shortlist')->nullable();             // AI: [{post_id, why}]
            $table->json('winners')->nullable();               // owner: [{post_id, place}]
            $table->foreignId('results_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('posts', fn (Blueprint $table) => $table->foreignId('contest_id')->nullable()->after('section_id')->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropConstrainedForeignId('contest_id'));
        Schema::dropIfExists('contests');
    }
};
