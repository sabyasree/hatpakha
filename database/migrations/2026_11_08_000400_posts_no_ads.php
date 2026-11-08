<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D111: the editor can keep ads off a piece (grief, a death, a tragedy). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->boolean('no_ads')->default(false)->after('noindex'));
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('no_ads'));
    }
};
