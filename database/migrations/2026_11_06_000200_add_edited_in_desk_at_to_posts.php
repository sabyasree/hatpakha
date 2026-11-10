<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Posts changed by an editor in /desk are never overwritten by a later WordPress re-import (Phase 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('edited_in_desk_at')->nullable()->after('legacy_wp_id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('edited_in_desk_at');
        });
    }
};
