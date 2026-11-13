<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D175: পাঠকের কলম. A weekly theme anyone can answer in a minute; the best are published on Sunday. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_prompts', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('kind', 12);                    // six | letter | memory
            $t->string('title', 200);
            $t->text('ask');
            $t->unsignedSmallInteger('max_chars')->default(300);
            $t->string('status', 12)->default('open'); // open | closed | published
            $t->timestamp('opens_at')->nullable();
            $t->timestamp('closes_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index('status');
        });
        Schema::create('reader_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reader_prompt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 80);
            $t->boolean('anonymous')->default(false);
            $t->text('body');
            $t->string('status', 12)->default('new');  // new | ok | flagged | picked | rejected
            $t->unsignedTinyInteger('ai_score')->nullable();
            $t->string('ai_note', 300)->nullable();
            $t->char('sender_hash', 64)->nullable();   // who sent it, hashed (limits repeats; never an IP address)
            $t->timestamps();
            $t->index(['reader_prompt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_entries');
        Schema::dropIfExists('reader_prompts');
    }
};
