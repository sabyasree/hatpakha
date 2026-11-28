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
    /**
     * D331 (owner's list: "longer বানান-যাচাই and এক শব্দে প্রকাশ lists"): from this Sunday the second files join the
     * lists (a new round starts; nothing changes in the middle of a day).
     */
    public const LIST2_FROM = '2026-12-06';

    public static function all(string $game, ?Carbon $day = null): array
    {
        $name = self::GAMES[$game][1];
        $list = WordGame::file($name);
        if (($day ?? now('Asia/Kolkata'))->toDateString() >= self::LIST2_FROM) {
            $list = array_merge($list, WordGame::file($name.'-2'));
        }
        $fixes = WordGame::fixes($game, $day);
        $key = self::answerKey($game);

        return $fixes ? array_map(fn ($row) => isset($fixes[$row[$key]]) ? null : $row, $list) : $list;
    }

    /** The answers asked before LIST2_FROM (kept for the end of the first round of the longer list). @return list<string> */
    private static function askedBefore(string $game): array
    {
        return \Illuminate\Support\Facades\Cache::remember('hp.choice.before.'.$game.'.'.self::LIST2_FROM, 86400 * 30, function () use ($game) {
            $out = [];
            for ($d = Carbon::parse(self::START, 'Asia/Kolkata'); $d->toDateString() < self::LIST2_FROM; $d = $d->copy()->addDay()) {
                $out = array_merge($out, array_column(self::day($game, $d)['items'], 'answer'));
            }

            return $out;
        });
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
        $tiles = WordGame::letters($answer);
        $length = count($tiles);
        $fits = fn ($w) => $w !== $answer && ! in_array($w, $clash, true) && ! str_contains($prompt, $w);
        // D330 (game designers: "too easy, the wrong ones are not believable"): the wrong choices look like the answer:
        // the same first letter or the same ending (-হীন, -বান, অ-…), the same part of the list, about as long
        $score = function ($w) use ($tiles, $length, $section, $answer) {
            $t = WordGame::letters($w);
            return (($t[0] ?? '') === $tiles[0] ? 3 : 0) + (end($t) === end($tiles) ? 3 : 0)
                + (($section[$w] ?? 0) === ($section[$answer] ?? -1) ? 2 : 0) + (abs(count($t) - $length) <= 1 ? 1 : 0);
        };
        $cands = array_values(array_filter($answers, $fits));
        shuffle($cands);   // ties broken by the day's seed
        usort($cands, fn ($a, $b) => $score($b) <=> $score($a));
        $top = array_slice($cands, 0, 6);
        shuffle($top);
        $out = array_slice($top, 0, 3);

        return $out;
    }

    /** Pairs that must never be the right answer and a wrong choice together: both could be said to fit. */
    public const CLASH = [
        'পাঠক' => ['শিক্ষার্থী', 'সহপাঠী', 'ছাত্র'], 'ক্ষণস্থায়ী' => ['নশ্বর', 'অস্থায়ী'], 'অমর' => ['অক্ষয়', 'নশ্বর', 'অবিনশ্বর'],
        'প্রবাসী' => ['নির্বাসিত'], 'প্রত্যাগত' => ['নবাগত'], 'অতল' => ['অসীম', 'অগাধ', 'অতলস্পর্শী'], 'অসীম' => ['অনন্ত', 'অপার'],
        'অজেয়' => ['অদম্য', 'অপরাজেয়'], 'অসংখ্য' => ['অগণিত', 'অগণ্য'],
    ];

    /** Which part of one-word.php and one-word-2.php (their "//" headings) each answer is in. @return array<string, string> */
    public static function sections(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        // grouped by the heading's words, so both files' groups of the same name are one group (D331)
        foreach (['one-word', 'one-word-2'] as $f) {
            $n = $f.'-0';
            foreach ((is_file($p = database_path('content/'.$f.'.php')) ? file($p) : []) ?: [] as $line) {
                if (preg_match('~^\s*//\s*(.+)$~u', $line, $h)) {
                    $n = trim($h[1]);
                } elseif (preg_match("~^\s*\['[^']*',\s*'([^']+)'~u", $line, $m)) {
                    $map[$m[1]] = $n;
                }
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
        // D331: from LIST2_FROM a new round over the longer list; what was asked before goes to its end
        $longer = $day->toDateString() >= self::LIST2_FROM;
        $i = $longer ? (int) Carbon::parse(self::LIST2_FROM, 'Asia/Kolkata')->diffInDays($day, false) : $no - 1;
        $late = $longer ? self::askedBefore($game) : [];
        foreach (WordGame::rota($all, self::PER_DAY, $i, $seed, $late, self::answerKey($game)) as $n => $row) {
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
