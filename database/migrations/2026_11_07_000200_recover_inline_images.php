<?php

use Illuminate\Database\Migrations\Migration;

/** D93: put back the pictures of the early pieces that were pasted as base64 (needs the old database; skips without it). */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $r = \App\Support\InlineImages::recover();
        echo '  Inline pictures: '.($r['skipped'] ?? "{$r['images']} pictures back in {$r['posts']} pieces").PHP_EOL;
    }

    public function down(): void {}
};
