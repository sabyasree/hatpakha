<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;

/**
 * D217 (owner, asked whether a writer is told of a comment on their piece: "yes, add daily comment email for writers").
 * Once a day, in the Kolkata evening, a writer gets ONE e-mail listing the new published comments on their pieces; no
 * comments, no e-mail. Never their own comment, never to the desk's byline, never to someone who switched letters off.
 */
final class CommentNews
{
    /** At most this many comments are quoted in one e-mail; the rest are counted. */
    public const QUOTED = 5;

    public static function send(): int
    {
        if (! BulkMail::real()) {
            return 0;   // only the live site with real mail; the comments wait
        }
        $new = Comment::with(['post.authors.user'])->where('status', 'approved')->whereNull('writer_told_at')->oldest('id')->limit(400)->get();
        $byWriter = [];
        foreach ($new as $c) {
            foreach ($c->post?->authors ?? [] as $a) {
                if (! $a->is_desk && $a->user && $c->post->status === 'published' && (int) $c->user_id !== (int) $a->user_id) {
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
            $url = $list->pluck('post')->unique('id')->count() === 1 ? $list->first()->post->url().'#comments' : Url::to('/account/writings');
            $sent += (int) rescue(function () use ($user, $list, $quoted, $more, $url) {
                $user->notify(new \App\Notifications\TemplateMail('new_comments', [
                    '{কতটি}' => Bn::num((string) $list->count()),
                    '{মন্তব্য}' => $quoted,
                    '{আরও}' => $more > 0 ? 'আরও '.Bn::num((string) $more).'টি মন্তব্য লেখার পাতায় আছে।' : '',
                ], $url));

                return true;
            }, false, true);
        }
        // Marked whether or not an e-mail went (the desk's pieces, a writer without an account, letters switched off): never held over.
        DB::table('comments')->whereIn('id', $new->pluck('id'))->update(['writer_told_at' => now()]);
        if ($sent) {
            AuditLog::record('comments.writers_told', null, ['writers' => $sent, 'comments' => $new->count()]);
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
