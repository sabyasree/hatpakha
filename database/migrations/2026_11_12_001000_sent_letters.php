<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D161: a copy of every letter the desk sends a writer, exactly as it went out (desk only; kept two years). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_letters', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40)->index();
            $table->string('to_email', 190);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 300);
            $table->longText('html');
            $table->timestamp('sent_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_letters');
    }
};
