<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D168: human reviewers (যাচাইকারী): the admin marks a user as one and assigns pieces; they read and give feedback. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_reviewer')->default(false)->after('role');
        });

        Schema::create('piece_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('request_note')->nullable();          // the desk's note to the reviewer ("what to look for")
            $table->date('due_on')->nullable();
            $table->string('status', 12)->default('assigned');  // assigned | done | withdrawn
            $table->string('verdict', 12)->nullable();          // good | changes | not_yet
            $table->unsignedTinyInteger('rating')->nullable();  // 1-5, optional
            $table->text('liked')->nullable();                  // what worked
            $table->text('improve')->nullable();                // what would make it better
            $table->text('after_ai')->nullable();               // a note after reading the AI's report
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('desk_read_at')->nullable();      // the desk has read it (new-feedback badge)
            $table->timestamps();
            $table->unique(['post_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_reviews');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_reviewer'));
    }
};
