<?php

namespace App\Support;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * নতুন কলম (owner, 2026-11-24; research §B6): a writer's first piece on Hatpakha. A piece out in the last 30 days whose
 * writers have no earlier published piece carries the label "নতুন কলম"; the home page shows the latest three.
 * Labels and the strip only: no e-mail and no phone notice (no new automatic message was approved).
 */
final class Debut
{
    public const DAYS = 30;

    private const CACHE = 'debut.ids';

    /**
     * Ids of the first pieces out in the last 30 days, newest first. A writer's own work only (not the desk's byline,
     * the desk's own drafts or editorials: Post::certifiable). Only the ids are cached, for an hour (D18).
     *
     * @return list<int>
     */
    public static function ids(): array
    {
        return array_map('intval', (array) cache()->remember(self::CACHE, 3600, fn () => self::compute()));
    }

    public static function forget(): void
    {
        cache()->forget(self::CACHE);
    }

    /** @return list<int> */
    public static function compute(): array
    {
        $recent = Post::published()->certifiable()->where('published_at', '>=', now()->subDays(self::DAYS))
            ->orderByDesc('published_at')->limit(200)->get(['id', 'published_at']);
        if ($recent->isEmpty()) {
            return [];
        }
        $byPost = DB::table('author_post')->whereIn('post_id', $recent->pluck('id'))->get(['post_id', 'author_id'])->groupBy('post_id');
        // The first publication of each of these writers (published or scheduled-and-out, as readers see it)
        $first = DB::table('author_post')->join('posts', 'posts.id', '=', 'author_post.post_id')
            ->whereIn('author_post.author_id', $byPost->flatten()->pluck('author_id')->unique())
            ->whereIn('posts.status', ['published', 'scheduled'])->where('posts.published_at', '<=', now())->whereNull('posts.deleted_at')
            ->groupBy('author_post.author_id')->selectRaw('author_post.author_id as a, min(posts.published_at) as at')->pluck('at', 'a');

        return $recent->filter(function (Post $p) use ($byPost, $first) {
            $authors = $byPost->get($p->id, collect());

            return $authors->isNotEmpty() && $authors->every(function ($row) use ($p, $first) {
                $at = $first->get($row->author_id);

                return $at !== null && \Illuminate\Support\Carbon::parse($at)->gte($p->published_at->copy()->startOfSecond());
            });
        })->pluck('id')->values()->all();
    }

    public static function is(Post $post): bool
    {
        return in_array($post->id, self::ids(), true);
    }

    /** The home page strip: the latest first pieces (up to three), with what the cards need. */
    public static function latest(int $n = 3): Collection
    {
        $ids = array_slice(self::ids(), 0, $n);

        return $ids ? Post::published()->whereIn('id', $ids)->with(['authors', 'section'])->orderByDesc('published_at')->get() : collect();
    }

    /** For the desk list: has this submission's writer nothing published on Hatpakha yet? */
    public static function firstFromWriter(Post $post): bool
    {
        if (! $post->submitted_by || in_array($post->status, ['published'], true)) {
            return false;
        }
        $authorIds = Author::where('user_id', $post->submitted_by)->pluck('id');

        return $authorIds->isEmpty() || ! Post::published()->whereKeyNot($post->id)->whereHas('authors', fn ($q) => $q->whereIn('authors.id', $authorIds))->exists();
    }
}
