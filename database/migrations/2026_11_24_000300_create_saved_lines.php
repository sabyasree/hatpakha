<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** C6 (research 2026-11): lines a signed-in reader keeps from a piece, shown only to them in their library. Plain text. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->string('text', 500);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_lines');
    }
};
