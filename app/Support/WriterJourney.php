<?php

namespace App\Support;

use App\Models\Post;
use App\Models\SentLetter;
use App\Models\User;

/**
 * Writer features (owner, 2026-11-24; docs/RESEARCH-2026-11.md §B): the reply time proved by real decisions, the
 * editor's letter on a declined piece with "what next", and the "the editor opened it" step of the tracker.
 */
final class WriterJourney
{
    /** A declined piece may come back as a new, revised piece after this many days. */
    public const RESUBMIT_AFTER_DAYS = 30;

    /** The public reply-time line needs at least this many decisions in the window. */
    public const MIN_DECISIONS = 5;

    public const WINDOW_DAYS = 90;

    private const CACHE = 'writers.reply_days';

    /**
     * Median days from sending to the editor's decision, over the decisions of the last 90 days (approve, ask for
     * changes, agreement asked, decline: each sets reviewed_at). Null with fewer than five. Only the number is
     * cached (D18), for six hours.
     */
    public static function replyDays(): ?int
    {
        $n = (int) cache()->remember(self::CACHE, now()->addHours(6), fn () => self::computeReplyDays() ?? 0);

        return $n > 0 ? $n : null;
    }

    public static function forget(): void
    {
        cache()->forget(self::CACHE);
    }

    public static function computeReplyDays(): ?int
    {
        $days = Post::whereNotNull('submitted_by')->whereNotNull('submitted_at')->whereNotNull('reviewed_at')
            ->where('reviewed_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->whereColumn('reviewed_at', '>=', 'submitted_at')
            ->get(['submitted_at', 'reviewed_at'])
            ->map(fn (Post $p) => ($p->reviewed_at->getTimestamp() - $p->submitted_at->getTimestamp()) / 86400)
            ->sort()->values();
        if ($days->count() < self::MIN_DECISIONS) {
            return null;
        }
        $mid = intdiv($days->count(), 2);
        $median = $days->count() % 2 ? $days[$mid] : ($days[$mid - 1] + $days[$mid]) / 2;

        return max(1, (int) round($median));
    }

    /** "গত ৯০ দিনে লেখকেরা গড়ে ১২ দিনে উত্তর পেয়েছেন", or null. */
    public static function replyLine(): ?string
    {
        $n = self::replyDays();

        return $n ? 'গত '.Bn::num((string) self::WINDOW_DAYS).' দিনে লেখকেরা গড়ে '.Bn::num((string) $n).' দিনে উত্তর পেয়েছেন' : null;
    }

    /**
     * The letter the writer got when the piece was declined, from the copy kept when it went out (D161): its date,
     * subject and paragraphs as plain text (the e-mail's own buttons, links and footer are left out). Null when no
     * copy was kept (letters before D161, or the mail failed); the page then shows the editor's note alone.
     *
     * @return array{sent_at: \Illuminate\Support\Carbon, subject: string, paras: list<array{kind: string, text: string}>}|null
     */
    public static function declineLetter(Post $post): ?array
    {
        $letter = SentLetter::where('post_id', $post->id)->where('kind', 'decision_declined')->latest('sent_at')->first();
        $writer = $post->submittedBy;
        if (! $letter || ! $writer || ! ($letter->user_id === $writer->id || ($letter->user_id === null && strcasecmp($letter->to_email, (string) $writer->email) === 0))) {
            return null;   // only the writer's own letter
        }
        $paras = self::paragraphs((string) $letter->html);

        return $paras ? ['sent_at' => $letter->sent_at, 'subject' => (string) $letter->subject, 'paras' => $paras] : null;
    }

    /** @return list<array{kind: string, text: string}> the readable parts of a sent letter: headline (h), editor's box (note), paragraphs (p) */
    public static function paragraphs(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }
        $dom = new \DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $x = new \DOMXPath($dom);
        $cell = $x->query('//td[contains(concat(" ", normalize-space(@class), " "), " content-cell ")]')->item(0);
        if (! $cell) {
            return [];
        }
        foreach (iterator_to_array($x->query('.//table[contains(@class, "action") or contains(@class, "subcopy")] | .//a[contains(@class, "hp-btn2")]/ancestor::p[1]', $cell)) as $n) {
            $n->parentNode?->removeChild($n);
        }
        $out = [];
        foreach ($x->query('.//h1 | .//h2 | .//p | .//table[contains(@class, "hp-note")]', $cell) as $n) {
            if ($n->nodeName === 'p' && $x->query('ancestor::table[contains(@class, "hp-note")]', $n)->length) {
                continue;   // inside the editor's box: read with the box
            }
            if ($n->nodeName === 'table') {
                $label = trim((string) $x->query('.//strong', $n)->item(0)?->textContent);
                $text = trim(preg_replace('/[ \t]+/u', ' ', (string) $n->textContent));
                if ($label !== '' && str_starts_with($text, $label)) {
                    $text = $label.': '.trim(mb_substr($text, mb_strlen($label)));
                }
                $out[] = ['kind' => 'note', 'text' => $text];

                continue;
            }
            $text = trim(preg_replace('/[ \t]+/u', ' ', (string) $n->textContent));
            if ($text !== '') {
                $out[] = ['kind' => in_array($n->nodeName, ['h1', 'h2'], true) ? 'h' : 'p', 'text' => $text];
            }
        }

        return $out;
    }

