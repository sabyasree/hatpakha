<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\Serial;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * D268 (owner, 2026-11-23: "for serials, remind the authors to add the next episode in proper time, if they didn't add
 * it already; every Saturday will publish"). A writer's own running serial goes out on its day (Saturday); the next
 * episode should reach the desk about a week before. When none is in hand:
 *   1. the day the week's episode goes out, from 6pm India time (or the day after): "the next one is due";
 *   2. Wednesday from 10am (or Thursday): one more, "there is still time for Saturday if it comes by Thursday".
 * At most these two a week, each once (kept in settings, so a release never sends one twice). Never for the desk's own
 * serials, a paused or finished one, one whose planned episodes are all out, or one silent for three weeks.
 * Nothing is published or changed by it. Sent by itself (owner's request; CLAUDE.md §2).
 */
final class SerialReminders
{
    private const TZ = 'Asia/Kolkata';

    public static function send(?Carbon $now = null): int
    {
        $now = ($now ?? now())->copy()->setTimezone(self::TZ);
        $stage = self::stage($now);
        if (! $stage) {
            return 0;
        }
        $sent = 0;
        foreach (Serial::where('status', 'active')->whereNotNull('user_id')->whereNull('writer')->with('user')->get() as $serial) {
            $sent += (int) self::remind($serial, $now, $stage);
        }

        return $sent;
    }

    /** 1 on the serial day from 6pm or the day after; 2 on Wednesday from 10am or Thursday; else nothing. */
    private static function stage(Carbon $now): ?int
    {
        return match (true) {
            $now->isSaturday() && $now->hour >= 18, $now->isSunday() => 1,
            $now->isWednesday() && $now->hour >= 10, $now->isThursday() => 2,
            default => null,
        };
    }

    /** The Saturday the next episode should go out on. */
    public static function nextDay(Serial $serial, Carbon $now): Carbon
    {
        $day = (int) ($serial->day ?? 6);
        $d = $now->copy()->setTimezone(self::TZ)->startOfDay();
        $d = $d->dayOfWeek === $day && $now->copy()->setTimezone(self::TZ)->hour < 9 ? $d : $d->next($day);

        return $d->setTime(9, 0);
    }

    private static function remind(Serial $serial, Carbon $now, int $stage): bool
    {
        $writer = $serial->user;
        if (! $writer || blank($writer->email) || $writer->spam_reason) {
            return false;
        }
        $last = $serial->latestEpisode();
        if (! $last || $last->published_at->lt($now->copy()->subDays(21))) {
            return false;   // not started, or the writer has stopped: no nagging
        }
        $live = $serial->episodes()->published()->count();
        if ($serial->planned_count && $live >= $serial->planned_count) {
            return false;   // all the planned episodes are out
        }
        $inHand = $serial->episodes()->where(fn ($q) => $q->whereIn('status', ['pending', 'confirm', 'changes'])
            ->orWhere(fn ($q) => $q->where('status', 'scheduled')->where('published_at', '>', $now)))->exists();
        if ($inHand) {
            return false;
        }
        $target = self::nextDay($serial, $now);
        $key = 'serial.reminded.'.$serial->id;
        $mark = $target->toDateString().':'.$stage;
        if (Setting::get($key) === $mark || (Setting::get($key) === $target->toDateString().':2' && $stage === 1)) {
            return false;   // this one went already
        }
        $date = Bn::date($target).', '.Serial::DAYS[$target->dayOfWeek];
        rescue(fn () => $writer->notify(new \App\Notifications\TemplateMail($stage === 1 ? 'serial_next' : 'serial_next_soon', [
            '{ধারাবাহিক}' => $serial->title,
            '{পর্ব}' => Bn::num((string) $serial->nextEpisodeNo()),
            '{তারিখ}' => $date,
        ], Url::to('/submit'))), null, true);
        Setting::put($key, $mark);
        AuditLog::record('serial.reminded', null, ['serial' => $serial->id, 'stage' => $stage, 'for' => $target->toDateString()]);

        return true;
    }
}
