<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * D243 (owner: "প্রবাদ পূরণ: add now and include in points"): the fourth daily game. Four well-known Bengali proverbs a
 * day, each with one word missing; the player taps the right word out of four. The same for everyone (by the Kolkata
 * date); no proverb comes back until the whole list (database/content/proverbs.php) has been used.
 */
final class Proverbs
{
    public const PER_DAY = 4;

    /** @return list<array{0: string, 1: string, 2: string}> [proverb, the word left out, meaning] */
    public static function all(): array
    {
        return WordGame::file('proverbs');
    }

    /**
     * @return array{no: int, date: string, items: list<array{before: string, after: string, answer: string, options: list<string>, meaning: string, full: string}>}
     */
    public static function day(?Carbon $day = null): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $i = max(0, (int) Carbon::parse(WordGame::EXTRA_FROM, 'Asia/Kolkata')->diffInDays($day, false));
        $all = self::all();
        $words = array_values(array_unique(array_column($all, 1)));
        $items = [];
        foreach (WordGame::rota($all, self::PER_DAY, $i, 4409) as $n => [$full, $answer, $meaning]) {
            $parts = preg_split('/\s+/u', trim($full));
            $at = array_search($answer, $parts, true);
            // three wrong words of about the same length, none of them in this proverb
            $length = count(WordGame::letters($answer));
            $others = array_values(array_filter($words, fn ($w) => $w !== $answer && ! in_array($w, $parts, true) && abs(count(WordGame::letters($w)) - $length) <= 1));
            mt_srand(2903 + $i * 7 + $n);
            shuffle($others);
            $options = array_merge([$answer], array_slice($others, 0, 3));
            shuffle($options);
            mt_srand();
            $items[] = ['before' => implode(' ', array_slice($parts, 0, (int) $at)), 'after' => implode(' ', array_slice($parts, (int) $at + 1)),
                'answer' => $answer, 'options' => $options, 'meaning' => $meaning, 'full' => $full];
        }

        return ['no' => $i + 1, 'date' => $day->toDateString(), 'items' => $items];
    }
}
