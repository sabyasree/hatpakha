<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Editor-in-chief alerts (D49): one open alert per kind. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editor_tasks', function (Blueprint $table) {
            $table->string('key', 60)->nullable()->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('editor_tasks', function (Blueprint $table) {
            $table->dropColumn('key');
        });
    }
};
