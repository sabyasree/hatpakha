<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * D178 (owner: "a badging system for different use cases… different badges and different tiers"): seven badges, each
 * in three tiers (ব্রোঞ্জ, রুপো, সোনা), earned by what a person really did on Hatpakha. Counted fresh from the
 * records each time, so nothing can be handed out or lost by mistake.
 */
final class Badges
{
    public const TIERS = [1 => 'ব্রোঞ্জ', 2 => 'রুপো', 3 => 'সোনা'];

    /** key => [name, what counts, unit, [bronze, silver, gold]] */
    public const ALL = [
        'reader' => ['পড়ুয়া', 'হাতপাখায় লেখা পড়লে', 'টি লেখা পড়েছেন', [10, 50, 200]],
        'streak' => ['রোজকার খেলোয়াড়', 'টানা দিন খেলা খেললে', 'দিন টানা খেলেছেন', [7, 30, 100]],
        'player' => ['শব্দ-শিকারি', 'খেলায় পয়েন্ট জমালে', 'পয়েন্ট জমিয়েছেন', [500, 5000, 25000]],
        'kolom' => ['পাঠকের কলম', 'পাঠকের কলমে লেখা বাছাই হলে', 'টি লেখা বাছাই হয়েছে', [1, 5, 15]],
        'writer' => ['লেখক', 'হাতপাখায় লেখা প্রকাশিত হলে', 'টি লেখা প্রকাশিত', [1, 10, 50]],
        'voice' => ['আলাপী', 'লেখায় মতামত দিলে', 'টি মতামত দিয়েছেন', [5, 25, 100]],
        'reviewer' => ['রিভিউয়ার', 'সম্পাদকের দেওয়া লেখা পড়ে মতামত দিলে', 'টি লেখা রিভিউ করেছেন', [1, 10, 30]],
    ];

    /** @return array<string, int> */
    public static function counts(User $u): array
    {
        $authorIds = \App\Models\Author::where('user_id', $u->id)->pluck('id');

        return [
            'reader' => DB::table('reading_history')->where('user_id', $u->id)->where('progress', '>=', 60)->count(),
            'streak' => GameScores::streak($u)['best'],
            'player' => (int) DB::table('game_scores')->where('user_id', $u->id)->sum('points'),
            'kolom' => DB::table('reader_entries')->join('reader_prompts', 'reader_prompts.id', '=', 'reader_entries.reader_prompt_id')
                ->where('reader_entries.user_id', $u->id)->where('reader_entries.status', 'picked')->where('reader_prompts.status', 'published')->count(),
            'writer' => $authorIds->isEmpty() ? 0 : DB::table('author_post')->join('posts', 'posts.id', '=', 'author_post.post_id')
                ->whereIn('author_post.author_id', $authorIds)->where('posts.status', 'published')->whereNull('posts.deleted_at')->distinct()->count('posts.id'),
            'voice' => DB::table('comments')->where('user_id', $u->id)->where('status', 'approved')->count(),
            'reviewer' => DB::table('piece_reviews')->where('reviewer_id', $u->id)->where('status', 'done')->count(),
        ];
    }

    /**
     * @return list<array{key: string, name: string, how: string, unit: string, count: int, tier: int, tierName: ?string, next: ?int, nextName: ?string, percent: int}>
     */
    public static function for(User $u): array
    {
        $counts = self::counts($u);
        $out = [];
        foreach (self::ALL as $key => [$name, $how, $unit, $steps]) {
            $n = $counts[$key];
            $tier = count(array_filter($steps, fn ($s) => $n >= $s));
            $next = $steps[$tier] ?? null;
            $from = $tier ? $steps[$tier - 1] : 0;
            $out[] = ['key' => $key, 'name' => $name, 'how' => $how, 'unit' => $unit, 'count' => $n, 'tier' => $tier, 'tierName' => self::TIERS[$tier] ?? null,
                'next' => $next, 'nextName' => self::TIERS[$tier + 1] ?? null, 'percent' => $next ? (int) min(100, round(100 * ($n - $from) / max(1, $next - $from))) : 100];
        }

        return $out;
    }

