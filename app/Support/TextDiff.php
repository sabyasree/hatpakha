<?php

namespace App\Support;

/**
 * D171: what changed between two versions of a piece, for the desk. Paragraphs are matched first, then words
 * inside a changed paragraph, so long stories stay fast. Output is escaped HTML with <ins>/<del> marks.
 */
final class TextDiff
{
    /** @return list<string> */
    public static function paragraphs(string $html): array
    {
        $text = html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</(p|h[1-6]|li|blockquote|div)>~i'], ["\n", "\n\n"], $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(array_map(fn ($p) => trim(preg_replace('/[ \t]+/u', ' ', $p)), preg_split('/\n\s*\n|\n/u', $text) ?: []), fn ($p) => $p !== ''));
    }

    /**
     * @return array{html: string, added: int, removed: int, same: bool}
     */
    public static function compare(string $oldHtml, string $newHtml, string $oldTitle = '', string $newTitle = ''): array
    {
        $a = self::paragraphs($oldHtml);
        $b = self::paragraphs($newHtml);
        $added = $removed = 0;
        $out = [];
        if ($oldTitle !== $newTitle) {
            [$h, $ad, $rm] = self::words($oldTitle, $newTitle);
            $out[] = '<p style="font-weight:700">'.e(__('শিরোনাম:')).' '.$h.'</p>';
            $added += $ad;
            $removed += $rm;
        }
        $ops = self::lcs($a, $b);
        // Pair a removed paragraph with the added one that follows it: that is an edit, shown word by word.
        for ($i = 0; $i < count($ops); $i++) {
            [$op, $text] = $ops[$i];
            if ($op === '-' && isset($ops[$i + 1]) && $ops[$i + 1][0] === '+') {
                [$h, $ad, $rm] = self::words($text, $ops[$i + 1][1]);
                $out[] = '<p>'.$h.'</p>';
                $added += $ad;
                $removed += $rm;
                $i++;
            } elseif ($op === '-') {
                $out[] = '<p><del style="background:#FBD9DD;text-decoration:line-through">'.e($text).'</del></p>';
                $removed += self::count($text);
            } elseif ($op === '+') {
                $out[] = '<p><ins style="background:#D7F0DC;text-decoration:none">'.e($text).'</ins></p>';
                $added += self::count($text);
            } else {
                $out[] = '<p style="opacity:.55">'.e($text).'</p>';
            }
        }

        return ['html' => implode("\n", $out), 'added' => $added, 'removed' => $removed, 'same' => $added === 0 && $removed === 0];
    }

    /** @return array{0: string, 1: int, 2: int} */
    private static function words(string $old, string $new): array
    {
        $a = preg_split('/(\s+)/u', $old, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/(\s+)/u', $new, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($a) * count($b) > 400000) {   // a huge rewrite of one paragraph: show it whole
            return ['<del style="background:#FBD9DD;text-decoration:line-through">'.e($old).'</del> <ins style="background:#D7F0DC;text-decoration:none">'.e($new).'</ins>', self::count($new), self::count($old)];
        }
        $html = '';
        $ad = $rm = 0;
        foreach (self::lcs($a, $b) as [$op, $t]) {
            $isWord = trim($t) !== '';
            $html .= match ($op) {
                '-' => '<del style="background:#FBD9DD;text-decoration:line-through">'.e($t).'</del>',
                '+' => '<ins style="background:#D7F0DC;text-decoration:none">'.e($t).'</ins>',
                default => e($t),
            };
            $rm += $op === '-' && $isWord ? 1 : 0;
            $ad += $op === '+' && $isWord ? 1 : 0;
        }

        return [$html, $ad, $rm];
    }

    /**
     * Longest-common-subsequence diff of two lists. @return list<array{0: string, 1: string}> ['=', '-', '+']
     */
    private static function lcs(array $a, array $b): array
    {
        // Trim the common start and end first (most edits are small).
        $pre = [];
        while ($a && $b && $a[0] === $b[0]) {
            $pre[] = ['=', array_shift($a)];
            array_shift($b);
        }
        $post = [];
        while ($a && $b && end($a) === end($b)) {
            array_unshift($post, ['=', array_pop($a)]);
            array_pop($b);
        }
        $n = count($a);
        $m = count($b);
        $len = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $len[$i][$j] = $a[$i] === $b[$j] ? $len[$i + 1][$j + 1] + 1 : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }
        $mid = [];
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $mid[] = ['=', $a[$i++]];
                $j++;
            } elseif ($len[$i + 1][$j] >= $len[$i][$j + 1]) {
                $mid[] = ['-', $a[$i++]];
            } else {
                $mid[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $mid[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $mid[] = ['+', $b[$j++]];
        }

        return array_merge($pre, $mid, $post);
    }

    private static function count(string $t): int
    {
        return count(preg_split('/\s+/u', trim($t), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
