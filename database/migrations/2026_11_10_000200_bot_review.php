<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner, 2026-11-10 (D121): likely bot accounts are flagged for a person to review, never removed by the
 * system. bot_reasons = why it looks like a bot; bot_review = the owner's decision (human | bot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('bot_reasons')->nullable()->after('spam_reason');
            $table->timestamp('bot_checked_at')->nullable()->after('bot_reasons');
            $table->string('bot_review', 10)->nullable()->index()->after('bot_checked_at');
            $table->foreignId('bot_reviewed_by')->nullable()->after('bot_review')->constrained('users')->nullOnDelete();
        });
        if (! app()->runningUnitTests()) {
            $n = \App\Support\BotCheck::scan();
            echo "\n  Bot check: {$n['checked']} accounts checked, {$n['flagged']} flagged for review\n";
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bot_reviewed_by');
            $table->dropColumn(['bot_reasons', 'bot_checked_at', 'bot_review']);
        });
    }
};
