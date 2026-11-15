<?php

use App\Models\AuditLog;
use App\Models\Post;
use App\Support\Handover;
use Illuminate\Database\Migrations\Migration;

/**
 * D210: between D208 going live and this release, a fix that only adds to the words (a missing "।") could be put into
 * the same piece again on every run. The fault is fixed; nothing is changed here. Pieces that had fixes put in more
 * than once are listed for the owner to look at.
 */
return new class extends Migration
{
    public function up(): void
    {
        rescue(function () {
            $ids = AuditLog::where('action', 'handover.fixes')->where('subject_type', (new Post)->getMorphClass())
                ->selectRaw('subject_id, count(*) as n')->groupBy('subject_id')->having('n', '>', 1)->pluck('subject_id');
            $titles = Post::whereKey($ids)->pluck('title')->all();
            if ($titles) {
                Handover::flag('repeat', 'এই লেখাগুলোয় একই সংশোধন একাধিকবার বসে থাকতে পারে (যেমন পরপর দুটো দাঁড়ি); খুলে একবার দেখে নিন, তারপর এই সতর্কতা সরান', $titles);
            }
        }, null, false);
    }

    public function down(): void
    {
    }
};
