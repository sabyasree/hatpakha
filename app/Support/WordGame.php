<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * D174 (owner): শব্দ-খেলা, a daily word jumble. Five words a day, the same for everyone (by the Kolkata date), their
 * letters (অক্ষর: a consonant with its conjuncts and vowel signs counts as one) shuffled; the reader taps them into
 * order with a clue. No keyboard needed. Progress and the streak live in the reader's browser.
 */
final class WordGame
{
    public const START = '2026-11-14';   // game no. 1

    public const PER_DAY = 5;

    /** @return list<array{0: string, 1: string}> */
    public static function words(): array
    {
        return require database_path('content/word-game.php');
    }

    /** One Bengali অক্ষর per item: "প্রজাপতি" → প্র, জা, প, তি. */
    public static function letters(string $word): array
    {
        $c = '[\x{0995}-\x{09B9}\x{09CE}\x{09DC}-\x{09DF}]\x{09BC}?';
        preg_match_all('/'.$c.'(?:\x{09CD}'.$c.')*[\x{09BE}-\x{09CC}\x{09D7}]*[\x{0981}-\x{0983}]*|[\x{0985}-\x{0994}][\x{0981}-\x{0983}]*|\X/u', $word, $m);

        return $m[0];
    }

    public static function number(?Carbon $day = null): int
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();

        return (int) Carbon::parse(self::START, 'Asia/Kolkata')->diffInDays($day, false) + 1;
    }

    /**
     * The day's puzzle: short words first. No word repeats until the whole list has been used.
     *
     * @return array{no: int, date: string, words: list<array{answer: string, clue: string, tiles: list<string>}>}
     */
    public static function day(?Carbon $day = null): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $no = self::number($day);
        $all = self::words();
        $count = count($all);
        $perCycle = intdiv($count, self::PER_DAY);
        $index = max(0, $no - 1);
        $cycle = intdiv($index, $perCycle);
        mt_srand(9173 + $cycle);
        $order = range(0, $count - 1);
        shuffle($order);
        $picked = array_slice($order, ($index % $perCycle) * self::PER_DAY, self::PER_DAY);
        $words = array_map(fn ($i) => $all[$i], $picked);
        usort($words, fn ($a, $b) => count(self::letters($a[0])) <=> count(self::letters($b[0])));

        mt_srand(4421 + $index);
        $out = [];
        foreach ($words as [$word, $clue]) {
            $letters = self::letters($word);
            $tiles = $letters;
            for ($try = 0; $try < 8 && ($tiles === $letters); $try++) {
                shuffle($tiles);
            }
            $out[] = ['answer' => $word, 'clue' => $clue, 'tiles' => $tiles];
        }
        mt_srand();

        return ['no' => $no, 'date' => $day->toDateString(), 'words' => $out];
    }
}
