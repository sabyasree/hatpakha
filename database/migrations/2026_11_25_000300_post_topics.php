<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D293: the topic pages a piece is on (slugs from App\Support\Topics), and who placed it (ai | desk). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $t) {
            $t->json('topics')->nullable();
            $t->string('topics_by', 8)->nullable();   // null: not sorted yet; [] with 'ai': read, no topic fits
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn(['topics', 'topics_by']));
    }
};
