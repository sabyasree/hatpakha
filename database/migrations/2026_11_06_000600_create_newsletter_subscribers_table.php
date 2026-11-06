<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Old MailPoet list, kept with HOW each address joined. Nobody is emailed without the owner's approval
// (CLAUDE.md §3); unsubscribed and bounced addresses are permanently suppressed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('status', 20)->index();      // subscribed|unconfirmed|inactive|unsubscribed|bounced
            $table->string('source', 30)->nullable();   // form = signed up themselves; wordpress_user = added automatically
            $table->boolean('suppressed')->default(false)->index(); // never email (unsubscribed / bounced)
            $table->timestamp('consented_at')->nullable(); // set only by an explicit opt-in (form, or a future re-consent click)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('legacy_mailpoet_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