    /** The day a declined piece may come back revised, as a new piece. */
    public static function resubmitFrom(Post $post): ?\Illuminate\Support\Carbon
    {
        return ($post->reviewed_at ?? $post->updated_at)?->copy()->addDays(self::RESUBMIT_AFTER_DAYS);
    }

    /**
     * The first time an editor opens a piece a writer sent and that waits for a decision: kept once, never by the
     * writer themself, never for the desk's own pieces. A plain update, so no history entry or save hook runs.
     */
    public static function markOpened(Post $post, ?User $by): void
    {
        if (! $by || ! $by->hasRole(\App\Enums\Role::Editor) || $post->status !== 'pending' || ! $post->submitted_by
            || $post->submitted_by === $by->id || $post->desk_opened_at) {
            return;
        }
        Post::whereKey($post->id)->whereNull('desk_opened_at')->update(['desk_opened_at' => now()]);
        $post->desk_opened_at = now();
        $post->syncOriginalAttribute('desk_opened_at');
    }

    /**
     * The writer's tracker: পাঠানো → সম্পাদকের কাছে পৌঁছেছে / সম্পাদক পড়ছেন → বাছাই হয়েছে → প্রকাশিত, each with its date
     * when it has one (India time).
     *
     * @return list<array{label: string, date: ?string, done: bool}>
     */
    public static function steps(Post $post): array
    {
        $chosen = in_array($post->status, ['confirm', 'scheduled', 'published'], true);
        $out = $post->isLive();

        return [
            ['label' => 'পাঠানো', 'date' => $post->submitted_at ? Bn::date($post->submitted_at) : null, 'done' => true],
            $post->desk_opened_at || $chosen
                ? ['label' => 'সম্পাদক পড়ছেন', 'date' => $post->desk_opened_at ? Bn::date($post->desk_opened_at) : null, 'done' => true]
                : ['label' => 'সম্পাদকের কাছে পৌঁছেছে', 'date' => null, 'done' => true],
            ['label' => 'বাছাই হয়েছে', 'date' => $chosen && $post->reviewed_at ? Bn::date($post->reviewed_at) : null, 'done' => $chosen],
            $post->status === 'scheduled' && ! $out && $post->published_at
                ? ['label' => 'প্রকাশ হবে', 'date' => Bn::date($post->published_at), 'done' => false]
                : ['label' => 'প্রকাশিত', 'date' => $out ? Bn::date($post->published_at) : null, 'done' => $out],
        ];
    }
}
