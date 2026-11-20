<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D244 (owner: "how many people logged in with Google and how many the normal way; I need the count in desk"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('last_login_via', 8)->nullable()->after('last_login_at'));   // google | password
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_login_via'));
    }
};
