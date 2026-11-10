<?php

namespace App\Support;

/**
 * Bengali → Latin for readable URLs of NEW posts (D14: old posts keep their exact WordPress slugs).
 * Simple phonetic rules (inherent "o" dropped at word end), good enough for "বৃষ্টির দিন" → "brishtir-din".
 */
final class Transliterate
{
    private const VOWELS = ['অ' => 'o', 'আ' => 'a', 'ই' => 'i', 'ঈ' => 'i', 'উ' => 'u', 'ঊ' => 'u', 'ঋ' => 'ri', 'এ' => 'e', 'ঐ' => 'oi', 'ও' => 'o', 'ঔ' => 'ou'];

    private const SIGNS = ['া' => 'a', 'ি' => 'i', 'ী' => 'i', 'ু' => 'u', 'ূ' => 'u', 'ৃ' => 'ri', 'ে' => 'e', 'ৈ' => 'oi', 'ো' => 'o', 'ৌ' => 'ou'];

    private const CONSONANTS = [
        'ক' => 'k', 'খ' => 'kh', 'গ' => 'g', 'ঘ' => 'gh', 'ঙ' => 'ng', 'চ' => 'ch', 'ছ' => 'chh', 'জ' => 'j', 'ঝ' => 'jh', 'ঞ' => 'n',
        'ট' => 't', 'ঠ' => 'th', 'ড' => 'd', 'ঢ' => 'dh', 'ণ' => 'n', 'ত' => 't', 'থ' => 'th', 'দ' => 'd', 'ধ' => 'dh', 'ন' => 'n',
        'প' => 'p', 'ফ' => 'ph', 'ব' => 'b', 'ভ' => 'bh', 'ম' => 'm', 'য' => 'j', 'র' => 'r', 'ল' => 'l', 'শ' => 'sh', 'ষ' => 'sh',
        'স' => 's', 'হ' => 'h', "\u{09DC}" => 'r', "\u{09DD}" => 'rh', "\u{09DF}" => 'y', 'ৎ' => 't',
    ];

    private const OTHERS = ['ং' => 'ng', 'ঃ' => 'h', 'ঁ' => 'n', '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];

    public static function slug(string $text, int $max = 80): string
    {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        // NFC decomposes ড়/ঢ়/য় into base + nukta (U+09BC); fold them back to one letter each.
        $text = str_replace(["\u{09A1}\u{09BC}", "\u{09A2}\u{09BC}", "\u{09AF}\u{09BC}"], ["\u{09DC}", "\u{09DD}", "\u{09DF}"], $text);
        $c = mb_str_split($text);
        $n = count($c);
        $isCons = fn ($x) => isset(self::CONSONANTS[$x]);
        $out = '';
        for ($i = 0; $i < $n; $i++) {
            $ch = $c[$i];
            if (! $isCons($ch)) {
                $out .= self::VOWELS[$ch] ?? self::SIGNS[$ch] ?? self::OTHERS[$ch] ?? (in_array($ch, ["\u{09CD}", "\u{09BC}", "\u{200C}", "\u{200D}"], true) ? '' : $ch);

                continue;
            }
            // ক্ষ → kkh
            if ($ch === 'ক' && ($c[$i + 1] ?? '') === "\u{09CD}" && ($c[$i + 2] ?? '') === 'ষ') {
                $out .= 'kkh';
                $i += 2;
            } else {
                $out .= self::CONSONANTS[$ch];
            }
            $afterConjunct = $i >= 2 && ($c[$i - 1] ?? '') === "\u{09CD}";
            $next = $c[$i + 1] ?? '';
            if (isset(self::SIGNS[$next])) {
                $out .= self::SIGNS[$next];
                $i++;
            } elseif ($next === "\u{09CD}") {
                $follow = $c[$i + 2] ?? '';
                if ($follow === 'ব') {        // ব-ফলা: স্ব → sw
                    $out .= 'w';
                    $i += 2;
                    $after = $c[$i + 1] ?? '';
                    if (isset(self::SIGNS[$after])) {
                        $out .= self::SIGNS[$after];
                        $i++;
                    } elseif ($isCons($after)) {
                        $out .= 'a';
                    }
                } elseif ($follow === 'য') {  // য-ফলা: ্য → y
                    $out .= 'y';
                    $i += 2;
                    $after = $c[$i + 1] ?? '';
                    if (isset(self::SIGNS[$after])) {
                        $out .= self::SIGNS[$after];
                        $i++;
                    }
                } else {
                    $i++; // plain conjunct: next consonant follows without a vowel
                }
            } elseif ($isCons($next) || $next === 'ং') {
                $out .= 'o';                    // inherent vowel inside a word
            } elseif ($afterConjunct) {
                $out .= 'o';                    // word-final conjunct is pronounced: স্বপ্ন → swapno
            }
        }

        return rtrim(mb_substr(\Illuminate\Support\Str::slug($out), 0, $max), '-');
    }
}
