<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D128: readers without an account can get phone notifications too: a browser may have no account and its own topics. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->change();
            $t->json('topics')->nullable()->after('auth');
        });
        // Until now every browser was linked to an account for its follows.
        \Illuminate\Support\Facades\DB::table('push_subscriptions')->whereNotNull('user_id')->update(['topics' => json_encode(['account'])]);
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $t) {
            $t->dropColumn('topics');
        });
    }
};
