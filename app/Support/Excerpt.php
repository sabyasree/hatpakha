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

    /**
     * The opening of a prose piece for a teaser (owner, 2026-11-09: a long essay as the editor's pick filled the home
     * page): about $chars characters, cut after the last full stop (। ? !) inside them, with "…". Safe to echo raw.
     */
    public static function prose(string $html, int $chars = 320): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace(['/<br\s*\/?>/i', '/<\/p>/i'], [' ', ' '], $html)))));
        if (mb_strlen($text) <= $chars) {
            return '<p>'.e($text).'</p>';
        }
        $cut = mb_substr($text, 0, $chars);
        $end = max((int) mb_strrpos($cut, '।'), (int) mb_strrpos($cut, '?'), (int) mb_strrpos($cut, '!'));
        $cut = $end > $chars * 0.4 ? mb_substr($cut, 0, $end + 1) : rtrim(mb_substr($cut, 0, (int) (mb_strrpos($cut, ' ') ?: $chars)), ' ,;:');

        return '<p>'.e($cut).' …</p>';
    }
}
