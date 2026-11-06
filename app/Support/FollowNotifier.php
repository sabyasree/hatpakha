<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use App\Notifications\NewFromWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * D64: when a piece goes live, the writer's followers get one e-mail each (they asked by following).
 * Run from the scheduler; each piece is handled once (followers_notified_at), up to 500 followers a run.
 */
final class FollowNotifier
{
    public static function run(int $maxPosts = 3): int
    {
        $sent = 0;
        foreach (Post::published()->with('authors')->whereNull('followers_notified_at')->where('published_at', '>=', now()->subDays(3))->limit($maxPosts)->get() as $post) {
            $post->forceFill(['followers_notified_at' => now()])->saveQuietly();   // first, so a crash never sends twice
            if (! NewsletterSender::realMail()) {
                continue;
            }
            foreach ($post->authors as $author) {
                $ids = DB::table('follows')->where('author_id', $author->id)->limit(500)->pluck('user_id');
                foreach (User::whereIn('id', $ids)->whereNotNull('email_verified_at')->get() as $u) {
                    rescue(fn () => $u->notify(new NewFromWriter($post, $author, URL::signedRoute('unfollow', ['author' => $author->id, 'user' => $u->id]))), null, false);
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
