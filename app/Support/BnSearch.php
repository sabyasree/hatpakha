<?php

namespace App\Support;

use App\Models\Post;

/**
 * Search that forgives Bengali spelling (owner, 2026-11-11): রবীন্দ্রনাথ = রবিন্দ্রনাথ, বিষণ্ণ = বিসন্ন, পড়া = পরা…
 * Text and query are both folded to one "key": long/short vowels, ণ/ন, ষ/স/শ, ড়/ঢ়/র, য়/য, ঙ/ং, ৎ/ত,
 * chandrabindu, visarga, joiners and punctuation are made the same; Bengali digits become 0-9; Latin is lower-cased.
 */
final class BnSearch
{
    private const MAP = [
        "\u{09AF}\u{09BC}" => 'য', "\u{09DF}" => 'য', "\u{09A1}\u{09BC}" => 'র', "\u{09DC}" => 'র', "\u{09A2}\u{09BC}" => 'র', "\u{09DD}" => 'র',
        'ী' => 'ি', 'ূ' => 'ু', 'ঈ' => 'ই', 'ঊ' => 'উ',
        'ণ' => 'ন', 'ষ' => 'শ', 'স' => 'শ', 'ঙ' => 'ং', 'ৎ' => 'ত', 'ঢ' => 'ড', 'ঘ' => 'গ', 'ধ' => 'দ', 'ভ' => 'ব', 'ঝ' => 'জ', 'ঠ' => 'ট', 'থ' => 'ত', 'ছ' => 'চ', 'ফ' => 'প', 'খ' => 'ক',
        'ঁ' => '', 'ঃ' => '', '়' => '', "\u{200C}" => '', "\u{200D}" => '',
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    public static function key(string $text): string
    {
        $text = class_exists(\Normalizer::class) ? (string) \Normalizer::normalize($text, \Normalizer::FORM_D) : $text;
        $text = mb_strtolower(strtr($text, self::MAP));
        $text = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Plain text of a piece (lines kept), for snippets. */
    public static function plain(string $html): string
    {
        return trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</p>~i'], ["\n", "\n"], $html)), ENT_QUOTES | ENT_HTML5));
    }

    /** Store the search keys of a piece: title + writers + tags, and its text. */
    public static function index(Post $post): void
    {
        // Fresh queries, never cached on the model (a new piece gets its writers after its first save).
        $authors = $post->authors()->get(['authors.display_name', 'authors.display_name_bn']);
        $head = $post->title.' '.$authors->map(fn ($a) => $a->display_name.' '.$a->display_name_bn)->implode(' ').' '.$post->tags()->pluck('name')->implode(' ').' '.$post->excerpt;
        Post::whereKey($post->id)->update(['search_title' => mb_substr(self::key($head), 0, 1000), 'search_body' => self::key(self::plain((string) $post->body_html))]);
    }

    /** The line of the piece where the words appear (a poem's line), for the result. */
    public static function snippet(Post $post, string $q): ?string
    {
        $words = array_filter(explode(' ', self::key($q)));
        if (! $words) {
            return null;
        }
        foreach (preg_split('/\n+/u', self::plain((string) $post->body_html)) as $line) {
            $k = self::key($line);
            if ($k !== '' && collect($words)->every(fn ($w) => str_contains($k, $w))) {
                // A long paragraph: just the sentence that holds the words (UX review: one short line, not four).
                $line = trim($line);
                if (mb_strlen($line) > 110) {
                    foreach (preg_split('/(?<=[।?!৷])\s+/u', $line) as $sentence) {
                        $sk = self::key($sentence);
                        if (collect($words)->every(fn ($w) => str_contains($sk, $w))) {
                            $line = trim($sentence);
                            break;
                        }
                    }
                }

                return mb_strlen($line) > 110 ? mb_substr($line, 0, 107).'…' : $line;
            }
        }

        return null;
    }
}
