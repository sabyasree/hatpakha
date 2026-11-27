<?php

namespace App\Support;

/**
 * D321: বাংলা শিখি, the course in database/content/learn-course.php. Units and lessons, the letter tiles of a word, what
 * has been taught by a given lesson, and the check that every word only uses what has been taught (LearnCourseTest).
 */
final class LearnCourse
{
    private static ?array $data = null;

    public static function data(): array
    {
        return self::$data ??= require database_path('content/learn-course.php');
    }

    /** @return array<string, array> slug => unit (with 'n' = its number, from 1) */
    public static function units(): array
    {
        $n = 0;

        return collect(self::data()['units'])->map(function ($u) use (&$n) {
            $u['n'] = ++$n;

            return $u;
        })->all();
    }

    public static function unit(string $slug): ?array
    {
        return self::units()[$slug] ?? null;
    }

    public static function lesson(string $slug, int $n): ?array
    {
        return self::units()[$slug]['lessons'][$n - 1] ?? null;
    }

    /** Every lesson in order, as [unit slug, lesson number, id "slug/n"]. */
    public static function all(): array
    {
        $out = [];
        foreach (self::units() as $slug => $u) {
            foreach (array_keys($u['lessons']) as $i) {
                $out[] = [$slug, $i + 1, $slug.'/'.($i + 1)];
            }
        }

        return $out;
    }

    /** @return array{0: ?array, 1: ?array} the lessons before and after, as [slug, n, id] */
    public static function around(string $slug, int $n): array
    {
        $all = self::all();
        $i = array_search($slug.'/'.$n, array_column($all, 2), true);

        return [$i !== false && $i > 0 ? $all[$i - 1] : null, $i !== false ? ($all[$i + 1] ?? null) : null];
    }

    public static function count(): int
    {
        return count(self::all());
    }

    public static function url(?string $slug = null, ?int $n = null, string $tail = ''): string
    {
        return Url::to('/janun/learn-bengali'.($slug ? '/'.$slug : '').($n ? '/'.$n : '').$tail);
    }

    /** Everything taught up to and including this lesson (letters, signs, joined letters). */
    public static function taught(string $slug, int $n): array
    {
        $set = [];
        foreach (self::all() as [$s, $i]) {
            foreach ((array) (self::lesson($s, $i)['new'] ?? []) as $x) {
                $set[self::nfc($x)] = true;
            }
            if ($s === $slug && $i === $n) {
                break;
            }
        }

        return array_keys($set);
    }

    public static function nfc(string $t): string
    {
        return \Normalizer::normalize($t, \Normalizer::FORM_C) ?: $t;
    }

    /**
     * A word as letter tiles, the way a child reads it: a letter or joined letter with its vowel sign and small signs.
     * কলম → ক, ল, ম; প্রজাপতি → প্র, জা, প, তি; সূর্য → সূ, র্য.
     */
    public static function tiles(string $word): array
    {
        $c = '[\x{0995}-\x{09B9}\x{09DC}\x{09DD}\x{09DF}]\x{09BC}?';
        preg_match_all('/(?:'.$c.'(?:\x{09CD}\x{200D}?'.$c.')*|[\x{0985}-\x{0994}])[\x{09BE}-\x{09CC}\x{09D7}]?[\x{0981}-\x{0983}]?\x{09CD}?|\x{09CE}|./u', self::nfc($word), $m);

        return array_values(array_filter($m[0], fn ($t) => trim($t) !== '' && ! preg_match('/^[\s\p{P}]+$/u', $t)));
    }

    /** @return list<string> what in this text has not been taught yet (empty = readable) */
    public static function unknown(string $text, array $taught): array
    {
        $taught = array_flip(array_map([self::class, 'nfc'], $taught));
        $bad = [];
        foreach (self::tiles($text) as $tile) {
            if (! preg_match('/^(.*?)([\x{09BE}-\x{09CC}\x{09D7}]?)([\x{0981}-\x{0983}]?)(\x{09CD}?)$/u', $tile, $m)) {
                continue;
            }
            [, $base, $sign, $mod] = $m;
            foreach (array_filter([$sign, $mod]) as $s) {
                if (! isset($taught[$s])) {
                    $bad[] = $s;
                }
            }
            if ($base === '' || ! preg_match('/[\x{0980}-\x{09FF}]/u', $base)) {
                continue;
            }
            if (! self::cluster($base, $taught)) {
                $bad[] = $base;
            }
        }

        return array_values(array_unique($bad));
    }

    /** A letter or joined letter is readable if taught as it is, or as ref + the rest, or as the rest + a phola. */
    private static function cluster(string $c, array $taught): bool
    {
        if (isset($taught[$c])) {
            return true;
        }
        if (mb_strlen($c) <= 2 && ! str_contains($c, "\u{09CD}")) {   // one letter (perhaps with its dot)
            return false;
        }
        if (str_starts_with($c, "র\u{09CD}") && isset($taught["র\u{09CD}"]) && self::cluster(mb_substr($c, 2), $taught)) {
            return true;
        }
        foreach (['র', 'য', 'ব', 'ম', 'ল'] as $p) {
            $phola = "\u{09CD}".$p;
            if (str_ends_with($c, $phola) && isset($taught[$phola]) && self::cluster(mb_substr($c, 0, -2), $taught)) {
                return true;
            }
        }

        return false;
    }

    /** Every word, sentence and story that uses something not yet taught: "unit/n: word (what)". */
    public static function problems(): array
    {
        $out = [];
        foreach (self::all() as [$slug, $n, $id]) {
            $l = self::lesson($slug, $n);
            $taught = self::taught($slug, $n);
            $texts = array_merge(array_column((array) ($l['words'] ?? []), 0), array_column((array) ($l['sentences'] ?? []), 0),
                isset($l['story']) ? [$l['story']['text'], $l['story']['q'][0], ...$l['story']['q'][1]] : []);
            foreach ($texts as $t) {
                if ($u = self::unknown((string) $t, $taught)) {
                    $out[] = $id.': '.mb_substr((string) $t, 0, 30).' ('.implode(' ', $u).')';
                }
            }
        }

        return $out;
    }
}
