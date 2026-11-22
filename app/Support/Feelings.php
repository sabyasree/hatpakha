<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * D231 (owner, shown another magazine's reaction box: "don't copy, but we can do something from this idea"; then
 * "build it, it should be automatic. No prize money involved, so no need to come to me"). Under a piece a signed-in
 * reader says how the piece left THEM, one of five, in place of the bare heart. One answer a reader a piece; it can
 * be changed or taken back. A heart given before this stays and counts. The writer sees the answers in their account.
 * On the first days of a month the piece readers answered most in the month before becomes that month's
 * "পাঠকের পছন্দ", by itself: a mark on the piece and a line in the readers' letter, no prize and no money.
 */
final class Feelings
{
    /** key => [what the reader taps, how the count reads to the writer, the list's heading on /pathoker-pochhondo/, English for the AI] */
    public const LIST = [
        'touched' => ['মন ছুঁয়ে গেল', ':n জনের মন ছুঁয়েছে', 'যে লেখা পাঠকের মন ছুঁয়েছে', 'it moved them'],
        'think' => ['ভাবিয়ে তুলল', ':n জনকে ভাবিয়েছে', 'যে লেখা পাঠককে ভাবিয়েছে', 'it made them think'],
        'smile' => ['হাসি পেল', ':n জনকে হাসিয়েছে', 'যে লেখা পাঠককে হাসিয়েছে', 'it made them smile'],
        'surprise' => ['চমকে দিল', ':n জনকে চমকে দিয়েছে', 'যে লেখা পাঠককে চমকে দিয়েছে', 'it surprised them'],
        'stay' => ['মনে থেকে যাবে', ':n জনের মনে থেকে যাবে', 'যে লেখা পাঠকের মনে থেকে গেছে', 'it will stay with them'],
    ];

    /** Fewer answers than this in the month and nobody is named: a handful of friends must not decide it. */
    public const MIN = 10;

    public static function mine(?User $u, Post $p): ?string
    {
        $row = $u ? DB::table('post_likes')->where(['user_id' => $u->id, 'post_id' => $p->id])->first() : null;

        return $row ? ($row->feeling ?: 'heart') : null;
    }

    /** Tap a feeling: it is set; tap the same one again: it is taken back. With no feeling (the old heart): on or off. */
    public static function set(User $u, Post $p, ?string $feeling): array
    {
        $feeling = isset(self::LIST[$feeling]) ? $feeling : null;
        $key = ['user_id' => $u->id, 'post_id' => $p->id];
        $row = DB::table('post_likes')->where($key)->first();
        if ($row && ($feeling === null || $row->feeling === $feeling)) {
            DB::table('post_likes')->where($key)->delete();
            $now = null;
        } elseif ($row) {
            DB::table('post_likes')->where($key)->update(['feeling' => $feeling]);
            $now = $feeling;
        } else {
            DB::table('post_likes')->insertOrIgnore($key + ['feeling' => $feeling, 'created_at' => now()]);
            $now = $feeling ?? 'heart';
        }
        $counts = self::recount($p);

        return ['on' => $now !== null, 'feeling' => $now, 'count' => (int) $p->likes_count, 'counts' => $counts];
    }

    /** D252: one network may give at most this many guest answers to one piece in a day (Jio and BSNL put many phones behind one address: UX review). */
    public const GUEST_PER_NET = 30;

    /**
     * D252 (owner: "build B": readers without an account can say how a piece left them). Counted once a phone: the
     * phone keeps a random id in a cookie, and only a hash of it is stored; a network gives at most GUEST_PER_NET a
     * piece a day. Tapping the same answer again takes it back. These answers are added to the counts readers see,
     * but the monthly পাঠকের পছন্দ is still decided by members' answers alone (it cannot be swayed by cleared cookies).
     *
     * @return array{on: bool, feeling: ?string, count: int, counts: array<string, int>}|null null when the cap is reached
     */
    public static function guestSet(Post $p, string $deviceId, string $ip, ?string $feeling): ?array
    {
        $feeling = isset(self::LIST[$feeling]) ? $feeling : null;
        $device = hash('sha256', 'hp-dev|'.$deviceId);
        $net = hash('sha256', 'hp-net|'.$ip.'|'.now('Asia/Kolkata')->toDateString());
        $key = ['post_id' => $p->id, 'device' => $device];
        $row = DB::table('guest_feelings')->where($key)->first();
        if ($row && ($feeling === null || $row->feeling === $feeling)) {
            DB::table('guest_feelings')->where($key)->delete();
            $now = null;
        } elseif ($row) {
            DB::table('guest_feelings')->where($key)->update(['feeling' => $feeling]);
            $now = $feeling;
        } elseif ($feeling) {
            if (DB::table('guest_feelings')->where('post_id', $p->id)->where('net', $net)->count() >= self::GUEST_PER_NET) {
                return null;
            }
            DB::table('guest_feelings')->insertOrIgnore($key + ['net' => $net, 'feeling' => $feeling, 'created_at' => now()]);
            $now = $feeling;
        } else {
            $now = null;
        }
        $counts = self::recount($p);

        return ['on' => $now !== null, 'feeling' => $now, 'count' => (int) $p->likes_count, 'counts' => $counts];
    }

