<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use App\Notifications\NewFromWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * D64: when a piece goes live, the writer's followers get one e-mail each (they asked by following),
 * and a phone notification where they turned those on (D127).
 * Run from the scheduler; each piece is handled once (followers_notified_at), up to 500 followers a run.
 */
final class FollowNotifier
{
    public static function run(int $maxPosts = 3): int
    {
        $sent = 0;
        foreach (Post::published()->with('authors')->whereNull('followers_notified_at')->where('published_at', '>=', now()->subDays(3))->limit($maxPosts)->get() as $post) {
            $post->forceFill(['followers_notified_at' => now()])->saveQuietly();   // first, so a crash never sends twice
            $mail = NewsletterSender::realMail();
            if (! $mail && ! WebPush::configured()) {
                continue;
            }
            $link = $post->url().'?utm_source=hatpakha-push&utm_medium=push';
            $told = [];
            // D108: readers who follow this serial hear about the new episode (once, even if they also follow the writer).
            if ($post->serial_id && $post->serial) {
                $ids = DB::table('serial_follows')->where('serial_id', $post->serial_id)->limit(2000)->pluck('user_id');
                foreach (User::whereIn('id', $ids)->whereNotNull('email_verified_at')->get() as $u) {
                    if ($mail) {
                        rescue(fn () => $u->notify(new \App\Notifications\NewEpisode($post, URL::signedRoute('serial.unfollow', ['serial' => $post->serial_id, 'user' => $u->id]))), null, false);
                    }
                    // D127: and on their phone, if they turned that on
                    rescue(fn () => WebPush::toUser($u, ['title' => $post->serial->title.': নতুন পর্ব', 'body' => trim($post->episodeLabel().' · '.$post->title, ' ·'), 'url' => $link, 'tag' => 'serial-'.$post->serial_id]), 0, false);
                    $told[$u->id] = true;
                    $sent++;
                }
            }
            foreach ($post->authors as $author) {
                $ids = DB::table('follows')->where('author_id', $author->id)->limit(500)->pluck('user_id');
                foreach (User::whereIn('id', $ids)->whereNotIn('id', array_keys($told))->whereNotNull('email_verified_at')->get() as $u) {
                    if ($mail) {
                        rescue(fn () => $u->notify(new NewFromWriter($post, $author, URL::signedRoute('unfollow', ['author' => $author->id, 'user' => $u->id]))), null, false);
                    }
                    rescue(fn () => WebPush::toUser($u, ['title' => $author->publicName().'-এর নতুন লেখা', 'body' => $post->title, 'url' => $link, 'tag' => 'post-'.$post->id]), 0, false);
                    $told[$u->id] = true;
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
