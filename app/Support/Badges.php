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
        // D201: tiers ask for harder levels, see crossTier()
        'cross' => ['শব্দজব্দ', 'শব্দজব্দ মেলালে (রুপো: মাঝারি বা কঠিন, সোনা: কঠিন)', 'টি ছক মিলিয়েছেন', [10, 25, 50]],
        'kolom' => ['পাঠকের কলম', 'পাঠকের কলমে লেখা বাছাই হলে', 'টি লেখা বাছাই হয়েছে', [1, 5, 15]],
        'writer' => ['লেখক', 'হাতপাখায় লেখা প্রকাশিত হলে', 'টি লেখা প্রকাশিত', [1, 10, 50]],
        'voice' => ['আলাপী', 'লেখায় মতামত দিলে', 'টি মতামত দিয়েছেন', [5, 25, 100]],
        'friend' => ['বন্ধু', 'বন্ধুকে হাতপাখায় আনলে', 'জন বন্ধু এসেছেন', [1, 5, 15]],   // D184
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
            'cross' => self::crossTier($u)[1],
            'kolom' => DB::table('reader_entries')->join('reader_prompts', 'reader_prompts.id', '=', 'reader_entries.reader_prompt_id')
                ->where('reader_entries.user_id', $u->id)->where('reader_entries.status', 'picked')->where('reader_prompts.status', 'published')->count(),
            'writer' => $authorIds->isEmpty() ? 0 : DB::table('author_post')->join('posts', 'posts.id', '=', 'author_post.post_id')
                ->whereIn('author_post.author_id', $authorIds)->where('posts.status', 'published')->whereNull('posts.deleted_at')->distinct()->count('posts.id'),
            'voice' => DB::table('comments')->where('user_id', $u->id)->where('status', 'approved')->count(),
            'friend' => Invites::count($u),
            'reviewer' => DB::table('piece_reviews')->where('reviewer_id', $u->id)->where('status', 'done')->count(),
        ];
    }

    /**
     * D201: the শব্দজব্দ medal. ব্রোঞ্জ: 10 puzzles solved at any level; রুপো: 25 at মাঝারি or কঠিন; সোনা: 50 at কঠিন.
     * @return array{0: int, 1: int} the tier, and the count that matters for the next one
     */
    public static function crossTier(User $u): array
    {
        $by = DB::table('game_scores')->where('user_id', $u->id)->whereIn('game', ['cross1', 'cross', 'cross3'])->where('points', '>', 0)
            ->groupBy('game')->selectRaw('game, count(*) as n')->pluck('n', 'game');
        $hard = (int) ($by['cross3'] ?? 0);
        $mid = $hard + (int) ($by['cross'] ?? 0);
        $any = $mid + (int) ($by['cross1'] ?? 0);
        $tier = $any >= 10 ? ($mid >= 25 ? ($hard >= 50 ? 3 : 2) : 1) : 0;

        return [$tier, [$any, $mid, $hard, $hard][$tier]];
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
            $tier = $key === 'cross' ? self::crossTier($u)[0] : count(array_filter($steps, fn ($s) => $n >= $s));
            $next = $steps[$tier] ?? null;
            $from = $tier && $key !== 'cross' ? $steps[$tier - 1] : 0;   // শব্দজব্দ counts a different set for each tier
            $out[] = ['key' => $key, 'name' => $name, 'how' => $how, 'unit' => $unit, 'count' => $n, 'tier' => $tier, 'tierName' => self::TIERS[$tier] ?? null,
                'next' => $next, 'nextName' => self::TIERS[$tier + 1] ?? null, 'percent' => $next ? (int) min(100, round(100 * max(0, $n - $from) / max(1, $next - $from))) : 100];
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

    /**
     * D181: one solid emblem per badge on a 24×24 grid, saying what the medal is for. {ink} is the emblem colour,
     * {hub} the colour behind it (for cut-out details). Recognisable at 30px; nothing thinner than 2 units.
     */
    public const EMBLEMS = [
        // an open book with a bookmark
        'reader' => '<path d="M1.5 5.5Q7 3 11.2 5.8V20Q7 17.6 1.5 19.4Z M22.5 5.5Q17 3 12.8 5.8V20Q17 17.6 22.5 19.4Z" fill="{ink}"/><path d="M10.4 19.5h3.2V24L12 22.4 10.4 24Z" fill="{ink}"/>',
        // a calendar leaf with a tick: played day after day
        'streak' => '<rect x="2.5" y="5" width="19" height="17" rx="2.5" fill="{ink}"/><rect x="6.6" y="1.5" width="2.8" height="6" rx="1.4" fill="{ink}" stroke="{hub}" stroke-width="1"/><rect x="14.6" y="1.5" width="2.8" height="6" rx="1.4" fill="{ink}" stroke="{hub}" stroke-width="1"/><path d="M7 14.5l3.4 3.4L17 11" fill="none" stroke="{hub}" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>',
        // a letter tile with ক and a star on its corner
        'player' => '<rect x="2" y="5.5" width="17" height="17" rx="3.5" fill="{ink}"/><text x="10.5" y="18.6" font-family="\'Anek Bangla\',\'Noto Sans Bengali\',sans-serif" font-weight="800" font-size="12.5" text-anchor="middle" fill="{hub}">ক</text><path d="{star}" fill="{ink}" stroke="{hub}" stroke-width="1.2" stroke-linejoin="round" transform="translate(19 5.5)"/>',
        // a little crossword: an L of squares, one filled with a tick
        'cross' => '<rect x="2" y="2" width="9" height="9" rx="2" fill="{ink}"/><rect x="13" y="2" width="9" height="9" rx="2" fill="none" stroke="{ink}" stroke-width="2.4"/><rect x="2" y="13" width="9" height="9" rx="2" fill="none" stroke="{ink}" stroke-width="2.4"/><rect x="13" y="13" width="9" height="9" rx="2" fill="{ink}"/><path d="M4.4 6.6l1.8 1.8 3-3.4" fill="none" stroke="{hub}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        // a sealed envelope with a star: your entry was picked
        'kolom' => '<rect x="1.5" y="5" width="21" height="15.5" rx="2.5" fill="{ink}"/><path d="M3 7l9 5.6L21 7" fill="none" stroke="{hub}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="{star}" fill="{hub}" transform="translate(12 16) scale(.78)"/>',
        // a fountain-pen nib
        'writer' => '<path d="M12 23.5L4.5 12 8 2.5h8L19.5 12Z" fill="{ink}" stroke="{ink}" stroke-width="1" stroke-linejoin="round"/><path d="M12 23V14" stroke="{hub}" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="11.6" r="2" fill="{hub}"/>',
        // two speech bubbles
        'voice' => '<rect x="8.5" y="2" width="14" height="10.5" rx="3.2" fill="none" stroke="{ink}" stroke-width="2.4"/><path d="M4.8 8.5h9.4a3.3 3.3 0 0 1 3.3 3.3v4.4a3.3 3.3 0 0 1-3.3 3.3H9.5L5 23.5v-4H4.8a3.3 3.3 0 0 1-3.3-3.3v-4.4a3.3 3.3 0 0 1 3.3-3.3Z" fill="{ink}" stroke="{hub}" stroke-width="1.4" stroke-linejoin="round"/>',
        // a pair of round spectacles: the editor's eye
        // two people side by side: you brought a friend
        'friend' => '<circle cx="8" cy="7.5" r="4.2" fill="{ink}"/><path d="M0.8 21.5c0-4.8 3.1-8 7.2-8s7.2 3.2 7.2 8z" fill="{ink}"/><circle cx="17.6" cy="9.6" r="3.4" fill="{ink}" stroke="{hub}" stroke-width="1.2"/><path d="M12.6 21.5c.2-3.9 2-6.4 5-6.4 3.2 0 5.4 2.5 5.6 6.4z" fill="{ink}" stroke="{hub}" stroke-width="1.2" stroke-linejoin="round"/>',
        'reviewer' => '<circle cx="6.6" cy="13.5" r="4.6" fill="none" stroke="{ink}" stroke-width="2.8"/><circle cx="17.4" cy="13.5" r="4.6" fill="none" stroke="{ink}" stroke-width="2.8"/><path d="M11 12.6q1-1.4 2 0M2.2 12L1.2 7.5M21.8 12l1-4.5" fill="none" stroke="{ink}" stroke-width="2.6" stroke-linecap="round"/>',
    ];

    /** How large the name sits on the banner, so the long ones fit on one line. [বাংলা, English] */
    private const NAME_SIZE = ['friend' => [15, 15], 'reader' => [15, 15], 'writer' => [15, 15], 'voice' => [15, 9.5], 'reviewer' => [14, 14], 'player' => [12.5, 12], 'kolom' => [11.5, 10.5], 'streak' => [10, 12], 'cross' => [15, 12.5]];

    /** A five-point star round (0,0). */
    private static function star(float $r): string
    {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $a = deg2rad(-90 + 36 * $i);
            $rr = $i % 2 ? $r * 0.45 : $r;
            $pts[] = round($rr * cos($a), 2).' '.round($rr * sin($a), 2);
        }

        return 'M'.implode('L', $pts).'Z';
    }

    /**
     * D181 (owner: "instead of the tier, why the medal makes more sense; tier is already described by colour; work with
     * UX experts to design the badge properly"; redrawn after a UX and visual-design review). A ribbon and a disc: the
     * logo's hand-fan rises like a half-sun behind a large emblem that says what the medal is for. The tier shows three
     * ways, never by colour alone: colour, the edge (ব্রোঞ্জ plain, রুপো a bold second ring, সোনা scalloped) and one
     * to three stars. From 200px the badge's name sits on a banner; smaller sizes drop the name (it is beside the
     * medal), then the stars. Logo palette only; our own fixed drawing.
     */
    public static function medalSvg(string $key, int $tier, int $size = 104): string
    {
        $full = $size >= 200;
        $small = $size < 96;
        [$disc, $rim, $hub, $ribA, $ribB, $banner] = [
            0 => ['#FFFBF4', '#E8DCCB', '#FFFBF4', '#FFFBF4', '#FFFBF4', '#FFFBF4'],
            1 => ['#E39A6A', '#7A1022', '#FBEBD8', '#13212C', '#B0162B', '#13212C'],
            2 => ['#DDF1FA', '#1A86C2', '#FFFFFF', '#13212C', '#2DAAE2', '#13212C'],
            3 => ['#EFC08D', '#CB1B32', '#FFFBF4', '#CB1B32', '#13212C', '#CB1B32'],
        ][$tier];
        $ink = $tier ? '#13212C' : '#4D5A63';
        $line = $tier ? ($small ? 4 : 3) : 2;
        [$cy, $hubR, $box, $rayR] = $full ? [65, 18, 25, 35] : [74, 22, 30, 38];

        // the edge: plain, or scalloped for gold
        if ($tier === 3) {
            $d = '';
            for ($k = 0; $k < 16; $k++) {
                $a = deg2rad(360 * $k / 16);
                $m = deg2rad(360 * ($k + 0.5) / 16);
                $n = deg2rad(360 * ($k + 1) / 16);
                $d .= ($k ? '' : 'M'.round(60 + 44 * cos($a), 2).' '.round(72 + 44 * sin($a), 2))
                    .'Q'.round(60 + 51.5 * cos($m), 2).' '.round(72 + 51.5 * sin($m), 2).' '.round(60 + 44 * cos($n), 2).' '.round(72 + 44 * sin($n), 2);
            }
            $edge = '<path d="'.$d.'Z" fill="'.$disc.'" stroke="'.$ink.'" stroke-width="'.$line.'" stroke-linejoin="round"/>';
        } else {
            $edge = '<circle cx="60" cy="72" r="44" fill="'.$disc.'" stroke="'.$ink.'" stroke-width="'.$line.'"'.($tier ? '' : ' stroke-dasharray="5 4"').'/>';
        }
        $ring = $tier === 2 ? '<circle cx="60" cy="72" r="38.5" fill="none" stroke="'.$rim.'" stroke-width="3.5"/>'
            : ($small ? '' : '<circle cx="60" cy="72" r="39.5" fill="none" stroke="'.$rim.'" stroke-width="1.6"/>');

        // the logo's fan, as a half-sun behind the emblem
        $n = $small ? 8 : 12;
        $rays = '';
        for ($k = 0; $k < $n; $k++) {
            $p = fn ($a) => round(60 + $rayR * cos($a), 2).' '.round($cy + $rayR * sin($a), 2);
            $rays .= '<path d="M60 '.$cy.'L'.$p(deg2rad(180 + 180 * $k / $n)).'A'.$rayR.' '.$rayR.' 0 0 1 '.$p(deg2rad(180 + 180 * ($k + 1) / $n)).'Z" fill="'
                .($tier ? ($k % 2 ? '#F08D90' : '#CB1B32') : ($k % 2 ? '#FFFBF4' : '#E8DCCB')).'"/>';
        }
        $emblem = str_replace(['{ink}', '{hub}', '{star}'], [$ink, $hub, self::star(4.2)], self::EMBLEMS[$key]);

        // the tier's stars (hollow while not yet earned); dropped on the smallest medals
        $stars = '';
        if (! $small) {
            [$sr, $sy, $gap] = $full ? [3.7, 108.8, 10.5] : [4.6, 106, 12];
            $count = $tier ?: 3;
            for ($i = 0; $i < $count; $i++) {
                $stars .= '<path d="'.self::star($sr).'" transform="translate('.(60 + ($i - ($count - 1) / 2) * $gap).' '.$sy.')" '
                    .($tier ? 'fill="'.$ink.'"' : 'fill="none" stroke="'.$ink.'" stroke-width="1.2" stroke-linejoin="round"').'/>';
            }
        }

        // the name on a banner, only where it can be read
        $name = '';
        if ($full) {
            $fs = self::NAME_SIZE[$key][app()->getLocale() === 'en' ? 1 : 0];
            $name = '<path d="M2 80H118L114 90.5L118 101H2L6 90.5Z" fill="'.$banner.'" stroke="'.$ink.'" stroke-width="2" stroke-linejoin="round"/>'
                .'<text x="60" y="'.round(90.5 + $fs * 0.36, 1).'" font-family="\'Anek Bangla\',\'Noto Sans Bengali\',sans-serif" font-weight="800" font-size="'.$fs.'" text-anchor="middle" fill="'.($tier ? '#FFFFFF' : $ink).'">'.e(__(self::ALL[$key][0])).'</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.round($size * 130 / 120).'" viewBox="0 0 120 130" aria-hidden="true" focusable="false">'
            .'<path d="M34 0h22l10 40H48z" fill="'.$ribA.'"/><path d="M86 0H64L54 40h18z" fill="'.$ribB.'"/>'
            .'<path d="M34 0h22l10 40H48z M86 0H64L54 40h18z" fill="none" stroke="'.$ink.'" stroke-width="'.($tier ? 2.5 : 2).'" stroke-linejoin="round"/>'
            .$edge.$ring.$rays
            .'<circle cx="60" cy="'.$cy.'" r="'.$hubR.'" fill="'.$hub.'" stroke="'.$ink.'" stroke-width="2"/>'
            .'<g transform="translate('.(60 - $box / 2).' '.($cy - $box / 2).') scale('.round($box / 24, 3).')">'.$emblem.'</g>'
            .$name.$stars.'</svg>';
    }
}
