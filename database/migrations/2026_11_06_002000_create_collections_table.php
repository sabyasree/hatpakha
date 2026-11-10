<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The archivist's themed collections and yearly anthology lists (D55). Public on /archive/ only after approval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12)->default('theme');      // theme | anthology
            $table->string('title');
            $table->text('intro')->nullable();
            $table->json('post_ids');
            $table->string('status', 12)->default('proposed'); // proposed | approved | hidden
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
