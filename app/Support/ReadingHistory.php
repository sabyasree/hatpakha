<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * D166: a signed-in reader's "আমার পড়া": how far they read each piece (only while their switch is on), what is new
 * from the writers and serials they follow, saved and liked pieces. Only they see any of it.
 */
final class ReadingHistory
{
    /** A piece counts as unfinished between these (percent). */
    public const STARTED = 8;

    public const DONE = 92;

    public static function record(User $user, Post $post, int $progress): void
    {
        if (! $user->reading_history || ! $post->isLive()) {
            return;
        }
        $progress = max(0, min(100, $progress));
        $row = DB::table('reading_history')->where('user_id', $user->id)->where('post_id', $post->id);
        $before = $row->value('progress');
        if ($before !== null) {
            // The furthest point counts; opening it again moves it to the top.
            $row->update(['progress' => max((int) $before, $progress), 'read_at' => now()]);
        } else {
            DB::table('reading_history')->insert(['user_id' => $user->id, 'post_id' => $post->id, 'progress' => $progress, 'read_at' => now()]);
        }
    }

    public static function clear(User $user): int
    {
        return DB::table('reading_history')->where('user_id', $user->id)->delete();
    }

    /** Pieces read part of the way, most recent first, each with ->progress. */
    public static function unfinished(User $user, int $limit = 3): Collection
    {
        $rows = DB::table('reading_history')->where('user_id', $user->id)->whereBetween('progress', [self::STARTED, self::DONE - 1])
            ->latest('read_at')->limit($limit)->pluck('progress', 'post_id');

        return self::posts($rows);
    }

    public static function recent(User $user, int $limit = 20): Collection
    {
        return self::posts(DB::table('reading_history')->where('user_id', $user->id)->latest('read_at')->limit($limit)->pluck('progress', 'post_id'));
    }

    /** New in the last 30 days from followed writers and serials, not yet opened. */
    public static function newForYou(User $user, int $limit = 5): Collection
    {
        $authors = DB::table('follows')->where('user_id', $user->id)->pluck('author_id');
        $serials = DB::table('serial_follows')->where('user_id', $user->id)->pluck('serial_id');
        if ($authors->isEmpty() && $serials->isEmpty()) {
            return collect();
        }
        $seen = DB::table('reading_history')->where('user_id', $user->id)->select('post_id');

        return Post::published()->with(['authors', 'section', 'serial'])->where('published_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->whereHas('authors', fn ($a) => $a->whereIn('authors.id', $authors))->orWhereIn('serial_id', $serials))
            ->whereNotIn('id', $seen)->latest('published_at')->limit($limit)->get();
    }

    /** @param Collection<int,int> $progressById post_id => progress, in order */
    private static function posts(Collection $progressById): Collection
    {
        $posts = Post::published()->with(['authors', 'section'])->whereIn('id', $progressById->keys())->get()->keyBy('id');

        return $progressById->keys()->map(fn ($id) => ($p = $posts->get($id)) ? tap($p, fn ($p) => $p->progress = (int) $progressById[$id]) : null)->filter()->values();
    }

    /** Daily: history older than a year goes (D166). */
    public static function prune(): int
    {
        return DB::table('reading_history')->where('read_at', '<', now()->subYear())->delete();
    }
}
