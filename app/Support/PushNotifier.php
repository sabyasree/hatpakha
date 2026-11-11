<?php

namespace App\Support;

use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * D127/D128: who gets a phone notification when a piece goes live, and the once-a-day note.
 * Each browser hears about a piece at most once, whichever way it asked (account follows or topics).
 */
final class PushNotifier
{
    /** A new piece: followers of its serial or writers, through their account or a topic on the browser. Returns how many were delivered. */
    public static function forPost(Post $post): int
    {
        if (! WebPush::configured()) {
            return 0;
        }
        $link = $post->url().'?utm_source=hatpakha-push&utm_medium=push';
        $done = [];
        $sent = 0;
        $send = function ($subs, array $message) use (&$done, &$sent) {
            foreach ($subs as $sub) {
                if (! isset($done[$sub->id])) {
                    $done[$sub->id] = true;
                    $sent += WebPush::send($sub, $message) ? 1 : 0;
                }
            }
        };

        if ($post->serial_id && $post->serial) {
            $users = DB::table('serial_follows')->where('serial_id', $post->serial_id)->pluck('user_id');
            $send(self::subscribers($users, 'serial:'.$post->serial_id),
                ['title' => $post->serial->title.': নতুন পর্ব', 'body' => trim($post->episodeLabel().' · '.$post->title, ' ·'), 'url' => $link, 'tag' => 'serial-'.$post->serial_id, 'kind' => 'serial']);
        }
        foreach ($post->authors->where('is_desk', false) as $author) {
            $users = DB::table('follows')->where('author_id', $author->id)->pluck('user_id');
            $send(self::subscribers($users, 'author:'.$author->id),
                ['title' => $author->publicName().'-এর নতুন লেখা', 'body' => $post->title, 'url' => $link, 'tag' => 'post-'.$post->id, 'kind' => 'post']);
        }

        // D132: readers who asked for every new piece of a section (e.g. only কবিতা). No account needed.
        if ($post->section_id && $post->section) {
            $send(PushSubscription::whereJsonContains('topics', 'section:'.$post->section_id)->limit(5000)->get(),
                ['title' => $post->section->name_bn.': নতুন লেখা', 'body' => $post->title, 'url' => $link, 'tag' => 'post-'.$post->id, 'kind' => 'section']);
        }

        return $sent;
    }

    /** D132: the owner's announcement goes out in batches of 200 a minute (so a big list never times out). */
    public static function announceBatch(int $size = 200): int
    {
        $a = \App\Models\PushAnnouncement::whereIn('status', ['queued', 'sending'])->oldest('id')->first();
        if (! $a || ! WebPush::configured()) {
            return 0;
        }
        $a->status === 'queued' && $a->forceFill(['status' => 'sending'])->save();
        $subs = self::audience($a->audience)->where('id', '>', $a->last_id)->orderBy('id')->limit($size)->get();
        $sent = 0;
        foreach ($subs as $sub) {
            $sent += WebPush::send($sub, ['title' => $a->title, 'body' => $a->body, 'url' => $a->url, 'tag' => 'announce-'.$a->id, 'kind' => 'announce']) ? 1 : 0;
        }
        $a->forceFill(['last_id' => $subs->max('id') ?? $a->last_id, 'sent' => $a->sent + $sent])->save();
        if ($subs->count() < $size) {
            $a->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            \App\Models\AuditLog::record('push.announced', null, ['announcement' => $a->id, 'sent' => $a->sent]);
        }

        return $sent;
    }

    /** all = every phone with notifications on; daily = phones that asked for the daily note. */
    public static function audience(string $audience)
    {
        return $audience === 'daily' ? PushSubscription::whereJsonContains('topics', 'daily') : PushSubscription::query();
    }

    /** Browsers linked to one of these accounts (topic "account"), or that asked for this topic. */
    private static function subscribers($userIds, string $topic)
    {
        return PushSubscription::query()
            ->where(fn ($q) => $q->where(fn ($a) => $a->whereIn('user_id', $userIds)->whereJsonContains('topics', 'account'))->orWhereJsonContains('topics', $topic))
            ->limit(5000)->get();
    }

    /** Once a day (evening): the pieces published since the last note. Nothing new, nothing sent. */
    public static function daily(): int
    {
        if (! WebPush::configured() || ! config('services.webpush.daily', true)) {
            return 0;
        }
        $since = Setting::get('push.daily_last');
        $since = $since ? \Illuminate\Support\Carbon::parse($since) : now()->subDay();
        $posts = Post::published()->where('published_at', '>', max($since, now()->subDays(2)))->where('published_at', '<=', now())
            ->where('noindex', false)->latest('published_at')->get(['id', 'slug', 'title', 'published_at']);
        Setting::put('push.daily_last', now()->toIso8601String());
        if ($posts->isEmpty()) {
            return 0;
        }
        $n = $posts->count();
        $message = [
            'title' => $n === 1 ? 'আজ হাতপাখায় নতুন লেখা' : 'আজ হাতপাখায় '.Bn::num((string) $n).'টি নতুন লেখা',
            'body' => $posts->take(3)->pluck('title')->implode(' · ').($n > 3 ? ' …' : ''),
            'url' => ($n === 1 ? $posts->first()->url() : url('/').'/').'?utm_source=hatpakha-push&utm_medium=push&utm_campaign=daily',
            'tag' => 'daily', 'kind' => 'daily',
        ];
        $sent = 0;
        PushSubscription::whereJsonContains('topics', 'daily')->chunkById(500, function ($subs) use ($message, &$sent) {
            foreach ($subs as $sub) {
                $sent += WebPush::send($sub, $message) ? 1 : 0;
            }
        });

        return $sent;
    }
}
