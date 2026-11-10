<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D73: certificates get a place (contest winners) and the automatic issue starts from today: pieces
 * published from now on get theirs on their own; older pieces only when the owner or the writer asks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedTinyInteger('place')->nullable()->after('kind');   // 1, 2, 3; 9 = special mention
        });
        foreach (['certificates.auto' => '1', 'certificates.auto_since' => now()->toIso8601String()] as $k => $v) {
            if (! DB::table('settings')->where('key', $k)->exists()) {
                DB::table('settings')->insert(['key' => $k, 'value' => $v, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('certificates', fn (Blueprint $table) => $table->dropColumn('place'));
    }
};
