<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D316: baby names by letter; each name's meaning checked in two dictionaries before it is printed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('name_pages', function (Blueprint $t) {
            $t->id();
            $t->string('gender', 4);        // boy | girl
            $t->string('letter', 4);        // NamePlan::LETTERS key
            $t->string('status', 12)->default('planned');   // planned | checking | scheduled | live | held
            $t->json('names')->nullable();  // [{bn, roman, meaning_bn, meaning_en, origin, status: new|ok|no, found: [hosts]}]
            $t->text('intro')->nullable();
            $t->unsignedTinyInteger('tries')->default(0);
            $t->timestamp('publish_at')->nullable();
            $t->timestamp('checked_at')->nullable();
            $t->timestamps();
            $t->unique(['gender', 'letter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('name_pages');
    }
};
