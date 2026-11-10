<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Owner (D95): English is an ordinary section in the menu's "আরও" group, not a separate button. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sections')->where('nav_group', 'english')->update(['nav_group' => 'other']);
        cache()->forget('sections.menu');
    }

    public function down(): void {}
};
