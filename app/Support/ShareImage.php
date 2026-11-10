<?php

namespace App\Support;

use App\Models\Post;

/**
 * The picture shown when a piece is shared on Facebook, WhatsApp, X… (docs/SEO.md §2, D88). Always 1200×630,
 * small enough for WhatsApp's preview (well under 300 KB).
 *  - With a cover photo at least 1200px wide: the photo, cropped to 1200×630, under the section's overlay
 *    (logo + section name, drawn once in a browser by scripts/make-og-assets.cjs, because GD cannot shape
 *    Bengali). Made once and kept in public/media/og/.
 *  - Otherwise: the section's ready-made card (public/images/og/card-{section}.jpg).
 */
final class ShareImage
{
    public const W = 1200;

    public const H = 630;

    private const VERSION = 1;   // bump to remake every picture after the overlays change

    public static function forPost(Post $post): string
    {
        $slug = $post->section?->slug ?? 'default';
        if ($post->hasLargeImage() && ($made = self::photo($post, $slug))) {
            return asset($made);
        }

        return asset(is_file(public_path("images/og/card-{$slug}.jpg")) ? "images/og/card-{$slug}.jpg" : 'images/og/card-default.jpg');
    }

    /** The cropped photo with the overlay, made on first use. Null if the photo can't be read. */
    private static function photo(Post $post, string $slug): ?string
    {
        $src = public_path(ltrim((string) $post->featured_image_path, '/'));
        if (! is_file($src)) {
            return null;
        }
        $rel = 'media/og/'.$post->id.'-'.substr(md5($post->featured_image_path.'|'.$slug.'|'.self::VERSION), 0, 10).'.jpg';
        if (is_file(public_path($rel))) {
            return $rel;
        }
        $info = @getimagesize($src);
        $img = match ($info[2] ?? 0) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG => @imagecreatefrompng($src),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default => false,
        };
        if (! $img) {
            return null;
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        // Fill 1200×630, keeping the middle (cover-style crop, a little above centre where faces and titles sit).
        $scale = max(self::W / $w, self::H / $h);
        $cw = (int) round(self::W / $scale);
        $ch = (int) round(self::H / $scale);
        $sx = (int) round(($w - $cw) / 2);
        $sy = (int) round(($h - $ch) * 0.4);
        $out = imagecreatetruecolor(self::W, self::H);
        imagecopyresampled($out, $img, 0, 0, $sx, $sy, self::W, self::H, $cw, $ch);
        $overlay = public_path("images/og/overlay-{$slug}.png");
        $overlay = is_file($overlay) ? $overlay : public_path('images/og/overlay-default.png');
        if (is_file($overlay) && ($ov = @imagecreatefrompng($overlay))) {
            imagealphablending($out, true);
            imagecopy($out, $ov, 0, 0, 0, 0, min(self::W, imagesx($ov)), min(self::H, imagesy($ov)));
        }
        @mkdir(public_path('media/og'), 0755, true);
        imageinterlace($out, true);
        $ok = imagejpeg($out, public_path($rel), 82);

        return $ok ? $rel : null;
    }
}
