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

    /**
     * UX review: random wrong choices made এক শব্দে প্রকাশ too easy (জলপ্রপাত beside প্রকাশক). They now come from the
     * same part of the list (people with people, sounds with sounds), of about the same length, and never one of the
     * pairs where both could fit. Call inside a seeded mt_srand. @return list<string> three wrong choices
     */
    public static function decoys(string $answer, string $prompt, array $answers): array
    {
        $section = self::sections();
        $clash = self::CLASH[$answer] ?? [];
        foreach (self::CLASH as $a => $list) {
            if (in_array($answer, $list, true)) {
                $clash[] = $a;
            }
        }
        $length = count(WordGame::letters($answer));
        $fits = fn ($w) => $w !== $answer && ! in_array($w, $clash, true) && ! str_contains($prompt, $w);
        $same = array_values(array_filter($answers, fn ($w) => $fits($w) && ($section[$w] ?? 0) === ($section[$answer] ?? -1) && abs(count(WordGame::letters($w)) - $length) <= 2));
        shuffle($same);
        $out = array_slice($same, 0, 3);
        if (count($out) < 3) {
            $rest = array_values(array_filter($answers, fn ($w) => $fits($w) && ! in_array($w, $out, true) && abs(count(WordGame::letters($w)) - $length) <= 1));
            shuffle($rest);
            $out = array_merge($out, array_slice($rest, 0, 3 - count($out)));
        }

        return $out;
    }

    /** Pairs that must never be the right answer and a wrong choice together: both could be said to fit. */
    public const CLASH = [
        'পাঠক' => ['শিক্ষার্থী', 'সহপাঠী', 'ছাত্র'], 'ক্ষণস্থায়ী' => ['নশ্বর', 'অস্থায়ী'], 'অমর' => ['অক্ষয়', 'নশ্বর', 'অবিনশ্বর'],
        'প্রবাসী' => ['নির্বাসিত'], 'প্রত্যাগত' => ['নবাগত'],
    ];

    /** Which part of one-word.php (its "//" headings) each answer is in. @return array<string, int> */
    public static function sections(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $n = 0;
        foreach (file(database_path('content/one-word.php')) ?: [] as $line) {
            if (preg_match('~^\s*//~u', $line)) {
                $n++;
            } elseif (preg_match("~^\s*\['[^']*',\s*'([^']+)'~u", $line, $m)) {
                $map[$m[1]] = $n;
            }
        }

        return $map;
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
                $options = array_merge([$answer], self::decoys($answer, $prompt, $answers));
            }
            shuffle($options);
            mt_srand();
            $items[] = ['prompt' => $prompt, 'answer' => $answer, 'options' => $options, 'note' => (string) $note];
        }

        return ['no' => $no, 'date' => $day->toDateString(), 'items' => $items];
    }
}
