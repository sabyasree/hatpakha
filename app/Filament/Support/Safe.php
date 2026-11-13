<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;
use Throwable;

/**
 * D191: a preview, a report or a hint inside a desk dialog must never stop the dialog itself from opening (owner,
 * 2026-11-14: the e-mail preview broke "request changes"). These draw the part at once; if it cannot be drawn they
 * show one plain line instead, and the error is reported (so it reaches /desk → সাইটের ত্রুটি).
 */
final class Safe
{
    /** What could not be drawn in this request (tests check it stays empty). @var list<string> */
    public static array $failed = [];

    /** A Blade view, drawn now. */
    public static function view(string $view, array $data = []): HtmlString
    {
        return self::html(fn () => view($view, $data)->render());
    }

    /** Any piece of HTML made by the callback. */
    public static function html(callable $make): HtmlString
    {
        try {
            return new HtmlString((string) $make());
        } catch (Throwable $e) {
            self::$failed[] = $e::class.': '.mb_substr($e->getMessage(), 0, 200);
            \App\Support\SiteErrors::record($e);
            try {
                report($e);
            } catch (Throwable) {
                // a test harness may rethrow what is reported; a visitor never sees that
            }

            return new HtmlString('<p style="opacity:.8">'.e(__('এই অংশটি এখন দেখানো গেল না। বাকি কাজ করা যাবে; ত্রুটিটি নথিভুক্ত হয়েছে।')).'</p>');
        }
    }
}
