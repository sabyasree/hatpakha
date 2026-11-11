<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D127: phones and computers where a reader turned on Hatpakha's notifications. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('endpoint');
            $t->char('endpoint_hash', 64)->unique();
            $t->string('p256dh', 120);
            $t->string('auth', 40);
            $t->string('device', 40)->nullable();
            $t->unsignedTinyInteger('failures')->default(0);
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
