<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Post;

/**
 * D252 (owner: "build A"): the day a writer's piece goes live (published now, or a scheduled piece reaching its time),
 * the writer gets one e-mail and a note on their phone: it is out, here is a ready WhatsApp message, here is the
 * picture card. Once a piece; never for the desk's byline; never to someone who switched letters off; checked every
 * ten minutes, so it goes within ten minutes of the piece going live. Pieces out before this was built are not told.
 */
final class PieceLive
{
    public static function send(): int
    {
        if (! BulkMail::real()) {
            return 0;   // D271: wait for real mail (a piece was marked told while mail was off, and the note was lost)
        }
        $sent = 0;
        $due = Post::published()->with('authors.user')->whereNull('writer_live_told_at')->where('published_at', '>=', now()->subDays(3))->limit(50)->get();
        foreach ($due as $post) {
            foreach ($post->authors as $a) {
                $user = $a->user;
                if ($a->is_desk || ! $user || blank($user->email) || $user->spam_reason) {
                    continue;
                }
                $wa = 'https://wa.me/?text='.rawurlencode('হাতপাখায় আমার নতুন লেখা “'.$post->title.'” পড়ুন: '.$post->url());
                $card = Url::to('/chhobi/'.$post->id);
                if (! $user->mail_opt_out && BulkMail::real()) {
                    $sent += (int) rescue(function () use ($user, $post, $wa, $card) {
                        $user->notify(new \App\Notifications\TemplateMail('piece_live', ['{লেখা}' => $post->title, '{হোয়াটসঅ্যাপ}' => $wa, '{ছবি}' => $card], $post->url()));

                        return true;
                    }, false, true);
                }
                foreach (\App\Models\PushSubscription::where('user_id', $user->id)->get() as $sub) {
                    rescue(fn () => WebPush::send($sub, ['title' => 'আপনার লেখা আজ প্রকাশিত হয়েছে', 'body' => '“'.$post->title.'” এখন হাতপাখায়। বন্ধুদের জানান।', 'url' => $card, 'tag' => 'live-'.$post->id, 'kind' => 'piece-live']), null, false);
                }
            }
            $post->forceFill(['writer_live_told_at' => now()])->saveQuietly();
            AuditLog::record('post.writer_told_live', $post);
        }

        return $sent;
    }
}
