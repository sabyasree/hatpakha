<?php

use App\Ai\SerialWriter;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

/** Owner's screenshot (2026-11-09): episode titles carried the serial name and number twice. Repair them once. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Post::with('serial')->whereNotNull('serial_id')->whereNotNull('episode_no')->get() as $p) {
            if (! $p->serial) {
                continue;
            }
            $fixed = SerialWriter::fullTitle($p->serial, (int) $p->episode_no, (string) $p->title);
            if ($fixed !== $p->title) {
                $p->forceFill(['title' => $fixed])->saveQuietly();   // the address (slug) stays the same
                echo '  fixed: '.$fixed.PHP_EOL;
            }
        }
    }

    public function down(): void {}
};
