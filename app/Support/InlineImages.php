<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * D93: a few early WordPress pieces (2013) had their pictures pasted into the text as base64 "data:" images.
 * The sanitiser rightly drops data: URLs, so those pieces showed as blank. This takes each picture from the old
 * WordPress database, decodes it, re-encodes it with GD as WebP (drops anything hidden in the file), saves it in
 * public/media/inline/ and puts the address back into the matching <img> (same order) of the new piece.
 * Safe to run again; skips quietly when the old database isn't connected.
 */
final class InlineImages
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const MAX_SIDE = 4000;

    /** @return array{posts:int, images:int, skipped:string|null} */
    public static function recover(): array
    {
        try {
            $legacy = DB::connection('legacy');
            $legacy->getPdo();
        } catch (\Throwable) {
            return ['posts' => 0, 'images' => 0, 'skipped' => 'old WordPress database not connected'];
        }
        $done = ['posts' => 0, 'images' => 0, 'skipped' => null];
        $rows = $legacy->table('posts')->where('post_type', 'post')->where('post_content', 'like', '%src="data:image%')->get(['ID', 'post_content']);
        foreach ($rows as $row) {
            $post = Post::where('legacy_wp_id', $row->ID)->first();
            if (! $post) {
                continue;
            }
            preg_match_all('~<img[^>]+src="data:image/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+/=\s]+)"~i', (string) $row->post_content, $m, PREG_SET_ORDER);
            $files = [];
            foreach ($m as $i => $hit) {
                $files[] = self::save((string) $hit[2], $post->id, $i);
            }
            $n = 0;
            $html = preg_replace_callback('~<img(?![^>]*\ssrc=)([^>]*)>~i', function ($img) use (&$n, $files) {
                $file = $files[$n++] ?? null;

                return $file ? '<img src="/'.$file.'"'.$img[1].'>' : $img[0];
            }, (string) $post->body_html);
            $placed = count(array_filter(array_slice($files, 0, $n)));
            if ($placed && $html !== $post->body_html) {
                DB::table('posts')->where('id', $post->id)->update(['body_html' => HtmlSanitizer::clean($html), 'updated_at' => now()]);
                $done['posts']++;
                $done['images'] += $placed;
            }
        }

        return $done;
    }

    /** Decode, check, re-encode as WebP. Returns the public path, or null if the picture is unusable. */
    private static function save(string $b64, int $postId, int $i): ?string
    {
        $rel = "media/inline/{$postId}-".($i + 1).'.webp';
        if (is_file(public_path($rel))) {
            return $rel;
        }
        $bytes = base64_decode(preg_replace('/\s+/', '', $b64), true);
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES || ! ($size = @getimagesizefromstring($bytes))
            || $size[0] < 1 || $size[1] < 1 || $size[0] > self::MAX_SIDE || $size[1] > self::MAX_SIDE) {
            Log::notice('Inline image skipped', ['post' => $postId, 'n' => $i + 1]);

            return null;
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        @mkdir(public_path('media/inline'), 0755, true);
        $ok = imagewebp($img, public_path($rel), 86);

        return $ok ? $rel : null;
    }
}
