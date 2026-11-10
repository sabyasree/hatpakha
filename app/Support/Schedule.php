<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Carbon;

/**
 * Publishing rhythm (PLAN.md: at least 3 pieces a week): suggests the next free slot,
 * Monday / Wednesday / Friday 09:00 India time, one piece per slot. Only a suggestion.
 */
final class Schedule
{
    public const DAYS = [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::FRIDAY];

    public static function nextSlot(): Carbon
    {
        $tz = 'Asia/Kolkata';
        $day = now($tz)->startOfDay();
        for ($i = 0; $i < 120; $i++, $day = $day->copy()->addDay()) {
            if (! in_array($day->dayOfWeek, self::DAYS, true)) {
                continue;
            }
            $slot = $day->copy()->setTime(9, 0);
            if ($slot->lt(now($tz)->addHour())) {
                continue;
            }
            $taken = Post::whereIn('status', ['scheduled', 'published'])
                ->whereBetween('published_at', [$day->copy()->utc(), $day->copy()->endOfDay()->utc()])->exists()
                || Post::where('status', 'confirm')->get()->contains(fn (Post $p) => str_starts_with((string) ($p->pending_edits['at'] ?? ''), $slot->toDateString()));
            if (! $taken) {
                return $slot->utc();
            }
        }

        return now()->addDay();
    }
}
