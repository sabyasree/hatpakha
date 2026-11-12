<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\PushAnnouncement;
use App\Models\User;

/**
 * বর্ষসেরা is news (owner, 2026-11-12), like a new issue (IssueNews): readers' phones hear about it, and each writer
 * in it hears "আপনার লেখা বর্ষসেরায়". Both only on the owner's click in the collection's desk page, each once.
 */
final class CollectionNews
{
    /** Writers in the collection with an account (a writer's own piece; never the desk's). */
    public static function writers(Collection $c)
    {
        $out = collect();
        foreach ($c->posts() as $p) {
            if (! \App\Models\Post::whereKey($p->id)->certifiable()->exists()) {
                continue;
            }
            foreach ($p->authors->where('is_desk', false) as $a) {
                if ($a->user && ! $out->has($a->user->id)) {
                    $out->put($a->user->id, ['user' => $a->user, 'name' => $a->publicName(), 'post' => $p]);
                }
            }
        }

        return $out->values();
    }

    public static function done(Collection $c, string $what): ?string
    {
        return $c->news[$what] ?? null;
    }

    public static function announce(Collection $c, ?User $by): ?PushAnnouncement
    {
        if ($c->status !== 'approved' || ! $c->slug || self::done($c, 'announced_at') || ! WebPush::configured()) {
            return null;
        }
        $a = PushAnnouncement::create([
            'title' => mb_substr($c->title, 0, 80), 'body' => mb_substr(Bn::num((string) count((array) $c->post_ids)).'টি বাছাই করা লেখা, পাতা উল্টে পড়ুন।', 0, 200),
            'url' => $c->url(), 'audience' => 'all', 'recipients' => PushNotifier::audience('all')->count(), 'created_by' => $by?->id,
        ]);
        self::mark($c, 'announced_at');
        AuditLog::record('collection.announced', null, ['collection' => $c->id, 'recipients' => $a->recipients]);

        return $a;
    }

    public static function tellWriters(Collection $c, ?User $by): int
    {
        if ($c->status !== 'approved' || ! $c->slug || self::done($c, 'writers_told_at')) {
            return 0;
        }
        $n = 0;
        foreach (self::writers($c) as $w) {
            $vars = ['{নাম}' => $w['name'], '{লেখা}' => $w['post']->title, '{সংকলন}' => $c->title];
            if ($w['user']->email_verified_at && NewsletterSender::realMail()) {
                rescue(fn () => $w['user']->notify(new \App\Notifications\TemplateMail('anthology_piece', $vars, $c->url())), null, false);
            }
            rescue(fn () => WebPush::toUser($w['user'], ['title' => 'আপনার লেখা '.$c->title.'-য়', 'body' => '“'.$w['post']->title.'” বাছাই হয়েছে।',
                'url' => $c->url(), 'tag' => 'collection-'.$c->id, 'kind' => 'announce']), 0, false);
            $n++;
        }
        self::mark($c, 'writers_told_at');
        AuditLog::record('collection.writers_told', null, ['collection' => $c->id, 'writers' => $n, 'by' => $by?->id]);

        return $n;
    }

    private static function mark(Collection $c, string $key): void
    {
        $news = (array) $c->news;
        $news[$key] = now()->toIso8601String();
        $c->forceFill(['news' => $news])->save();
    }
}
