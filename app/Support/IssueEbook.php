<?php

namespace App\Support;

use App\Models\Issue;
use Illuminate\Support\Facades\Storage;

/**
 * Owner (2026-11-12): "a monthly issue e-book, built automatically". Every night each published issue whose pieces
 * changed gets a fresh EPUB (IssueBook::epub: cover, contents, editorial, pieces, writers, back page), kept privately
 * for the owner in /desk → মাসের পত্রিকা. Readers read the issue on the site only (owner, later the same day).
 * An e-book is built only once the issue's editorial is published: every e-book has the editorial.
 */
final class IssueEbook
{
    private const DIR = 'ebooks';

    /** What the e-book is made of: the issue's own fields and every piece (and when it last changed). */
    public static function hash(Issue $issue): string
    {
        $pieces = IssueBook::pieces($issue)->map(fn ($p) => $p->id.'@'.$p->updated_at?->timestamp)->implode(',');

        return sha1($issue->title.'|'.$issue->cover_path.'|'.$issue->editor_note.'|'.$issue->liveEditorial()?->id.'|'.$pieces);
    }

    public static function build(Issue $issue): void
    {
        $tmp = IssueBook::epub($issue);
        $hash = self::hash($issue);
        $path = self::DIR.'/'.$issue->slug.'-'.substr($hash, 0, 8).'.epub';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);
        if ($issue->ebook_path && $issue->ebook_path !== $path) {
            Storage::disk('local')->delete($issue->ebook_path);   // the earlier build
        }
        $issue->forceFill(['ebook_path' => $path, 'ebook_hash' => $hash, 'ebook_built_at' => now()])->save();
    }

    /** Scheduler (night): build what changed (issues with a published editorial only). */
    public static function buildDue(): int
    {
        $n = 0;
        foreach (Issue::where('status', 'published')->get() as $issue) {
            if (IssueBook::pieces($issue)->isEmpty() || ! $issue->liveEditorial() || $issue->ebook_hash === self::hash($issue)) {
                continue;
            }
            rescue(function () use ($issue, &$n) {
                self::build($issue);
                $n++;
            }, null, true);
        }

        return $n;
    }
}
