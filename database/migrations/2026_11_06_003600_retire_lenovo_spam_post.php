<?php

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

/**
 * Owner, 2026-11-06: "remove it": an advertising post ("Best Selling Lenovo Phones…") left from the old site.
 * Retired the same way as the content audit's "remove" (D20): archived (kept, can be restored), and its old URL
 * answers 410 Gone so search engines drop it. Undo: set the status back to published and delete the redirect.
 */
return new class extends Migration
{
    private const SLUG = 'best-selling-lenovo-phones-market-right-now';

    public function up(): void
    {
        $post = Post::where('slug', self::SLUG)->first();
        if (! $post || $post->status === 'archived') {
            return;
        }
        $post->forceFill(['status' => 'archived'])->saveQuietly();
        Redirect::updateOrCreate(['from_path' => '/'.self::SLUG.'/'], ['to_path' => null, 'status_code' => 410]);
        AuditLog::record('post.retired', $post, ['reason' => 'spam (owner asked)']);
    }

    public function down(): void
    {
    }
};
