<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Post;
use App\Models\PushAnnouncement;
use App\Models\User;

/**
 * When an issue goes live (owner, 2026-11-12): readers' phones hear "নতুন সংখ্যা", and each writer in it hears
 * "আপনার লেখা এই সংখ্যায়". Both are prepared here and sent only when the owner presses the button in
 * /desk → মাসের পত্রিকা (bulk messages need a person's click, D42), and each only once per issue.
 */
final class IssueNews
{
    /** The issue's writers who can be told: a writer's own live piece (not the desk's, not the editorial), with an account. */
    public static function writers(Issue $issue)
    {
        $posts = Post::published()->certifiable()->with('authors.user')->where('issue_id', $issue->id)
            ->when($issue->liveEditorial(), fn ($q, $e) => $q->whereKeyNot($e->id))->orderBy('published_at')->get();
        $out = collect();
        foreach ($posts as $p) {
            foreach ($p->authors->where('is_desk', false) as $a) {
                if ($a->user && ! $out->has($a->user->id)) {
                    $out->put($a->user->id, ['user' => $a->user, 'name' => $a->publicName(), 'post' => $p]);
                }
            }
        }

        return $out->values();
    }

    public static function announced(Issue $issue): ?string
    {
        return $issue->ai_plan['announced_at'] ?? null;
    }

    public static function writersTold(Issue $issue): ?string
    {
        return $issue->ai_plan['writers_told_at'] ?? null;
    }

    /** The phone notification, as it will read. */
    public static function message(Issue $issue): array
    {
        $titles = Post::published()->where('issue_id', $issue->id)->orderBy('published_at')->limit(2)->pluck('title')->implode(', ');
        $n = Post::published()->where('issue_id', $issue->id)->count();

        return [
            'title' => mb_substr('নতুন সংখ্যা: '.$issue->title, 0, 80),
            'body' => mb_substr(Bn::num((string) $n).'টি লেখা'.($titles ? ': '.$titles.'…' : ''), 0, 200),
            'url' => $issue->url(),
        ];
    }

    /** Queue the notification to every phone that has notifications on (sent by the usual announcement sender). */
    public static function announce(Issue $issue, ?User $by): ?PushAnnouncement
    {
        if ($issue->status !== 'published' || self::announced($issue) || ! WebPush::configured()) {
            return null;
        }
        $m = self::message($issue);
        $a = PushAnnouncement::create($m + ['audience' => 'all', 'recipients' => PushNotifier::audience('all')->count(), 'created_by' => $by?->id]);
        self::mark($issue, 'announced_at');
        AuditLog::record('issue.announced', $issue, ['announcement' => $a->id, 'recipients' => $a->recipients]);

        return $a;
    }

    /** Tell each writer in the issue, once: an e-mail (editable in ই-মেলের নমুনা) and their phones. Returns how many. */
    public static function tellWriters(Issue $issue, ?User $by): int
    {
        if ($issue->status !== 'published' || self::writersTold($issue)) {
            return 0;
        }
        $n = 0;
        foreach (self::writers($issue) as $w) {
            $vars = ['{নাম}' => $w['name'], '{লেখা}' => $w['post']->title, '{সংখ্যা}' => $issue->title];
            if ($w['user']->email_verified_at && NewsletterSender::realMail()) {
                rescue(fn () => $w['user']->notify(new \App\Notifications\TemplateMail('issue_piece', $vars, $issue->url())), null, false);
            }
            rescue(fn () => WebPush::toUser($w['user'], ['title' => 'আপনার লেখা '.$issue->title.'-য়', 'body' => '“'.$w['post']->title.'” এই মাসের সংখ্যায় আছে।',
                'url' => $issue->url(), 'tag' => 'issue-'.$issue->id, 'kind' => 'announce']), 0, false);
            $n++;
        }
        self::mark($issue, 'writers_told_at');
        AuditLog::record('issue.writers_told', $issue, ['writers' => $n, 'by' => $by?->id]);

        return $n;
    }

    private static function mark(Issue $issue, string $key): void
    {
        $plan = (array) $issue->ai_plan;
        $plan[$key] = now()->toIso8601String();
        $issue->forceFill(['ai_plan' => $plan])->save();
    }
}
