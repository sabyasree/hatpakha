<?php

namespace App\Support;

/**
 * Render-time only: a paragraph that contains nothing but a YouTube link becomes a privacy-friendly
 * player (youtube-nocookie, allowed by the CSP frame-src). The stored HTML stays sanitised and
 * iframe-free; the video ID is strictly validated, so no attacker input reaches the iframe.
 */
class Embeds
{
    public static function render(string $html): string
    {
        return preg_replace_callback(
            '~<p>\s*<a href="https?://(?:www\.|m\.)?(?:youtube\.com/watch\?(?:[^"]*?&amp;)?v(?:=|&#61;)|youtu\.be/|youtube\.com/shorts/)([A-Za-z0-9_-]{11})[^"]*"[^>]*>[^<]*</a>\s*</p>~',
            fn ($m) => '<figure class="video"><iframe src="https://www.youtube-nocookie.com/embed/'.$m[1].'" title="YouTube" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></figure>',
            $html
        );
    }
}
