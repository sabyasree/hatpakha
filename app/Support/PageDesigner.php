<?php

namespace App\Support;

use App\Models\Post;

/**
 * The magazine's page designer (owner, 2026-11-12: "the cover image of the piece and the images inside it, everything
 * placed properly, like a real magazine"). It reads each piece and decides how it opens and where its pictures go:
 *
 *   feature   a long prose piece with an upright picture: a full-page picture with the title on a navy panel, the text follows
 *   hero      a wide picture: a band across the top of the first page, the title under it
 *   vignette  a poem (or a short piece) with an upright picture: a smaller picture centred above the title
 *   none      no picture
 *
 * Pictures inside the text keep their place and caption, each in a <figure> with its exact shape, so the pages can be
 * laid out before the pictures arrive. Embeds (video, iframes) are left out; the cover picture is not shown twice.
 * The output is XHTML (it also goes into the EPUB).
 */
final class PageDesigner
{
    /** Pictures taller than this (height / width) count as upright. */
    private const UPRIGHT = 0.9;

    /** @return array{src: string, w: int, h: int, alt: string}|null A picture on our own site, with its size. */
    public static function picture(?string $src, ?int $w = null, ?int $h = null, string $alt = ''): ?array
    {
        if (blank($src)) {
            return null;
        }
        $url = parse_url(html_entity_decode((string) $src));
        $host = strtolower($url['host'] ?? '');
        $ours = array_filter([parse_url((string) config('app.url'), PHP_URL_HOST), 'hatpakha.com', 'www.hatpakha.com', 'new.hatpakha.com']);
        if ($host !== '' && ! in_array($host, $ours, true)) {
            return null;   // never load other sites' pictures into the book
        }
        $path = '/'.ltrim(rawurldecode($url['path'] ?? ''), '/');
        if (str_contains($path, '..') || ! preg_match('/\.(jpe?g|png|webp|gif)$/i', $path)) {
            return null;
        }
        $file = public_path(ltrim($path, '/'));
        if ((! $w || ! $h) && is_file($file)) {
            [$w, $h] = array_pad((array) (@getimagesize($file) ?: []), 2, null);
        }
        if (! is_file($file) || ! $w || ! $h) {
            return null;   // a missing file would leave a hole on the page
        }
        $variants = Images::variants($path);
        $best = $variants[960] ?? $variants[1440] ?? null;

        return ['src' => $best ?? implode('/', array_map('rawurlencode', explode('/', $path))), 'w' => (int) $w, 'h' => (int) $h, 'alt' => $alt];
    }

    /** @return array{layout: string, hero: ?array, body: string, credit: ?string} */
    public static function design(Post $post): array
    {
        $hero = self::picture($post->featured_image_path ? '/'.ltrim($post->featured_image_path, '/') : null,
            $post->featured_image_width, $post->featured_image_height, (string) ($post->featured_image_alt ?: $post->title));
        $length = mb_strlen(trim(strip_tags((string) $post->body_html)));
        $upright = $hero && $hero['h'] / $hero['w'] >= self::UPRIGHT;
        $layout = match (true) {
            ! $hero => 'none',
            $post->isPoem() => $upright ? 'vignette' : 'hero',
            $upright && $length > 1500 => 'feature',
            $upright => 'vignette',
            default => 'hero',
        };

        return [
            'layout' => $layout,
            'hero' => $hero,
            'body' => self::body((string) $post->body_html, $hero['src'] ?? null, $post->featured_image_path),
            'credit' => $hero && $post->image_is_ai ? 'ছবি: এআই-এর সাহায্যে আঁকা' : null,
        ];
    }

