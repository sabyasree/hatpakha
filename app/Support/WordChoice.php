<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * D328 (owner: "do বানান-যাচাই, এক শব্দে প্রকাশ…"): two daily games of five questions, four choices each, one try
 * each (20 points a right answer, so a day is 100 like every game). The same for everyone by the Kolkata date; no
 * question comes back before the whole list has been used (WordGame::rota).
 *
 *  - বানান-যাচাই (`banan`): four spellings of a word; the right one is the spelling both Bangla Academy and the
 *    Paschimbanga Bangla Akademi use (words the two disagree on are not in the list). database/content/spelling.php
 *  - এক শব্দে প্রকাশ (`ekshobde`): a phrase and four words; the classic বাক্য সংকোচন. database/content/one-word.php
 */
final class WordChoice
{
    public const PER_DAY = 5;

    public const POINTS = 20;

    /** The first day of both games (game no. 1). */
    public const START = '2026-11-27';

    public const GAMES = ['banan' => ['বানান-যাচাই', 'spelling', 6151], 'ekshobde' => ['এক শব্দে প্রকাশ', 'one-word', 7177]];

    /**
     * The list with readers' reports applied (D179): an entry taken out becomes null and is skipped, so the days that
     * follow do not shift. @return list<array|null>
     */
    public static function all(string $game, ?Carbon $day = null): array
    {
        $list = WordGame::file(self::GAMES[$game][1]);
        $fixes = WordGame::fixes($game, $day);
        $key = self::answerKey($game);

        return $fixes ? array_map(fn ($row) => isset($fixes[$row[$key]]) ? null : $row, $list) : $list;
    }

    /** Where the answer sits in an entry. */
    public static function answerKey(string $game): int
    {
        return $game === 'banan' ? 0 : 1;
    }

    public static function number(string $game, ?Carbon $day = null): int
    {
        return max(1, (int) Carbon::parse(self::START, 'Asia/Kolkata')->diffInDays(($day ?? now('Asia/Kolkata'))->copy()->startOfDay(), false) + 1);
    }

    public static function open(?Carbon $day = null): bool
    {
        return ($day ?? now('Asia/Kolkata'))->toDateString() >= self::START;
    }

    /**
     * @return array{no: int, date: string, items: list<array{prompt: string, answer: string, options: list<string>, note: string}>}
     */
    public static function day(string $game, ?Carbon $day = null): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        [, , $seed] = self::GAMES[$game];
        $no = self::number($game, $day);
        $all = self::all($game, $day);
        $items = [];
        $answers = $game === 'ekshobde' ? array_values(array_unique(array_column(array_filter($all), 1))) : [];
        foreach (WordGame::rota($all, self::PER_DAY, $no - 1, $seed) as $n => $row) {
            mt_srand($seed + $no * 11 + $n);
            if ($game === 'banan') {
                [$answer, $wrong, $note] = $row;
                $options = array_merge([$answer], array_slice(array_values((array) $wrong), 0, 3));
                $prompt = '';
            } else {
                [$prompt, $answer, $note] = $row + [2 => ''];
                // three other answers of about the same length, none of them a word of this phrase
                $length = count(WordGame::letters($answer));
                $others = array_values(array_filter($answers, fn ($w) => $w !== $answer && ! str_contains($prompt, $w) && abs(count(WordGame::letters($w)) - $length) <= 1));
                if (count($others) < 3) {
                    $others = array_values(array_filter($answers, fn ($w) => $w !== $answer));
                }
                shuffle($others);
                $options = array_merge([$answer], array_slice($others, 0, 3));
            }
            shuffle($options);
            mt_srand();
            $items[] = ['prompt' => $prompt, 'answer' => $answer, 'options' => $options, 'note' => (string) $note];
        }

        return ['no' => $no, 'date' => $day->toDateString(), 'items' => $items];
    }
}
