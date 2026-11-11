<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Owner (2026-11-12): "a monthly issue e-book, built automatically; show me the final version to approve".
 * Every night each published issue whose pieces changed gets a fresh EPUB (IssueBook::epub: cover, contents,
 * editorial, pieces, writers, back page). It waits in /desk → ই-বুক; readers get it only after the owner approves it,
 * and a newer build replaces the approved one only when approved again.
 */
final class IssueEbook
{
    private const DIR = 'ebooks';

    /** What the e-book is made of: the issue's own fields and every piece (and when it last changed). */
    public static function hash(Issue $issue): string
    {
        $pieces = IssueBook::pieces($issue)->map(fn ($p) => $p->id.'@'.$p->updated_at?->timestamp)->implode(',');

        return sha1($issue->title.'|'.$issue->cover_path.'|'.$issue->editor_note.'|'.$issue->editorial_post_id.'|'.$pieces);
    }

    public static function build(Issue $issue): void
    {
        $tmp = IssueBook::epub($issue);
        $hash = self::hash($issue);
        $path = self::DIR.'/'.$issue->slug.'-'.substr($hash, 0, 8).'.epub';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);
        if ($issue->ebook_path && $issue->ebook_path !== $issue->ebook_public_path && $issue->ebook_path !== $path) {
            Storage::disk('local')->delete($issue->ebook_path);   // an earlier build nobody approved
        }
        $issue->forceFill(['ebook_path' => $path, 'ebook_hash' => $hash, 'ebook_built_at' => now()])->save();
    }

    /** Scheduler (night): build what changed, then tell the owner there is something to approve. */
    public static function buildDue(): int
    {
        $n = 0;
        foreach (Issue::where('status', 'published')->get() as $issue) {
            if (IssueBook::pieces($issue)->isEmpty() || $issue->ebook_hash === self::hash($issue)) {
                continue;
            }
            rescue(function () use ($issue, &$n) {
                self::build($issue);
                $n++;
            }, null, true);
        }
        if ($n > 0) {
            foreach (User::where('role', \App\Enums\Role::Admin->value)->get() as $admin) {
                rescue(fn () => WebPush::toUser($admin, ['title' => 'নতুন ই-বুক তৈরি', 'body' => 'দেখে অনুমোদন দিলে পাঠকেরা নামাতে পারবেন।', 'url' => url('/desk/ebooks'), 'tag' => 'ebooks', 'kind' => 'team']), 0, false);
            }
        }

        return $n;
    }

    public static function waiting(Issue $issue): bool
    {
        return $issue->ebook_path !== null && $issue->ebook_path !== $issue->ebook_public_path;
    }

    public static function approved(Issue $issue): bool
    {
        return $issue->ebook_public_path !== null && Storage::disk('local')->exists($issue->ebook_public_path);
    }

    public static function approve(Issue $issue, ?User $by): void
    {
        abort_unless($issue->ebook_path && Storage::disk('local')->exists($issue->ebook_path), 404);
        $old = $issue->ebook_public_path;
        $issue->forceFill(['ebook_public_path' => $issue->ebook_path, 'ebook_approved_at' => now(), 'ebook_approved_by' => $by?->id])->save();
        if ($old && $old !== $issue->ebook_path) {
            Storage::disk('local')->delete($old);
        }
        \App\Models\AuditLog::record('ebook.approved', null, ['issue' => $issue->id]);
    }

    public static function withdraw(Issue $issue): void
    {
        $issue->forceFill(['ebook_public_path' => null, 'ebook_approved_at' => null, 'ebook_approved_by' => null])->save();
        \App\Models\AuditLog::record('ebook.withdrawn', null, ['issue' => $issue->id]);
    }
}