    /** @return array<string, int> members' and guests' answers together */
    public static function recount(Post $p): array
    {
        $by = DB::table('post_likes')->where('post_id', $p->id)->selectRaw('feeling, count(*) as n')->groupBy('feeling')->pluck('n', 'feeling');
        $guests = DB::table('guest_feelings')->where('post_id', $p->id)->selectRaw('feeling, count(*) as n')->groupBy('feeling')->pluck('n', 'feeling');
        $counts = collect(array_keys(self::LIST))->mapWithKeys(fn ($k) => [$k => (int) ($by[$k] ?? 0) + (int) ($guests[$k] ?? 0)])->all();
        $p->forceFill(['likes_count' => (int) $by->sum() + (int) $guests->sum(), 'feeling_counts' => $counts])->saveQuietly();

        return $counts;
    }

    /** @return array<string, int> */
    public static function counts(Post $p): array
    {
        $c = is_array($p->feeling_counts) ? $p->feeling_counts : (array) json_decode((string) $p->feeling_counts, true);

        return collect(array_keys(self::LIST))->mapWithKeys(fn ($k) => [$k => (int) ($c[$k] ?? 0)])->all();
    }

    /**
     * D232: the pieces most readers gave this answer to (a writer's or the desk's, any age), most first.
     * Each carries `feeling_n`, how many.
     */
    public static function top(string $feeling, int $limit = 8): \Illuminate\Support\Collection
    {
        $n = DB::table('post_likes')->where('feeling', $feeling)->groupBy('post_id')->selectRaw('post_id, count(*) as n')->orderByDesc('n')->orderByDesc('post_id')->limit($limit * 3)->pluck('n', 'post_id');
        $posts = Post::published()->with('authors')->whereIn('id', $n->keys())->where('noindex', false)->get()->keyBy('id');

        return $n->keys()->map(fn ($id) => $posts[$id] ?? null)->filter()->take($limit)->each(fn (Post $p) => $p->feeling_n = (int) $n[$p->id])->values();
    }

    /** D232: true counts in plain English for the AI (marketing, serial writers); empty below $min answers in all. */
    public static function facts(Post $p, int $min = 1): string
    {
        $c = array_filter(self::counts($p));
        arsort($c);

        return array_sum($c) >= $min ? collect($c)->map(fn ($n, $k) => $n.' said '.self::LIST[$k][3])->implode(', ') : '';
    }

    /** For the writer: "৩ জনের মন ছুঁয়েছে · ২ জনকে ভাবিয়েছে" (most first); empty when nobody has answered. */
    public static function summary(Post $p): string
    {
        return collect(self::counts($p))->filter()->sortDesc()->map(fn ($n, $k) => str_replace(':n', Bn::num((string) $n), self::LIST[$k][1]))->implode(' · ');
    }

    public static function monthLabel(string $month): string
    {
        [$y, $m] = array_map('intval', explode('-', $month));

        return Bn::MONTHS[$m - 1].' '.Bn::year($y);
    }

    /**
     * The readers' choice of the month before: the piece (out in the last two months, by a writer and not the desk,
     * never chosen before) that got the most answers during that month. Once a month; nobody when too few answered.
     */
    public static function pickMonth(?Carbon $now = null): ?Post
    {
        $now = ($now ?? now('Asia/Kolkata'))->copy()->timezone('Asia/Kolkata');
        $month = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $key = $month->format('Y-m');
        if (Setting::get('feelings.picked') === $key) {
            return null;
        }
        Setting::put('feelings.picked', $key);
        $top = DB::table('post_likes')->join('posts', 'posts.id', '=', 'post_likes.post_id')
            ->whereBetween('post_likes.created_at', [$month->copy()->utc(), $month->copy()->endOfMonth()->utc()])
            ->where('posts.status', 'published')->whereNull('posts.readers_choice')->where('posts.published_at', '>=', $month->copy()->subMonthNoOverflow()->utc())
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('author_post')->join('authors', 'authors.id', '=', 'author_post.author_id')->whereColumn('author_post.post_id', 'posts.id')->where('authors.is_desk', false))
            ->groupBy('posts.id', 'posts.published_at')->selectRaw('posts.id, count(*) as n')
            ->orderByDesc('n')->orderBy('posts.published_at')->orderBy('posts.id')->first();
        if (! $top || (int) $top->n < self::MIN) {
            return null;
        }
        $post = Post::find($top->id);
        $post->forceFill(['readers_choice' => $key])->saveQuietly();
        AuditLog::record('readers_choice', $post, ['month' => $key, 'answers' => (int) $top->n, 'auto' => true]);

        return $post;
    }

    /** The newest readers' choice, while it is news (35 days). */
    public static function latest(): ?Post
    {
        $key = now('Asia/Kolkata')->subDays(35)->subMonthNoOverflow()->format('Y-m');

        return Post::published()->with('authors')->whereNotNull('readers_choice')->where('readers_choice', '>=', $key)->orderByDesc('readers_choice')->first();
    }
}
