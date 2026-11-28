<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D331 (owner's list: "festival weeks: themed puzzles and a one-time keepsake badge"). From three days before a
 * festival to the day after it, one word of শব্দ-খেলা and the easy riddle come from that festival
 * (database/content/festival-games.php), the games show a small banner, and anyone who plays on those days keeps the
 * festival's keepsake for that year, once. Runs by itself: fixed-date days are written here; moving festivals take
 * their day only from what the site has already verified (the government holiday lists, D314, and the festival
 * times, D302). A festival whose day is not verified simply has no week that year.
 */
final class GameFestival
{
    /** Tests of the lists' own order switch festivals off (festival words come back every year on purpose). */
    public static bool $enabled = true;

    public const BEFORE = 3;

    public const AFTER = 1;

    /** slug => month-day of fixed-date festivals (the Gregorian day they are kept on) */
    public const FIXED = [
        'makar-sankranti' => '01-14', 'pohela-falgun' => '02-14', 'ekushe-february' => '02-21', 'poila-baishakh' => '04-14',
        'rabindra-jayanti' => '05-08', 'nazrul-jayanti' => '05-25', 'bijoy-dibos' => '12-16', 'christmas' => '12-25',
    ];

    /** slug => how the holiday lists name the moving ones */
    public const MOVING = [
        'saraswati-puja' => 'সরস্বতী', 'dol-holi' => 'দোল', 'eid-ul-fitr' => 'ফিতর', 'eid-ul-adha' => 'আ[জয]হা', 'durga-puja' => 'দুর্গা|বিজয়া দশমী',
        'lakshmi-puja' => 'লক্ষ্মী', 'kali-puja-diwali' => 'কালী|দীপাবলি',
    ];

    public static function content(): array
    {
        return WordGame::file('festival-games');
    }

    /** The festival days of a year: slug => Y-m-d (the first day, for a festival of several days). */
    public static function days(int $year): array
    {
        return Cache::remember('hp.festival.days.'.$year, 86400, function () use ($year) {
            $out = [];
            foreach (self::FIXED as $slug => $md) {
                $out[$slug] = $year.'-'.$md;
            }
            $rows = rescue(fn () => DB::table('holidays')->where('status', 'verified')->whereBetween('day', [$year.'-01-01', $year.'-12-31'])->orderBy('day')->get(['day', 'name']), collect(), false);
            foreach (self::MOVING as $slug => $pattern) {
                foreach ($rows as $r) {
                    if (preg_match('/'.$pattern.'/u', (string) $r->name)) {
                        $out[$slug] = substr((string) $r->day, 0, 10);
                        break;
                    }
                }
            }

            return $out;
        });
    }

    /**
     * The festival whose week a day falls in, with its content, or null.
     * @return ?array{slug: string, name: string, line: string, day: string, offset: int, words: list<array>, riddles: list<array>}
     */
    public static function current(?Carbon $day = null): ?array
    {
        if (! self::$enabled) {
            return null;
        }
        $day = ($day ?? now('Asia/Kolkata'))->copy()->startOfDay();
        $content = self::content();
        foreach ([$day->year, $day->year + 1, $day->year - 1] as $y) {
            foreach (self::days($y) as $slug => $date) {
                if (! isset($content[$slug])) {
                    continue;
                }
                $f = Carbon::parse($date, 'Asia/Kolkata');
                $offset = (int) $f->diffInDays($day, false);
                if ($offset >= -self::BEFORE && $offset <= self::AFTER) {
                    return ['slug' => $slug, 'day' => $date, 'offset' => $offset + self::BEFORE] + $content[$slug] + ['line' => '', 'words' => [], 'riddles' => []];
                }
            }
        }

        return null;
    }

    /** The festival word of a day ([word, clue]) or null. */
    public static function word(Carbon $day): ?array
    {
        $f = self::current($day);
        $words = $f ? array_values(array_filter($f['words'], fn ($w) => count(WordGame::letters($w[0])) >= 3)) : [];

        return $words ? $words[($f['offset'] * 3 + crc32($f['slug'])) % count($words)] : null;
    }

    /** The festival riddle of a day ([riddle, answer]) or null. */
    public static function riddle(Carbon $day): ?array
    {
        $f = self::current($day);

        return $f && $f['riddles'] ? $f['riddles'][$f['offset'] % count($f['riddles'])] : null;
    }

    /** A player who played in a festival's week keeps its keepsake for that year (once). Returns the name if new. */
    public static function keep(int $userId, string $date): ?string
    {
        $f = self::current(Carbon::parse($date, 'Asia/Kolkata'));
        if (! $f) {
            return null;
        }
        $made = DB::table('game_keepsakes')->insertOrIgnore(['user_id' => $userId, 'festival' => $f['slug'], 'year' => (int) substr($f['day'], 0, 4), 'created_at' => now()]);

        return $made ? $f['name'] : null;
    }

    /** @return list<array{name: string, year: int}> a player's keepsakes, newest first */
    public static function keepsakes(int $userId): array
    {
        $content = self::content();

        return DB::table('game_keepsakes')->where('user_id', $userId)->orderByDesc('created_at')->get(['festival', 'year'])
            ->map(fn ($k) => ['name' => (string) ($content[$k->festival]['name'] ?? $k->festival), 'year' => (int) $k->year])->all();
    }
}
