<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Carbon;

/**
 * D269 (owner, 2026-11-23: "need to add a rule that episodes can only publish on Saturday"). Every episode of a serial
 * goes out on the serial's day (Saturday), India time: the desk's approve dialog and the piece's own publishing time
 * refuse any other day; the writer's agreement keeps the day the editor chose; the detective series already keeps it.
 */
final class SerialDay
{
    public const MESSAGE = 'ধারাবাহিকের পর্ব শুধু শনিবার প্রকাশ হয়। একটি শনিবার বেছে নিন।';

    /** A validation rule for a publishing time of this piece (nothing for an ordinary piece). */
    public static function rule(?Post $post): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($post) {
            // the box's value is checked as typed, in the desk's own time (India time)
            $at = $value instanceof \DateTimeInterface ? Carbon::instance($value) : (filled($value) ? Carbon::parse((string) $value, \Filament\Support\Facades\FilamentTimezone::get()) : null);
            if ($post?->serial && $at && ! $post->serial->isEpisodeDay($at)) {
                $fail(self::MESSAGE);
            }
        };
    }
}
