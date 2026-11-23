<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use App\Notifications\TemplateMail;
use Illuminate\Support\Carbon;

/**
 * Owner, 2026-11-23 (D255): a writer was told the day their selected piece goes out ("মনোনীত"). When the editor moves
 * that day, a tick box on the desk (on by default) sends a short e-mail with the old and the new date. Nothing goes
 * without the editor's save (D42); the day it goes live, the usual "প্রকাশিত" note follows (D252).
 */
final class DateChange
{
    /** Show the tick box: a scheduled piece with a writer, whose date in the form differs from the saved one and is still ahead. */
    public static function applies(?Post $post, mixed $newAt, mixed $status): bool
    {
        if (! $post || $post->status !== 'scheduled' || $status !== 'scheduled' || ! $post->submitted_by || blank($newAt)) {
            return false;
        }
        $new = rescue(fn () => Carbon::parse($newAt), null, false);

        return $new && $new->isFuture() && (! $post->published_at || abs($new->diffInMinutes($post->published_at)) >= 1);
    }

    /** After the save: tell the writer when the day really moved and is still ahead. True when an e-mail went. */
    public static function tell(Post $post, ?Carbon $before, ?User $by): bool
    {
        $writer = $post->submittedBy;
        $at = $post->published_at;
        if (! $writer || blank($writer->email) || $post->status !== 'scheduled' || ! $at || ! $at->isFuture()
            || ($before && abs($at->diffInMinutes($before)) < 1)) {
            return false;
        }
        $writer->notify(new TemplateMail('date_changed', [
            '{লেখা}' => $post->title,
            '{আগের_তারিখ}' => $before ? Bn::date($before) : '—',
            '{নতুন_তারিখ}' => Bn::date($at),
        ], Url::to('/account')));
        AuditLog::record('post.date_told', $post, ['from' => $before?->toIso8601String(), 'to' => $at->toIso8601String(), 'by' => $by?->id]);

        return true;
    }
}
