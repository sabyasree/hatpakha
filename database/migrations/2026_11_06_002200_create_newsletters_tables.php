<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Newsletters (D58): letters to readers who opted in and/or to registered writers. Drafted by the
 * AI team or the owner; sent only when the owner clicks "পাঠান", in small batches by the scheduler.
 * One delivery row per recipient, so nobody gets the same letter twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 10)->default('readers');   // readers | writers | both
            $table->string('subject', 120);
            $table->text('intro')->nullable();
            $table->json('items')->nullable();                     // [{id,title,author,url,line}]
            $table->text('closing')->nullable();
            $table->string('source', 16)->default('owner');       // owner | ai_weekly | ai_writers | ai_topic
            $table->string('status', 10)->default('draft');       // draft | queued | sending | sent | cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
        Schema::create('newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('kind', 10);                            // subscriber | writer
            $table->unsignedBigInteger('recipient_id');
            $table->timestamp('sent_at')->nullable();
            $table->string('error', 200)->nullable();
            $table->unique(['newsletter_id', 'email']);
            $table->index(['newsletter_id', 'sent_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->boolean('mail_opt_out')->default(false)->after('desk_locale'));
        Schema::table('newsletter_subscribers', fn (Blueprint $table) => $table->timestamp('confirm_sent_at')->nullable()->after('consented_at'));
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', fn (Blueprint $table) => $table->dropColumn('confirm_sent_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('mail_opt_out'));
        Schema::dropIfExists('newsletter_deliveries');
        Schema::dropIfExists('newsletters');
    }
};
