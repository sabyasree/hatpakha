<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D132: how phone notifications are doing (per day and kind: sent, tapped; no person, no address), and
 * announcements the owner sends from the desk to every phone (sent in small batches by the scheduler).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_stats', function (Blueprint $t) {
            $t->id();
            $t->date('day');
            $t->string('kind', 12);   // post | serial | section | daily | announce | team | test | other
            $t->unsignedInteger('sent')->default(0);
            $t->unsignedInteger('opened')->default(0);
            $t->unique(['day', 'kind']);
        });
        Schema::create('push_announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title', 80);
            $t->string('body', 200);
            $t->string('url', 300);
            $t->string('audience', 10)->default('all');   // all | daily
            $t->string('status', 10)->default('queued');  // queued | sending | sent | cancelled
            $t->unsignedBigInteger('last_id')->default(0);
            $t->unsignedInteger('recipients')->default(0);
            $t->unsignedInteger('sent')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_announcements');
        Schema::dropIfExists('push_stats');
    }
};
