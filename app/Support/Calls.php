<?php

namespace App\Support;

use App\Models\Contest;
use App\Models\Issue;
use App\Models\ReaderPrompt;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * লেখার ডাক (owner, 2026-11-24; research §B4): what writers are invited to now, from real data only. Coming issues
 * (approved or planned by the desk, never the AI's unapproved proposals) with their day, and a last date only where
 * one was really set (the ফিরে আসা সংখ্যা, D188); this week's পাঠকের কলম; open contests; and the editor's wish list,
 * written by the owner in /desk → প্রতিযোগিতা → "সম্পাদক যা খুঁজছেন".
 */
final class Calls
{
    public const WISHLIST = 'calls.wishlist';

    public const WISHLIST_MAX = 2000;

    /** @return list<string> */
    public static function wishlist(): array
    {
        return array_values(array_filter(array_map(fn ($l) => trim(ltrim(trim($l), '-•*·')), preg_split('/\R/u', self::wishlistText()))));
    }

    public static function wishlistText(): string
    {
        return (string) Setting::get(self::WISHLIST, '');
    }

    public static function saveWishlist(string $text): void
    {
        $text = trim(mb_substr(strip_tags($text), 0, self::WISHLIST_MAX));
        Setting::put(self::WISHLIST, $text === '' ? null : $text);
    }

    /**
     * Coming issues, soonest first: [issue, day it goes out (or null), last day to send (or null), what to write about (or null)].
     *
     * @return Collection<int, array{issue: Issue, at: ?Carbon, last: ?Carbon, theme: ?string}>
     */
    public static function issues(): Collection
    {
        $tz = 'Asia/Kolkata';

        return Issue::whereIn('status', ['planning', 'published'])
            ->where(fn ($q) => $q->where('published_at', '>', now())->orWhere(fn ($q) => $q->where('slug', WriterInvites::ISSUE_SLUG)->where('status', 'planning')))
            ->get()
            ->map(function (Issue $i) use ($tz) {
                $return = $i->slug === WriterInvites::ISSUE_SLUG;
                if ($return && ! WriterInvites::open() && ! $i->published_at?->isFuture()) {
                    return null;
                }

                return [
                    'issue' => $i,
                    'at' => $i->published_at ?? ($return ? WriterInvites::issueAt() : null),
                    'last' => $return && WriterInvites::open() ? Carbon::parse(WriterInvites::LAST_DATE, $tz) : null,
                    'theme' => $return ? WriterInvites::THEME_NOTE : null,
                ];
            })
            ->filter()
            ->sortBy(fn ($row) => $row['at']?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    public static function kolom(): ?ReaderPrompt
    {
        $p = ReaderPrompt::current();

        return $p && $p->isOpen() ? $p : null;
    }

    public static function contests(): Collection
    {
        return Contest::openNow()->with('section')->orderByRaw('ends_at is null')->orderBy('ends_at')->get();
    }
}
