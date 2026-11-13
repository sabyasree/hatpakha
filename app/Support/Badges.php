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
}
