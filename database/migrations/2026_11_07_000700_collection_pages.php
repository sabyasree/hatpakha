<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** D97: every collection gets its own page (/sonkolon/{slug}/), featured months; the first theme is queued. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $t) {
            $t->string('slug', 120)->nullable()->unique()->after('title');
            $t->json('months')->nullable()->after('intro');
        });
        // Collections approved before this get an address from their title.
        foreach (DB::table('collections')->whereNull('slug')->get() as $c) {
            DB::table('collections')->where('id', $c->id)->update(['slug' => \App\Models\Collection::uniqueSlug((string) $c->title, $c->id)]);
        }
        // Owner: শীতের লেখা first. The archivist prepares it on the next scheduler minute (needs AI switched on).
        if (! app()->runningUnitTests()) {
            \App\Ai\TeamJobs::queue('archivist', 'themed', 'shiter-lekha');
        }
    }

    public function down(): void
    {
        Schema::table('collections', fn (Blueprint $t) => $t->dropColumn(['slug', 'months']));
    }
};
