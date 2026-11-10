<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Least privilege at import: the WordPress role is remembered, never granted (docs/SECURITY.md §3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('legacy_role', 30)->nullable()->after('role');
            $table->string('spam_reason', 120)->nullable()->index()->after('legacy_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['legacy_role', 'spam_reason']);
        });
    }
};
