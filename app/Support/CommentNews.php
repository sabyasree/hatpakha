<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;

/**
 * D217 (owner, asked whether a writer is told of a comment on their piece: "yes, add daily comment email for writers").
 * A writer gets an e-mail listing the new published comments on their pieces; no comments, no e-mail. Never their own
 * comment, never to the desk's byline, never to someone who switched letters off.
 * D252 (owner: "when a comment is given, the writer will get to know by e-mail… the link brings them straight to that
 * comment"): it now runs every ten minutes, so a comment is told within about ten minutes; comments that come together
 * go in one e-mail, and a writer gets at most one such e-mail an hour (UX review: a busy piece must not flood them).
 * A single comment's link opens the piece at that comment (#comment-ID). Replies count too.
 * And a reader whose comment gets a published reply is told (replies()), if they wrote it signed in.
 */
final class CommentNews
{
    /** At most this many comments are quoted in one e-mail; the rest are counted. */
    public const QUOTED = 5;

    /** A writer gets at most one comment e-mail in this many minutes; the rest wait for the next one. */
    public const GAP_MINUTES = 60;

    public static function send(): int
    {
        if (! BulkMail::real()) {
            return 0;   // only the live site with real mail; the comments wait
        }
        $new = Comment::with(['post.authors.user', 'parent'])->where('status', 'approved')->whereNull('writer_told_at')->oldest('id')->limit(400)->get();
        // D271 (audit): a comment waits whole. If any writer of its piece was told within the hour, none of them is told
        // this round, so the comment is marked once, after all its writers had it (it went round co-writers hourly before).
        $resting = fn ($userId) => \Illuminate\Support\Facades\Cache::store('database')->has('hp.cnews.'.$userId);
        $mailable = fn ($a) => ! $a->is_desk && $a->user && filled($a->user->email) && ! $a->user->mail_opt_out && ! $a->user->spam_reason;
        $held = $new->filter(fn ($c) => collect($c->post?->authors ?? [])->contains(fn ($a) => $mailable($a) && $resting($a->user_id)))->pluck('id')->all();
        $byWriter = [];
        foreach ($new as $c) {
            if (in_array($c->id, $held, true)) {
                continue;
            }
            foreach ($c->post?->authors ?? [] as $a) {
                // a reply to the writer's own comment reaches them as "a reply to you" (replies()), not twice
                $replyToThem = $c->parent && (int) $c->parent->user_id === (int) $a->user_id;
                if (! $a->is_desk && $a->user && $c->post->status === 'published' && (int) $c->user_id !== (int) $a->user_id && ! $replyToThem) {
                    $byWriter[$a->user_id]['user'] = $a->user;
                    $byWriter[$a->user_id]['comments'][] = $c;
                }
            }
        }
        $sent = 0;
        foreach ($byWriter as $w) {
            $user = $w['user'];
            if (blank($user->email) || $user->mail_opt_out || $user->spam_reason) {
                continue;
            }
            $list = collect($w['comments']);
            $quoted = $list->take(self::QUOTED)->map(fn (Comment $c) => '> “'.self::clean($c->body, 220).'”'."\n".'> — '.self::clean($c->author_name, 60).', “'.self::clean($c->post->title, 80).'”-এ')->implode("\n>\n");
            $more = $list->count() - self::QUOTED;
            $url = $list->count() === 1 ? $list->first()->post->url().'#comment-'.$list->first()->id
                : ($list->pluck('post')->unique('id')->count() === 1 ? $list->first()->post->url().'#comments' : Url::to('/account/writings'));
            $sent += (int) rescue(function () use ($user, $list, $quoted, $more, $url) {
                $user->notify(new \App\Notifications\TemplateMail('new_comments', [
                    '{কতটি}' => Bn::num((string) $list->count()),
                    '{মন্তব্য}' => $quoted,
                    '{আরও}' => $more > 0 ? 'আরও '.Bn::num((string) $more).'টি মন্তব্য লেখার পাতায় আছে।' : '',
                ], $url));

                \Illuminate\Support\Facades\Cache::store('database')->put('hp.cnews.'.$user->id, true, now()->addMinutes(self::GAP_MINUTES));   // survives a release (D271)

                return true;
            }, false, true);
        }
        // Marked whether or not an e-mail went (the desk's pieces, a writer without an account, letters switched off): never held over.
        DB::table('comments')->whereIn('id', $new->pluck('id')->diff($held))->update(['writer_told_at' => now()]);
        if ($sent) {
            AuditLog::record('comments.writers_told', null, ['writers' => $sent, 'comments' => $new->count()]);
        }

        return $sent;
    }

    /**
     * D252: a reply was published under someone's comment: the person who wrote that comment is told, once, if they
     * wrote it signed in with a checked address and letters on. Never about their own reply.
     */
    public static function replies(): int
    {
        if (! BulkMail::real()) {
            return 0;
        }
        $new = Comment::with(['post', 'parent.user', 'user'])->whereNotNull('parent_id')->where('status', 'approved')->whereNull('reply_told_at')->oldest('id')->limit(200)->get();
        $sent = 0;
        foreach ($new as $r) {
            $to = $r->parent?->user;
            if ($to && $r->post?->status === 'published' && (int) $to->id !== (int) $r->user_id && filled($to->email) && ! $to->mail_opt_out && ! $to->spam_reason && $to->hasVerifiedEmail()) {
                $byWriter = $r->user_id && $r->post->authors()->where('user_id', $r->user_id)->exists();
                $sent += (int) rescue(function () use ($to, $r, $byWriter) {
                    $to->notify(new \App\Notifications\TemplateMail('comment_reply', [
                        '{লেখা}' => self::clean($r->post->title, 120),
                        '{উত্তরদাতা}' => self::clean($r->author_name, 60).($byWriter ? ' (লেখক)' : ''),
                        '{উত্তর}' => '> “'.self::clean($r->body, 400).'”',
                        '{আপনার_মন্তব্য}' => '> “'.self::clean($r->parent->body, 200).'”',
                    ], $r->post->url().'#comment-'.$r->id));

                    return true;
                }, false, true);
            }
        }
        DB::table('comments')->whereIn('id', $new->pluck('id'))->update(['reply_told_at' => now()]);
        if ($sent) {
            AuditLog::record('comments.replies_told', null, ['readers' => $sent]);
        }

        return $sent;
    }

    private static function clean(string $s, int $max): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
        $s = str_replace(['{', '}', '[', ']', '<', '>', '`', '*', '_', '#'], '', $s);   // a reader's words are quoted, never formatted

        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)).'…' : $s;
    }
}
