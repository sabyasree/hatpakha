<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** বর্ষসেরা (owner, 2026-11-12): a collection can be a magazine with its own designed cover, and its news is sent once. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $t) {
            $t->string('cover_path')->nullable()->after('intro');
            $t->string('proposed_cover_path')->nullable()->after('cover_path');
            $t->json('news')->nullable()->after('months');   // {"writers_told_at": …, "announced_at": …}
        });
    }

    public function down(): void
    {
        Schema::table('collections', fn (Blueprint $t) => $t->dropColumn(['cover_path', 'proposed_cover_path', 'news']));
    }
};
