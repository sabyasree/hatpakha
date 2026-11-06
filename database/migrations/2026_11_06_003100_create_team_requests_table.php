<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Work the owner gives the AI team by hand (D72): run a job now, redo it, or a request in their own words. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_requests', function (Blueprint $table) {
            $table->id();
            $table->string('agent', 32)->nullable();          // null = the editor-in-chief decides who
            $table->string('job', 40)->nullable();            // a known job, or null for a request in words
            $table->text('instruction')->nullable();          // the owner's words
            $table->boolean('redo')->default(false);          // do it again even if already done this period
            $table->string('status', 16)->default('queued');  // queued | running | done | failed
            $table->text('result')->nullable();               // what was done, or the written answer
            $table->string('link', 500)->nullable();          // where to see it in /desk
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_requests');
    }
};
