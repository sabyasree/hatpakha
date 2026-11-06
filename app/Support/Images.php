<?php

namespace App\Support;

/**
 * Responsive images for old WordPress uploads. `php artisan hatpakha:images` writes WebP copies
 * next to each original (photo.jpg → photo.w480.webp, photo.w960.webp, photo.w1440.webp).
 * Here we only *read* what exists, so pages still work before the command has run.
 */
final class Images
{
    public const WIDTHS = [480, 960, 1440];

    /** Upload paths only; never anything outside public/wp-content/uploads. */
    public static function relative(string $src): ?string
    {
        $path = parse_url($src, PHP_URL_PATH) ?: '';
        $path = rawurldecode('/'.ltrim($path, '/'));
        if (! str_starts_with($path, '/wp-content/uploads/') || str_contains($path, '..') || ! preg_match('/\.(jpe?g|png)$/i', $path)) {
            return null;
        }

        return ltrim($path, '/');
    }

    public static function variantPath(string $relative, int $width): string
    {
        return preg_replace('/\.(jpe?g|png)$/i', '.w'.$width.'.webp', $relative);
    }

    /** @return array<int,string> width → URL of the WebP copies that exist on disk */
    public static function variants(string $src): array
    {
        $rel = self::relative($src);
        if (! $rel) {
            return [];
        }
        $out = [];
        foreach (self::WIDTHS as $w) {
            $v = self::variantPath($rel, $w);
            if (is_file(public_path($v))) {
                $out[$w] = '/'.implode('/', array_map('rawurlencode', explode('/', $v)));
            }
        }

        return $out;
    }

    /** srcset string for a <source type="image/webp">, or '' when no copies exist yet. */
    public static function srcset(?string $src): string
    {
        if (! $src) {
            return '';
        }

        return implode(', ', array_map(fn ($w, $u) => "{$u} {$w}w", array_keys($v = self::variants($src)), $v));
    }

    /**
     * Post body: every upload <img> becomes <picture> with WebP sizes (when present), lazy-loaded.
     * Rebuilt from parsed src/alt/width/height only, all re-escaped, so no attribute can be injected.
     */
    public static function renderBody(string $html): string
    {
        return preg_replace_callback('~<img\b[^>]*>~i', function ($m) {
            $tag = $m[0];
            $attr = function (string $name) use ($tag): ?string {
                return preg_match('~\s'.$name.'="([^"]*)"~i', $tag, $a) ? html_entity_decode($a[1], ENT_QUOTES | ENT_HTML5) : null;
            };
            $src = $attr('src');
            if (! $src) {
                return $tag;
            }
            $w = (int) $attr('width');
            $h = (int) $attr('height');
            $img = '<img src="'.e($src).'" alt="'.e((string) $attr('alt')).'"'
                .($w > 0 && $h > 0 ? ' width="'.$w.'" height="'.$h.'"' : '')
                .' loading="lazy" decoding="async">';
            $set = self::srcset($src);
            if ($set === '') {
                return $img;
            }
            $sizes = $w > 0 ? "(max-width: 760px) 100vw, {$w}px" : '(max-width: 760px) 100vw, 720px';

            return '<picture><source type="image/webp" srcset="'.e($set).'" sizes="'.e($sizes).'">'.$img.'</picture>';
        }, $html) ?? $html;
    }
}
