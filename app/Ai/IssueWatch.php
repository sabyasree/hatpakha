<?php

namespace App\Ai;

use App\Enums\Role;
use App\Models\Issue;
use App\Models\Post;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Owner (2026-11-12): "an editorial every month, so I never miss it; every e-book has the editorial; and a proper
 * cover every month". The monthly plan, checked every day:
 *
 *   20th        the AI editor proposes next month's issue, drafts the editorial, the designer paints the cover (IssuePlanner)
 *   every day   anything missing is made again (the editorial draft, the cover), at most once a day each
 *   25th, 28th  a phone reminder if the editorial is not yet published or the cover not yet chosen
 *   live issue  a reminder every day until its editorial is published; the e-book is built only with the editorial in it
 *
 * Nothing is published by the AI (D42): the owner edits and publishes the editorial and chooses the cover.
 */
final class IssueWatch
{
    /** This month's issue and next month's (once it exists). */
    public static function current(): Collection
    {
        $now = now('Asia/Kolkata');

        return Issue::whereIn('slug', [$now->format('Y-m'), $now->copy()->addMonthNoOverflow()->format('Y-m')])->orderBy('slug')->get();
    }

    /** @return array{editorial: string, cover: string, post: ?Post, ready: bool} editorial: missing|draft|live; cover: missing|proposed|set */
    public static function status(Issue $issue): array
    {
        $post = $issue->editorialPost();
        $editorial = ! $post ? 'missing' : ($post->isLive() ? 'live' : 'draft');
        $cover = $issue->cover_path ? 'set' : ($issue->proposed_cover_path ? 'proposed' : 'missing');

        return ['editorial' => $editorial, 'cover' => $cover, 'post' => $post, 'ready' => $editorial === 'live' && $cover === 'set'];
    }

    /** What still needs the owner, in plain words (for the desk and the reminder). */
    public static function needs(Issue $issue): array
    {
        $s = self::status($issue);

        return array_values(array_filter([
            match ($s['editorial']) {
                'missing' => __('সম্পাদকীয় এখনও লেখা হয়নি'),
                'draft' => __('সম্পাদকীয়র খসড়া দেখে প্রকাশ করুন'),
                default => null,
            },
            match ($s['cover']) {
                'missing' => __('প্রচ্ছদ এখনও আঁকা হয়নি'),
                'proposed' => __('ডিজাইনারের প্রচ্ছদ দেখে বেছে নিন'),
                default => null,
            },
            // Owner (2026-11-12): a live issue is news for readers and for its writers.
            $issue->status === 'published' && ((! \App\Support\IssueNews::announced($issue) && \App\Support\WebPush::configured())
                || (! \App\Support\IssueNews::writersTold($issue) && \App\Support\IssueNews::writers($issue)->isNotEmpty()))
                ? __('পাঠক আর লেখকদের খবর দিন') : null,
        ]));
    }

    /** Daily (AI shift): make what is missing, attach the editorial to its issue, remind the owner. */
    public static function run(): array
    {
        $owner = User::where('role', Role::Admin->value)->orderBy('id')->first();
        $done = ['editorials' => 0, 'covers' => 0, 'reminders' => 0];
        if (! $owner) {
            return $done;
        }
        $now = now('Asia/Kolkata');
        foreach (self::current() as $issue) {
            $s = self::status($issue);
            if ($s['editorial'] === 'missing' && ! Claude::blocked() && self::once('editorial', $issue)) {
                $done['editorials'] += EditorialWriter::draft($issue, $owner) ? 1 : 0;
            }
            if ($s['cover'] === 'missing' && ! Illustrator::blocked() && self::once('cover', $issue)) {
                $done['covers'] += Illustrator::forIssue($issue) === null ? 1 : 0;
            }
            // A live issue's editorial belongs to it (the magazine and the e-book read the issue's pieces).
            if ($issue->status === 'published' && $s['post'] && $s['post']->issue_id !== $issue->id) {
                Post::whereKey($s['post']->id)->update(['issue_id' => $issue->id]);
            }
            $needs = self::needs($issue->fresh());
            $isNext = $issue->slug !== $now->format('Y-m');
            $due = $isNext ? in_array($now->day, [25, 28], true) : $issue->status === 'published';
            if ($needs && $due && self::once('remind', $issue)) {
                foreach (User::where('role', Role::Admin->value)->get() as $admin) {
                    rescue(fn () => WebPush::toUser($admin, ['title' => $issue->title.': '.__('আপনার জন্য অপেক্ষায়'), 'body' => implode(' · ', $needs),
                        'url' => url('/desk/ebooks'), 'tag' => 'issue-'.$issue->id, 'kind' => 'team']), 0, false);
                }
                $done['reminders']++;
            }
        }

        return $done;
    }

    /** At most once a day per issue and kind (an AI failure is not retried in a loop). */
    private static function once(string $kind, Issue $issue): bool
    {
        return Cache::add('issue-watch:'.$kind.':'.$issue->id.':'.now('Asia/Kolkata')->toDateString(), 1, now()->addDay());
    }
}
