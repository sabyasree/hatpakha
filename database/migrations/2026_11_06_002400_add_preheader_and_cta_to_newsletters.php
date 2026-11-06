<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The copywriter's letter (D61): an inbox preview line and one clear button instead of raw links. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->string('preheader', 160)->nullable()->after('subject');
            $table->string('cta_label', 60)->nullable()->after('closing');
            $table->string('cta_target', 20)->nullable()->after('cta_label');   // submit | submissions | home | archive | issue | contest
        });
    }

    public function down(): void
    {
        Schema::table('newsletters', fn (Blueprint $table) => $table->dropColumn(['preheader', 'cta_label', 'cta_target']));
    }
};
