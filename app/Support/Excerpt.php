<?php

namespace App\Support;

/** Safe excerpts from sanitised body HTML. */
final class Excerpt
{
    /** First N lines (poems), re-escaped as <p> lines. Output is safe to echo raw. */
    public static function lines(string $html, int $max): string
    {
        $text = preg_replace(['/<br\s*\/?>/i', '/<\/p>/i'], ["\n", "\n"], $html);
        $lines = array_values(array_filter(array_map('trim', explode("\n", html_entity_decode(strip_tags($text)))), fn ($l) => $l !== ''));

        return implode('', array_map(fn ($l) => '<p>'.e($l).'</p>', array_slice($lines, 0, $max)));
    }
}