    /** An <img> with its exact shape: never wider than the column, never taller than $maxH of the page. */
    public static function img(array $pic, float $maxH = 0.5, string $class = ''): string
    {
        $r = round($pic['w'] / $pic['h'], 4);
        $style = 'aspect-ratio: '.$pic['w'].' / '.$pic['h'].'; width: min(100%, '.$pic['w'].'px, calc(var(--ph) * '.$maxH.' * '.$r.'));';

        return '<img src="'.e($pic['src']).'" alt="'.e($pic['alt']).'" width="'.$pic['w'].'" height="'.$pic['h'].'" decoding="async"'
            .($class ? ' class="'.$class.'"' : '').' style="'.$style.'"/>';
    }

    /** The text with its pictures placed: every picture in a figure (wide, upright or small), captions kept. */
    public static function body(string $html, ?string $skipSrc = null, ?string $skipPath = null): string
    {
        $html = preg_replace(['~<(iframe|video|audio|script|style|object|embed)\b.*?</\1>~is', '~<(iframe|embed)\b[^>]*/?>~i'], '', $html);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        @$doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }
        $skip = array_filter([self::stem($skipSrc), self::stem($skipPath)]);
        foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
            $pic = self::picture($img->getAttribute('src'), (int) $img->getAttribute('width') ?: null, (int) $img->getAttribute('height') ?: null, $img->getAttribute('alt'));
            $holder = $img;
            while ($holder->parentNode && in_array(strtolower($holder->parentNode->nodeName), ['a', 'picture'], true)) {
                $holder = $holder->parentNode;   // a picture inside a link: the link goes too (the book has no links out)
            }
            $parent = $holder->parentNode;
            if (! $pic || in_array(self::stem($pic['src']), $skip, true) || in_array(self::stem($img->getAttribute('src')), $skip, true)) {
                $parent?->removeChild($holder);
                if ($parent && in_array(strtolower($parent->nodeName), ['figure', 'p'], true) && trim(preg_replace('/\s+/u', '', $parent->textContent)) === ''
                    && $parent->getElementsByTagName('img')->length === 0) {
                    $parent->parentNode?->removeChild($parent);
                }

                continue;
            }
            $kind = $pic['w'] < 320 ? 'small' : ($pic['h'] / $pic['w'] >= self::UPRIGHT ? 'tall' : 'wide');
            $frag = $doc->createDocumentFragment();
            $frag->appendXML(self::img($pic, $kind === 'tall' ? 0.55 : 0.5));
            $new = $frag->firstChild;
            $figure = $parent && strtolower($parent->nodeName) === 'figure' ? $parent : null;
            if (! $figure && $parent && strtolower($parent->nodeName) === 'p' && trim(preg_replace('/\s+/u', '', $parent->textContent)) === '') {
                // WordPress puts a picture alone in a paragraph: that paragraph becomes the figure.
                $figure = $doc->createElement('figure');
                $parent->parentNode->replaceChild($figure, $parent);
                $figure->appendChild($new);
            } elseif (! $figure) {
                $figure = $doc->createElement('figure');
                $holder->parentNode->insertBefore($figure, $holder);
                $holder->parentNode->removeChild($holder);
                $figure->appendChild($new);
                if ($figure->parentNode && strtolower($figure->parentNode->nodeName) === 'p') {   // a figure may not sit inside a paragraph
                    $p = $figure->parentNode;
                    $p->parentNode->insertBefore($figure, $p->nextSibling);
                }
            } else {
                $figure->replaceChild($new, $holder);
            }
            $figure->setAttribute('class', 'mag-fig mag-fig-'.$kind);
        }
        $out = '';
        foreach ($body->childNodes as $n) {
            $out .= $doc->saveXML($n);
        }

        return $out;
    }

    /** The same picture in any size: "photo-1024x768.jpg", "photo.w960.webp" and "photo.jpg" are all "photo". */
    private static function stem(?string $src): ?string
    {
        if (blank($src)) {
            return null;
        }
        $name = strtolower(pathinfo(rawurldecode((string) parse_url($src, PHP_URL_PATH)), PATHINFO_FILENAME));

        return preg_replace(['/\.w\d+$/', '/-\d+x\d+$/', '/-scaled$/'], '', $name);
    }
}
