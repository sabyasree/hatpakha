<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * D328 (owner: "do … অক্ষরজাল"): a 4×4 grid of অক্ষর tiles, the same for everyone by the Kolkata date. The player
 * joins touching tiles (sideways, up-down or across) into words; a tile is used once per word. A word counts only if
 * it is in our lists (the শব্দ-খেলা words and database/content/akshorjal-words.php), so the grid's every word is
 * known in advance: the day's grid is made by planting a few real words along random paths, filling the rest with
 * common অক্ষর, and keeping the try that holds the most words. 3 minutes. 2 tiles = 5 points, 3 = 10, 4 or more
 * = 20; a day is at most 100 like every game.
 */
final class Akshorjal
{
    public const SIZE = 4;

    public const SECONDS = 180;

    public const START = WordChoice::START;

    /** A grid is kept only if it holds at least this many words (most days hold far more). */
    public const MIN_WORDS = 12;

    public static function points(int $tiles): int
    {
        return $tiles >= 4 ? 20 : ($tiles === 3 ? 10 : ($tiles === 2 ? 5 : 0));
    }

    public static function number(?Carbon $day = null): int
    {
        return WordChoice::number('jal', $day);
    }

    /** Every word the grid may hold, as its অক্ষর. @return array<string, list<string>> word => tiles */
    public static function dictionary(): array
    {
        static $dict = null;
        if ($dict !== null) {
            return $dict;
        }
        $words = WordGame::file('akshorjal-words');
        foreach (['word-game', 'word-game-2', 'word-game-hard', 'word-game-hard-2'] as $f) {
            $words = array_merge($words, array_column(WordGame::file($f), 0));
        }
        $dict = [];
        foreach ($words as $w) {
            $w = \Normalizer::normalize(trim((string) $w), \Normalizer::FORM_C) ?: '';
            $t = WordGame::letters($w);
            if ($w !== '' && count($t) >= 2 && count($t) <= self::SIZE * self::SIZE && ! preg_match('/[^\x{0980}-\x{09FF}]/u', $w)) {
                $dict[$w] = $t;
            }
        }

        return $dict;
    }

    /** @return array{no: int, date: string, grid: list<string>, words: list<string>} */
    public static function day(?Carbon $day = null): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $date = $day->toDateString();
        $made = Cache::remember('hp.akshorjal.'.$date.'.'.count(self::dictionary()), 86400 * 3, fn () => self::make(crc32('akshorjal'.$date)));

        return ['no' => self::number($day), 'date' => $date] + $made;
    }

    /** @return array{grid: list<string>, words: list<string>} */
    public static function make(int $seed): array
    {
        $dict = self::dictionary();
        $trie = self::trie($dict);
        // how often each tile starts or sits in a word: the filling follows it, so the grid reads like Bengali
        $weights = [];
        foreach ($dict as $tiles) {
            foreach ($tiles as $t) {
                $weights[$t] = ($weights[$t] ?? 0) + 1;
            }
        }
        arsort($weights);
        $common = array_slice($weights, 0, 60, true);
        $plantable = array_values(array_filter($dict, fn ($t) => count($t) >= 3 && count($t) <= 5));
        mt_srand($seed);
        $best = null;
        for ($try = 0; $try < 120; $try++) {
            $grid = array_fill(0, self::SIZE * self::SIZE, null);
            for ($w = 0, $planted = 0; $w < 40 && $planted < 5; $w++) {
                $planted += self::plant($grid, $plantable[mt_rand(0, count($plantable) - 1)]) ? 1 : 0;
            }
            foreach ($grid as $i => $t) {
                $grid[$i] = $t ?? self::pick($common);
            }
            $found = self::solve($grid, $trie);
            $score = count($found) + 2 * count(array_filter($found, fn ($w) => count($dict[$w]) >= 4));
            // the same tile three times or more makes a dull grid
            $score -= 5 * count(array_filter(array_count_values($grid), fn ($n) => $n > 2));
            if (! $best || $score > $best[0]) {
                $best = [$score, $grid, $found];
            }
        }
        mt_srand();
        [, $grid, $found] = $best;
        usort($found, fn ($a, $b) => [count($dict[$b]), $a] <=> [count($dict[$a]), $b]);

        return ['grid' => array_values($grid), 'words' => $found];
    }

    /** Lay a word's tiles along a random path of touching cells, through empty cells or ones holding the same tile. */
    private static function plant(array &$grid, array $tiles): bool
    {
        $n = self::SIZE * self::SIZE;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $path = [];
            $cell = mt_rand(0, $n - 1);
            foreach ($tiles as $k => $t) {
                if ($k > 0) {
                    $next = array_values(array_filter(self::neighbours($cell), fn ($c) => ! in_array($c, $path, true) && ($grid[$c] === null || $grid[$c] === $t)));
                    if (! $next) {
                        continue 2;
                    }
                    $cell = $next[mt_rand(0, count($next) - 1)];
                } elseif ($grid[$cell] !== null && $grid[$cell] !== $t) {
                    continue 2;
                }
                $path[] = $cell;
            }
            foreach ($path as $k => $c) {
                $grid[$c] = $tiles[$k];
            }

            return true;
        }

        return false;
    }

    private static function pick(array $weights): string
    {
        $r = mt_rand(1, (int) array_sum($weights));
        foreach ($weights as $t => $w) {
            if (($r -= $w) <= 0) {
                return (string) $t;
            }
        }

        return (string) array_key_first($weights);
    }

    /** @return list<int> */
    public static function neighbours(int $i): array
    {
        $s = self::SIZE;
        [$r, $c] = [intdiv($i, $s), $i % $s];
        $out = [];
        for ($dr = -1; $dr <= 1; $dr++) {
            for ($dc = -1; $dc <= 1; $dc++) {
                if (($dr || $dc) && $r + $dr >= 0 && $r + $dr < $s && $c + $dc >= 0 && $c + $dc < $s) {
                    $out[] = ($r + $dr) * $s + $c + $dc;
                }
            }
        }

        return $out;
    }

    private static function trie(array $dict): array
    {
        $root = [];
        foreach ($dict as $word => $tiles) {
            $node = &$root;
            foreach ($tiles as $t) {
                $node[$t] ??= [];
                $node = &$node[$t];
            }
            $node['$'] = $word;
            unset($node);
        }

        return $root;
    }

    /** Every word of the list the grid holds. @return list<string> */
    public static function solve(array $grid, ?array $trie = null): array
    {
        $trie ??= self::trie(self::dictionary());
        $found = [];
        $walk = function (int $cell, array $node, array $used) use (&$walk, &$found, $grid) {
            $node = $node[$grid[$cell]] ?? null;
            if ($node === null) {
                return;
            }
            $used[$cell] = true;
            if (isset($node['$']) && count($used) >= 2) {
                $found[$node['$']] = true;
            }
            foreach (self::neighbours($cell) as $c) {
                if (! isset($used[$c])) {
                    $walk($c, $node, $used);
                }
            }
        };
        foreach (array_keys($grid) as $cell) {
            $walk($cell, $trie, []);
        }

        return array_keys($found);
    }

    /** The points for the words a player sent: only words the day's grid holds count, each once; at most 100. */
    public static function score(array $words, string $date): int
    {
        $day = self::day(Carbon::parse($date, 'Asia/Kolkata'));
        $dict = self::dictionary();
        $ok = array_intersect(array_unique(array_map(fn ($w) => \Normalizer::normalize((string) $w, \Normalizer::FORM_C) ?: '', $words)), $day['words']);

        return min(100, array_sum(array_map(fn ($w) => self::points(count($dict[$w])), $ok)));
    }
}