    /** Only the earned ones, best first (for a writer's public page). */
    public static function earned(User $u): array
    {
        $b = array_values(array_filter(self::for($u), fn ($x) => $x['tier'] > 0));
        usort($b, fn ($x, $y) => $y['tier'] <=> $x['tier']);

        return $b;
    }

    /** Small line drawings, one per badge (24×24 paths). */
    public const GLYPHS = [
        'reader' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
        'streak' => '<path d="M12 3c1 4 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-5 1-9z"/>',
        'player' => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
        'kolom' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
        'writer' => '<path d="M5 3h10l4 4v14H5z"/><path d="M9 12h6M9 16h6M9 8h3"/>',
        'voice' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.5A8 8 0 1 1 21 12z"/>',
        'reviewer' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4M8.5 11l2 2 3.5-4"/>',
    ];

    /**
     * D180 (owner: "a medal kind of badge, using the Hatpakha logo"): a ribbon and a disc carrying the logo's hand-fan,
     * coloured by tier from the logo palette only (ব্রোঞ্জ peach-deep, রুপো sky, সোনা peach; not yet earned: pale).
     * Our own fixed drawing, no user data in it.
     */
    public static function medalSvg(string $key, int $tier, int $size = 72): string
    {
        [$disc, $rim, $ray1, $ray2, $hub, $ribbonA, $ribbonB] = [
            0 => ['#FFFBF4', '#E8DCCB', '#E8DCCB', '#FFFBF4', '#E8DCCB', '#E8DCCB', '#FFFBF4'],
            1 => ['#E39A6A', '#7A1022', '#CB1B32', '#F08D90', '#FBEBD8', '#13212C', '#CB1B32'],
            2 => ['#DDF1FA', '#1A86C2', '#CB1B32', '#F08D90', '#FFFFFF', '#13212C', '#2DAAE2'],
            3 => ['#EFC08D', '#CB1B32', '#CB1B32', '#F08D90', '#FFFBF4', '#CB1B32', '#13212C'],
        ][$tier] ?? [];
        $rays = '';
        $n = 12;
        for ($k = 0; $k < $n; $k++) {
            $a1 = deg2rad(180 + 180 * $k / $n);
            $a2 = deg2rad(180 + 180 * ($k + 1) / $n);
            $p = fn ($a) => round(60 + 30 * cos($a), 2).' '.round(84 + 30 * sin($a), 2);
            $rays .= '<path d="M60 84 L'.$p($a1).' A30 30 0 0 1 '.$p($a2).' Z" fill="'.($k % 2 ? $ray2 : $ray1).'"/>';
        }
        $stars = '';
        for ($i = 0; $i < $tier; $i++) {   // one, two or three marks under the fan
            $stars .= '<circle cx="'.(60 + ($i - ($tier - 1) / 2) * 9).'" cy="103" r="2.6" fill="'.$rim.'"/>';
        }
        $ink = $tier ? '#13212C' : '#B9AE9D';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.round($size * 130 / 120).'" viewBox="0 0 120 130" aria-hidden="true" focusable="false">'
            .'<path d="M34 0h22l10 40H48z" fill="'.$ribbonA.'"/><path d="M86 0H64L54 40h18z" fill="'.$ribbonB.'"/>'
            .'<path d="M34 0h22l10 40H48z M86 0H64L54 40h18z" fill="none" stroke="'.$ink.'" stroke-width="2.5" stroke-linejoin="round"/>'
            .'<circle cx="60" cy="82" r="44" fill="'.$disc.'" stroke="'.$ink.'" stroke-width="3"/>'
            .'<circle cx="60" cy="82" r="37" fill="none" stroke="'.$rim.'" stroke-width="2.5" stroke-dasharray="1 5" stroke-linecap="round"/>'
            .$rays.'<path d="M48 84 A12 12 0 0 1 72 84 Z" fill="'.$hub.'"/><path d="M28 84h64" stroke="'.$ink.'" stroke-width="2" stroke-linecap="round"/>'
            .'<g transform="translate(53 87) scale(.58)" fill="none" stroke="'.$ink.'" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">'.self::GLYPHS[$key].'</g>'
            .$stars.'</svg>';
    }
}
