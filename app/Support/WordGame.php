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

    /** @return list<array{0: string, 1: string}> [riddle, answer] */
    public static function riddles(): array
    {
        return require database_path('content/riddles.php');
    }

    /**
     * D177: the day's ধাঁধা. The answer's letters plus a few from other answers, shuffled; tapped into order.
     *
     * @return array{no: int, date: string, words: list<array{answer: string, clue: string, tiles: list<string>}>}
     */
    public static function riddle(?Carbon $day = null): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $index = max(0, self::number($day) - 1);
        $all = self::riddles();
        $count = count($all);
        mt_srand(7723 + intdiv($index, $count));
        $order = range(0, $count - 1);
        shuffle($order);
        [$riddle, $answer] = $all[$order[$index % $count]];
        mt_srand(5519 + $index);
        $letters = self::letters($answer);
        $pool = [];
        foreach ($all as [, $other]) {
            foreach (self::letters($other) as $l) {
                if (! in_array($l, $letters, true)) {
                    $pool[$l] = true;
                }
            }
        }
        $pool = array_keys($pool);
        shuffle($pool);
        $tiles = array_merge($letters, array_slice($pool, 0, max(3, 7 - count($letters))));
        shuffle($tiles);
        mt_srand();

        return ['no' => max(1, $index + 1), 'date' => $day->toDateString(), 'words' => [['answer' => $answer, 'clue' => $riddle, 'tiles' => $tiles]]];
    }

    /**
     * D177: the day's শব্দজব্দ, a small crossword built from the word list: words cross where they share an অক্ষর.
     * Standard rules: no two words side by side, every word reads cleanly.
     *
     * @return array{no: int, date: string, size: int, words: list<array{answer: string, clue: string, row: int, col: int, down: bool, letters: list<string>}>, bank: list<string>}
     */
    public static function crossword(?Carbon $day = null): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $index = max(0, self::number($day) - 1);

        return \Illuminate\Support\Facades\Cache::remember('hp.cross.'.$day->toDateString().'.'.count(self::words()), 90000, function () use ($day, $index) {
            $today = array_column(self::day($day)['words'], 'answer');
            $best = null;
            for ($try = 0; $try < 12; $try++) {
                mt_srand(3301 + $index * 13 + $try);
                $list = array_values(array_filter(self::words(), fn ($w) => ! in_array($w[0], $today, true)));
                shuffle($list);
                $made = self::buildGrid(array_slice($list, 0, 260));
                if (! $best || count($made['words']) > count($best['words'])) {
                    $best = $made;
                }
                if (count($best['words']) >= 6) {
                    break;
                }
            }
            mt_srand(3301 + $index);
            $bank = array_values(array_unique(array_merge(...array_column($best['words'], 'letters'))));
            shuffle($bank);
            mt_srand();

            return ['no' => max(1, $index + 1), 'date' => $day->toDateString(), 'size' => $best['size'], 'words' => $best['words'], 'bank' => $bank];
        });
    }

    /** Greedy crossword on a 9×9 board of অক্ষর, then cropped. @param list<array{0: string, 1: string}> $list */
    private static function buildGrid(array $list, int $want = 6): array
    {
        $n = 11;
        $grid = [];
        $placed = [];
        $fits = function (array $letters, int $r, int $c, bool $down) use (&$grid, $n): int {
            $len = count($letters);
            $er = $r + ($down ? $len - 1 : 0);
            $ec = $c + ($down ? 0 : $len - 1);
            if ($r < 0 || $c < 0 || $er >= $n || $ec >= $n) {
                return -1;
            }
            // the cell before the start and after the end must be empty
            [$br, $bc] = $down ? [$r - 1, $c] : [$r, $c - 1];
            [$ar, $ac] = $down ? [$er + 1, $c] : [$r, $ec + 1];
            if (isset($grid[$br][$bc]) || isset($grid[$ar][$ac])) {
                return -1;
            }
            $cross = 0;
            foreach ($letters as $i => $l) {
                $y = $r + ($down ? $i : 0);
                $x = $c + ($down ? 0 : $i);
                if (isset($grid[$y][$x])) {
                    if ($grid[$y][$x]['l'] !== $l || $grid[$y][$x]['down'] === $down) {
                        return -1;
                    }
                    $cross++;
                } else {   // a new cell may not touch another word sideways
                    [$s1, $s2] = $down ? [[$y, $x - 1], [$y, $x + 1]] : [[$y - 1, $x], [$y + 1, $x]];
                    if (isset($grid[$s1[0]][$s1[1]]) || isset($grid[$s2[0]][$s2[1]])) {
                        return -1;
                    }
                }
            }

            return $cross;
        };
        $put = function (array $w, array $letters, int $r, int $c, bool $down) use (&$grid, &$placed) {
            foreach ($letters as $i => $l) {
                $y = $r + ($down ? $i : 0);
                $x = $c + ($down ? 0 : $i);
                $grid[$y][$x] = ['l' => $l, 'down' => isset($grid[$y][$x]) ? null : $down];
            }
            $placed[] = ['answer' => $w[0], 'clue' => $w[1], 'row' => $r, 'col' => $c, 'down' => $down, 'letters' => $letters];
        };
        foreach ($list as $w) {
            $letters = self::letters($w[0]);
            if (count($letters) > 5) {
                continue;
            }
            if (! $placed) {
                $put($w, $letters, 5, intdiv($n - count($letters), 2), false);

                continue;
            }
            $done = false;
            foreach ($placed as $p) {
                foreach ($p['letters'] as $pi => $pl) {
                    foreach ($letters as $li => $l) {
                        if ($l !== $pl || $done) {
                            continue;
                        }
                        $down = ! $p['down'];
                        $r = $p['row'] + ($p['down'] ? $pi : 0) - ($down ? $li : 0);
                        $c = $p['col'] + ($p['down'] ? 0 : $pi) - ($down ? 0 : $li);
                        if ($fits($letters, $r, $c, $down) >= 1) {
                            $put($w, $letters, $r, $c, $down);
                            $done = true;
                        }
                    }
                }
                if ($done) {
                    break;
                }
            }
            if (count($placed) >= $want) {
                break;
            }
        }
        // crop to the used square
        $rows = array_merge(...array_map(fn ($p) => [$p['row'], $p['row'] + ($p['down'] ? count($p['letters']) - 1 : 0)], $placed));
        $cols = array_merge(...array_map(fn ($p) => [$p['col'], $p['col'] + ($p['down'] ? 0 : count($p['letters']) - 1)], $placed));
        [$r0, $c0] = [min($rows), min($cols)];
        $size = max(max($rows) - $r0, max($cols) - $c0) + 1;
        foreach ($placed as &$p) {
            $p['row'] -= $r0;
            $p['col'] -= $c0;
        }
        usort($placed, fn ($a, $b) => [$a['row'], $a['col']] <=> [$b['row'], $b['col']]);

        return ['size' => $size, 'words' => $placed];
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
