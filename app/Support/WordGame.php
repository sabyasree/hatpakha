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

    /**
     * The word list, with the fixes readers' reports led to (D179) from the day each fix starts. A corrected entry
     * keeps its place; a removed one becomes null (and is skipped), so the days' puzzles do not shift.
     *
     * @return list<array{0: string, 1: string}|null>
     */
    public static function listFor(string $game, ?Carbon $day = null): array
    {
        $day ??= now('Asia/Kolkata');
        $new = $day->toDateString() >= self::NEW_FROM;
        $base = array_merge(...array_map(fn ($f) => self::file($f), array_filter([
            'word' => ['word-game', $new ? 'word-game-2' : null],
            'hard' => ['word-game-hard', $new ? 'word-game-hard-2' : null],
            'riddle' => ['riddles', $new ? 'riddles-2' : null],
            'riddle2' => ['riddles-hard'],
        ][$game])));
        $riddle = $game === 'riddle' || $game === 'riddle2';
        $fixes = self::fixes($riddle ? 'riddle' : 'word', $day);
        if (! $fixes) {
            return $base;
        }
        $k = $riddle ? 1 : 0;   // where the answer sits in a pair
        foreach ($base as $i => $row) {
            if ($f = $fixes[$row[$k]] ?? null) {
                $base[$i] = $f['action'] === 'remove' ? null
                    : ($riddle ? [$f['new_clue'] ?: $row[0], $f['new_word'] ?: $row[1]] : [$f['new_word'] ?: $row[0], $f['new_clue'] ?: $row[1]]);
            }
        }

        return $base;
    }

    /** A content list, read once. */
    public static function file(string $name): array
    {
        static $read = [];

        return $read[$name] ??= is_file($path = database_path('content/'.$name.'.php')) ? require $path : [];
    }

    /**
     * D243 (owner: "add more words so it will not repeat for 1 year"): from NEW_FROM the lists are longer and are
     * handed out so that nothing comes back before the whole list has been used: 3 everyday words a day, 2 harder
     * ones, one ধাঁধা of each kind. What was already played before NEW_FROM goes to the end of the first round.
     */
    public const NEW_FROM = GameScores::RULES_FROM;

    /** The day the কঠিন ধাঁধা and প্রবাদ পূরণ began. */
    public const EXTRA_FROM = '2026-11-20';

    /**
     * $perDay entries for the $i-th day of a list: shuffled once per round, so none repeats within a round.
     * @param list<string> $late answers to keep for the end of the first round
     */
    public static function rota(array $list, int $perDay, int $i, int $seed, array $late = [], int $key = 0): array
    {
        $count = count($list);
        if ($count < $perDay) {
            return [];
        }
        $i = max(0, $i);
        $perRound = intdiv($count, $perDay);
        $round = intdiv($i, $perRound);
        mt_srand($seed + $round);
        $order = range(0, $count - 1);
        shuffle($order);
        mt_srand();
        if ($round === 0 && $late) {
            $late = array_flip($late);
            $isLate = fn ($k) => isset($late[$list[$k][$key] ?? '']);
            $order = array_merge(array_values(array_filter($order, fn ($k) => ! $isLate($k))), array_values(array_filter($order, $isLate)));
        }
        $out = [];
        for ($k = 0; $k < $perDay; $k++) {
            $entry = self::at($list, $order[($i % $perRound) * $perDay + $k]);
            if (($entry[0] ?? '') !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /** What was played before NEW_FROM. @return array{word: list<string>, riddle: list<string>} */
    private static function played(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('hp.game.played-before.'.self::NEW_FROM, 86400 * 30, function () {
            $out = ['word' => [], 'riddle' => []];
            $end = Carbon::parse(self::NEW_FROM, 'Asia/Kolkata');
            for ($d = Carbon::parse(self::START, 'Asia/Kolkata'); $d->lt($end); $d = $d->copy()->addDay()) {
                $out['word'] = array_merge($out['word'], array_column(self::day($d)['words'], 'answer'));
                $out['riddle'][] = self::riddle($d)['words'][0]['answer'];
            }

            return $out;
        });
    }

    private static function daysSince(string $from, Carbon $day): int
    {
        return (int) Carbon::parse($from, 'Asia/Kolkata')->diffInDays($day->copy()->startOfDay(), false);
    }

    /** @return array<string, array{action: string, new_word: ?string, new_clue: ?string}> fixes in force on that day */
    public static function fixes(string $game, ?Carbon $day = null): array
    {
        $all = \Illuminate\Support\Facades\Cache::remember('hp.wordfixes', 600, fn () => \Illuminate\Support\Facades\DB::table('word_fixes')->get()
            ->map(fn ($f) => ['game' => $f->game, 'word' => $f->word, 'action' => $f->action, 'new_word' => $f->new_word, 'new_clue' => $f->new_clue, 'from' => (string) $f->from_day])->all());
        $on = ($day ?? now('Asia/Kolkata'))->toDateString();
        $out = [];
        foreach ($all as $f) {
            if ($f['game'] === $game && substr($f['from'], 0, 10) <= $on) {
                $out[$f['word']] = $f;
            }
        }

        return $out;
    }

    /** The entry at a place in the list; a removed one is replaced by the next one still standing, half the list away. */
    private static function at(array $list, int $i): array
    {
        $n = count($list);
        for ($step = 0; $step < $n && $list[$i] === null; $step++) {
            $i = ($i + intdiv($n, 2) + $step) % $n;
        }

        return $list[$i] ?? ['', ''];
    }

    public const HARD_FROM = '2026-11-16';

    public const HARD_PER_DAY = 2;

    /**
     * D199 (owner: "can we add words which are a little tough", then "yes, two words is good"): the two harder words of
     * the day, shorter first; none before HARD_FROM. Their own list (database/content/word-game-hard.php, none of
     * them in the everyday list), shuffled once per round, so none comes back until all have been used (383 words:
     * 191 days). Readers' fixes (D179) apply to them like to any word.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function hardWords(?Carbon $day = null): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $i = (int) Carbon::parse(self::HARD_FROM, 'Asia/Kolkata')->diffInDays($day, false);
        if ($i < 0) {
            return [];
        }
        $list = require database_path('content/word-game-hard.php');
        $fixes = self::fixes('word', $day);
        foreach ($list as $k => $row) {
            if ($f = $fixes[$row[0]] ?? null) {
                $list[$k] = $f['action'] === 'remove' ? null : [$f['new_word'] ?: $row[0], $f['new_clue'] ?: $row[1]];
            }
        }
        $count = count($list);
        $perRound = intdiv($count, self::HARD_PER_DAY);
        mt_srand(6607 + intdiv($i, $perRound));
        $order = range(0, $count - 1);
        shuffle($order);
        mt_srand();
        $out = [];
        for ($k = 0; $k < self::HARD_PER_DAY; $k++) {
            $word = self::at($list, $order[($i % $perRound) * self::HARD_PER_DAY + $k]);
            if ($word[0] !== '') {
                $out[] = $word;
            }
        }
        usort($out, fn ($a, $b) => count(self::letters($a[0])) <=> count(self::letters($b[0])));

        return $out;
    }

    /** @return list<array{0: string, 1: string}> the words in play today */
    public static function words(?Carbon $day = null): array
    {
        return array_values(array_filter(self::listFor('word', $day)));
    }

    /** One Bengali অক্ষর per item: "প্রজাপতি" → প্র, জা, প, তি. */
    public static function letters(string $word): array
    {
        $c = '[\x{0995}-\x{09B9}\x{09CE}\x{09DC}-\x{09DF}]\x{09BC}?';
        preg_match_all('/'.$c.'(?:\x{09CD}'.$c.')*[\x{09BE}-\x{09CC}\x{09D7}]*[\x{0981}-\x{0983}]*|[\x{0985}-\x{0994}][\x{0981}-\x{0983}]*|\X/u', $word, $m);

        return $m[0];
    }

    /** @return list<array{0: string, 1: string}> [riddle, answer] */
    public static function riddles(?Carbon $day = null): array
    {
        return array_values(array_filter(self::listFor('riddle', $day)));
    }

    /**
     * D177: the day's ধাঁধা. The answer's letters plus a few from other answers, shuffled; tapped into order.
     *
     * @return array{no: int, date: string, words: list<array{answer: string, clue: string, tiles: list<string>}>}
     */
    public static function riddle(?Carbon $day = null, string $level = 'easy'): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $index = max(0, self::number($day) - 1);
        $all = self::riddles($day);
        if ($level === 'hard') {   // D243: its own list, more spare letters
            [$riddle, $answer] = self::rota(self::listFor('riddle2', $day), 1, self::daysSince(self::EXTRA_FROM, $day), 8831, [], 1)[0] ?? ['', ''];
            if ($answer === '') {
                return ['no' => max(1, $index + 1), 'date' => $day->toDateString(), 'level' => 'hard', 'words' => []];
            }
        } elseif ($day->toDateString() >= self::NEW_FROM) {
            [$riddle, $answer] = self::rota(self::listFor('riddle', $day), 1, self::daysSince(self::NEW_FROM, $day), 7723, self::played()['riddle'], 1)[0];
        } else {
            $list = self::listFor('riddle', $day);
            $count = count($list);
            mt_srand(7723 + intdiv($index, $count));
            $order = range(0, $count - 1);
            shuffle($order);
            [$riddle, $answer] = self::at($list, $order[$index % $count]);
        }
        mt_srand(($level === 'hard' ? 6143 : 5519) + $index);
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
        $tiles = array_merge($letters, array_slice($pool, 0, $level === 'hard' ? max(5, 10 - count($letters)) : max(3, 7 - count($letters))));
        shuffle($tiles);
        mt_srand();

        return ['no' => max(1, $index + 1), 'date' => $day->toDateString(), 'level' => $level, 'words' => [['answer' => $answer, 'clue' => $riddle, 'tiles' => $tiles]]];
    }

    /**
     * D177: the day's শব্দজব্দ, a small crossword built from the word list: words cross where they share an অক্ষর.
     * Standard rules: no two words side by side, every word reads cleanly.
     *
     * @return array{no: int, date: string, size: int, words: list<array{answer: string, clue: string, row: int, col: int, down: bool, letters: list<string>}>, bank: list<string>}
     */
    /**
     * D201 (owner: "for শব্দজব্দ can we do 3 level. easy, medium and expert. And change the words accordingly").
     * key => [name, url part, game key in game_scores, most letters in a word, words wanted, board, full points, cost of one shown square]
     */
    public const LEVELS = [
        'easy' => ['সহজ', 'sohoj', 'cross1', 3, 5, 9, 60, 5],
        'medium' => ['মাঝারি', '', 'cross', 5, 6, 11, 100, 10],
        'hard' => ['কঠিন', 'kothin', 'cross3', 7, 9, 12, 150, 25],
    ];

    public static function crosswordUrl(string $level = 'medium'): string
    {
        return Url::to('/khela/shobdojobdo'.(self::LEVELS[$level][1] !== '' ? '/'.self::LEVELS[$level][1] : ''));
    }

    public static function crossword(?Carbon $day = null, string $level = 'medium'): array
    {
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $index = max(0, self::number($day) - 1);
        [, , , $maxLetters, $want, $board] = self::LEVELS[$level];

        return \Illuminate\Support\Facades\Cache::remember('hp.cross.'.$level.'.'.$day->toDateString().'.'.count(self::words($day)).'.'.count(self::fixes('word', $day)), 90000, function () use ($day, $index, $level, $maxLetters, $want, $board) {
            $today = array_column(self::day($day)['words'], 'answer');
            // মাঝারি keeps the seed it always had, so today's puzzle does not change under a player
            $seed = 3301 + ['easy' => 500000, 'medium' => 0, 'hard' => 900000][$level];
            $everyday = array_values(array_filter(self::words($day), fn ($w) => ! in_array($w[0], $today, true)));
            $tough = [];
            if ($level === 'hard') {   // the harder list first, everyday words only to join them up
                $fixes = self::fixes('word', $day);
                foreach (require database_path('content/word-game-hard.php') as $row) {
                    $f = $fixes[$row[0]] ?? null;
                    if (($f['action'] ?? '') === 'remove' || in_array($row[0], $today, true)) {
                        continue;
                    }
                    $tough[] = $f ? [$f['new_word'] ?: $row[0], $f['new_clue'] ?: $row[1]] : $row;
                }
            }
            $best = null;
            for ($try = 0; $try < 12; $try++) {
                mt_srand($seed + $index * 13 + $try);
                $list = $everyday;
                shuffle($list);
                $list = array_slice($list, 0, 260);
                if ($tough) {
                    shuffle($tough);
                    $list = array_merge(array_slice($tough, 0, 200), array_slice($list, 0, 60));
                }
                $made = self::buildGrid($list, $want, $maxLetters, $board);
                if (! $best || count($made['words']) > count($best['words'])) {
                    $best = $made;
                }
                if (count($best['words']) >= $want) {
                    break;
                }
            }
            mt_srand($seed + $index);
            $bank = array_values(array_unique(array_merge(...array_column($best['words'], 'letters'))));
            if ($level === 'hard') {   // a few letters that belong nowhere, so the bank alone does not give the answers away
                $spare = array_values(array_diff(array_unique(array_merge(...array_map(fn ($w) => self::letters($w[0]), array_slice($everyday, 0, 40)))), $bank));
                shuffle($spare);
                $bank = array_merge($bank, array_slice($spare, 0, 6));
            }
            shuffle($bank);
            mt_srand();

            return ['no' => max(1, $index + 1), 'date' => $day->toDateString(), 'level' => $level, 'size' => $best['size'], 'words' => $best['words'], 'bank' => $bank];
        });
    }

    /** Greedy crossword on a 9×9 board of অক্ষর, then cropped. @param list<array{0: string, 1: string}> $list */
    private static function buildGrid(array $list, int $want = 6, int $maxLetters = 5, int $n = 11): array
    {
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
            if (count($letters) > $maxLetters || count($letters) < 2) {
                continue;
            }
            if (! $placed) {
                $put($w, $letters, intdiv($n, 2), intdiv($n - count($letters), 2), false);

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
        $index = max(0, $no - 1);
        $byLength = fn ($a, $b) => count(self::letters($a[0])) <=> count(self::letters($b[0]));
        if ($day->toDateString() >= self::NEW_FROM) {   // D243: three everyday words and two harder ones, none again for a year
            $i = self::daysSince(self::NEW_FROM, $day);
            $late = self::played()['word'];
            $words = self::rota(self::listFor('word', $day), self::PER_DAY - self::HARD_PER_DAY, $i, 9173, $late);
            $hard = self::rota(self::listFor('hard', $day), self::HARD_PER_DAY, $i, 6607, $late);
            usort($words, $byLength);
            usort($hard, $byLength);
            $words = array_merge($words, $hard);
        } else {
            $all = self::listFor('word', $day);
            $count = count($all);
            $perCycle = intdiv($count, self::PER_DAY);
            $cycle = intdiv($index, $perCycle);
            mt_srand(9173 + $cycle);
            $order = range(0, $count - 1);
            shuffle($order);
            $picked = array_slice($order, ($index % $perCycle) * self::PER_DAY, self::PER_DAY);
            $words = array_map(fn ($i) => self::at($all, $i), $picked);
            usort($words, $byLength);
            // D199: from HARD_FROM the day's last two words are harder ones, from their own list and in their own order
            // (so the everyday words' order, and "no word again within six months", stay exactly as they were).
            foreach (self::hardWords($day) as $k => $hard) {
                $words[self::PER_DAY - self::HARD_PER_DAY + $k] = $hard;
            }
        }

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
