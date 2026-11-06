<?php

namespace App\Support;

use App\Models\Author;
use App\Models\Milestone;
use App\Models\Post;
use App\Models\Setting;

/**
 * Writers' milestones (D78), found from real data, no AI: a first piece published, the 10th/25th/50th/100th/200th
 * piece, and 5/10/15 years since their first piece with us. Only milestones reached recently (60 days; 30 for a
 * first piece) are proposed, so the archive never floods the owner. Nothing shows until the owner approves.
 */
final class Milestones
{
    public const PIECES = [10, 25, 50, 100, 200, 300];

    public const YEARS = [5, 10, 15];

    /** Once a day from the scheduler (cheap; no AI). Returns how many new proposals. */
    public static function detect(bool $force = false): int
    {
        $today = now('Asia/Kolkata')->toDateString();
        if (! $force && Setting::get('milestones.checked') === $today) {
            return 0;
        }
        Setting::put('milestones.checked', $today);
        $n = 0;
        $since = now()->subDays(60);
        // Writers with something new lately, or still active (a piece in 3 years) for the "years with us" milestones.
        $authors = Author::where('is_desk', false)->whereHas('posts', fn ($q) => $q->published()->where('published_at', '>=', now()->subYears(3)))->get();
        foreach ($authors as $a) {
            $dates = $a->posts()->published()->orderBy('published_at')->get(['posts.id', 'posts.published_at']);
            if ($dates->isEmpty()) {
                continue;
            }
            $first = $dates->first();
            if ($dates->last()->published_at->lt($since) && ! collect(self::YEARS)->contains(fn ($y) => $first->published_at->copy()->addYears($y)->between($since, now()))) {
                continue;
            }
            if ($dates->count() === 1 && $first->published_at->gte(now()->subDays(30))) {
                $n += self::propose($a, 'first', 'প্রথম লেখা প্রকাশিত', $first->published_at, $first->id);
            }
            foreach (self::PIECES as $k) {
                if ($dates->count() >= $k && ($nth = $dates[$k - 1])->published_at->gte($since)) {
                    $n += self::propose($a, 'pieces_'.$k, Bn::num((string) $k).'তম লেখা', $nth->published_at, $nth->id);
                }
            }
            foreach (self::YEARS as $y) {
                $at = $first->published_at->copy()->addYears($y);
                if ($at->between($since, now())) {
                    $n += self::propose($a, 'years_'.$y, 'হাতপাখায় '.Bn::num((string) $y).' বছর', $at, null);
                }
            }
        }

        return $n;
    }

    private static function propose(Author $a, string $kind, string $label, $at, ?int $postId): int
    {
        $m = Milestone::firstOrCreate(['author_id' => $a->id, 'kind' => $kind], ['label' => $label, 'post_id' => $postId, 'reached_at' => $at]);

        return (int) $m->wasRecentlyCreated;
    }

    /** What the home page shows: approved, reached in the last 45 days, newest first. */
    public static function forHome(int $limit = 6)
    {
        return Milestone::with(['author', 'post'])->where('status', 'approved')->where('reached_at', '>=', now()->subDays(45))
            ->latest('reached_at')->limit($limit)->get()->filter(fn ($m) => $m->author);
    }
}
