<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Serial;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * D281 (owner: "on the 2nd or any episode there is no way to go to the previous one or start from the 1st; readers get
 * confused"; UX review): where a reader is in a serial, worked out once per page. Published episodes only, in order,
 * so a missing number never hides a button. "Read" marks come only from a signed-in reader's own reading history.
 */
final class EpisodeNav
{
    /** @return array{episodes: Collection, first: ?Post, prev: ?Post, next: ?Post, count: int, index: int, read: array<int,int>} */
    public static function for(Post $post): array
    {
        $episodes = self::episodes($post->serial);
        $i = $episodes->search(fn (Post $p) => $p->id === $post->id);
        $i = $i === false ? -1 : $i;

        return [
            'episodes' => $episodes, 'first' => $episodes->first(), 'count' => $episodes->count(), 'index' => $i,
            'prev' => $i > 0 ? $episodes[$i - 1] : null, 'next' => $i >= 0 ? $episodes->get($i + 1) : null,
            'read' => self::readMap(auth()->user(), $episodes),
        ];
    }

    /** The serial's published episodes, oldest first (titles and dates only). */
    public static function episodes(?Serial $serial): Collection
    {
        return $serial ? Post::published()->where('serial_id', $serial->id)->orderBy('episode_no')->orderBy('published_at')
            ->get(['id', 'slug', 'title', 'serial_id', 'episode_no', 'published_at', 'status'])->each->setRelation('serial', $serial)->values() : collect();
    }

    /** post id => how far the reader got (percent), for a signed-in reader who keeps a reading history. */
    public static function readMap(?User $user, Collection $episodes): array
    {
        if (! $user?->reading_history || $episodes->isEmpty()) {
            return [];
        }

        return DB::table('reading_history')->where('user_id', $user->id)->whereIn('post_id', $episodes->pluck('id'))->pluck('progress', 'post_id')
            ->map(fn ($p) => (int) $p)->all();
    }

    /** "✓ পড়েছেন" / "অর্ধেক পড়া" / null. */
    public static function mark(array $read, int $postId): ?string
    {
        $p = $read[$postId] ?? null;

        return $p === null || $p < ReadingHistory::STARTED ? null : ($p >= ReadingHistory::DONE ? '✓ পড়েছেন' : 'অর্ধেক পড়া');
    }

    /** For the serial page: where a signed-in reader stopped, or the next one they have not read. @return array{0: string, 1: Post}|null */
    public static function resume(Collection $episodes, array $read): ?array
    {
        if (! $read) {
            return null;
        }
        foreach ($episodes as $e) {
            $p = $read[$e->id] ?? 0;
            if ($p >= ReadingHistory::STARTED && $p < ReadingHistory::DONE) {
                return ['যেখানে থেমেছিলেন', $e];
            }
            if ($p < ReadingHistory::STARTED) {
                return $e->id === $episodes->first()->id ? null : ['পরের না-পড়া পর্ব', $e];
            }
        }

        return null;
    }
}
