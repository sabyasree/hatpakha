<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Support\Review;
use App\Support\Slug;
use Carbon\CarbonInterface;

/**
 * The AI editor's prepared work for one piece (D42), applied only when the owner approves.
 * Copy-edits go through CopyEdit (exact find → replace, server-checked); metadata fills tags,
 * teaser and Google fields. Word-level edits need the writer's OK first ("confirm" status).
 */
final class EditorPackage
{
    /** The latest finished report for a piece, or null. */
    public static function report(Post $post): ?array
    {
        $r = $post->latestAiReview;

        return $r && $r->status === 'done' && is_array($r->report) ? $r->report : null;
    }

    /** @return array<int,array{original:string,replacement:string,kind:string}> */
    public static function edits(Post $post): array
    {
        // The proofreader's careful pass first (D50), then anything extra the editor noticed.
        $all = array_merge((array) ($post->proof['edits'] ?? []), (array) (self::report($post)['edits'] ?? []));
        $seen = [];

        return array_values(array_filter($all, function ($e) use (&$seen) {
            $k = ($e['original'] ?? '').'→'.($e['replacement'] ?? '');

            return isset($seen[$k]) ? false : ($seen[$k] = true);
        }));
    }

    /** Checkbox labels for the approve dialog, e.g. "করিনা → করি না · বানান". */
    public static function editOptions(Post $post): array
    {
        $out = [];
        foreach (self::edits($post) as $i => $e) {
            $out[$i] = $e['original'].' → '.$e['replacement'].' · '.__(CopyEdit::LABELS[$e['kind']] ?? $e['kind']);
        }

        return $out;
    }

    /** By default only edits that never change the writer's words are ticked. */
    public static function safeIndexes(Post $post): array
    {
        return array_keys(array_filter(self::edits($post), fn ($e) => in_array($e['kind'], CopyEdit::SAFE, true)));
    }

    /**
     * The owner's one-click approval of the package.
     *
     * @param array<int,int|string> $chosen indexes of the edits the owner kept
     * @return string "published" or "confirm" (waiting for the writer)
     */
    public static function approve(Post $post, User $owner, CarbonInterface $at, ?string $slug, ?string $note, array $chosen, bool $metadata, bool $applyTitleFix = false): string
    {
        $edits = self::edits($post);
        $chosen = array_values(array_intersect(array_map('intval', $chosen), array_keys($edits)));
        $safe = array_values(array_filter($chosen, fn ($i) => $edits[$i]['kind'] !== 'word'));
        $word = array_values(array_filter($chosen, fn ($i) => $edits[$i]['kind'] === 'word'));

        self::rememberFixes($post, self::applyEdits($post, array_intersect_key($edits, array_flip($safe))));
        if ($titleFix = ($post->proof['title_fix'] ?? '')) {
            if ($applyTitleFix) {
                $post->forceFill(['title' => $titleFix])->save();
            }
        }
        if ($metadata && ($r = self::report($post))) {
            self::applyMetadata($post, $r);
        }

        // Word changes: the writer sees them first (owner's rule, D42). No account (old WordPress piece) = owner decides.
        if ($word && $post->submittedBy) {
            $post->forceFill([
                'status' => 'confirm',
                'pending_edits' => ['edits' => array_values(array_intersect_key($edits, array_flip($word))), 'at' => $at->toIso8601String(), 'slug' => $slug, 'note' => $note, 'by' => $owner->id],
                'review_note' => $note, 'reviewed_at' => now(), 'reviewed_by' => $owner->id,
            ])->save();
            AuditLog::record('submission.confirm_requested', $post, ['by' => $owner->id, 'edits' => count($word)]);
            $post->submittedBy->notify(new \App\Notifications\SubmissionDecision($post, 'confirm'));

            return 'confirm';
        }
        self::applyEdits($post, array_intersect_key($edits, array_flip($word)));
        Review::approve($post, $owner, $at, $slug, $note);

        return 'published';
    }

    /**
     * Before a piece goes back to its writer ("বদল চাই"): put in the small fixes the editor ticked, so the writer
     * revises the corrected text. Never a word change (those need the writer's OK, D42).
     *
     * @param array<int,int|string> $chosen
     */
    public static function applySafeEdits(Post $post, array $chosen): int
    {
        $edits = self::edits($post);
        $keep = array_intersect(array_map('intval', $chosen), self::safeIndexes($post));
        $applied = self::applyEdits($post, array_intersect_key($edits, array_flip($keep)));
        self::rememberFixes($post, $applied);

        return count($applied);
    }

    /** The writer accepts the word changes: apply them and publish as the owner decided. */
    public static function writerAccepts(Post $post): void
    {
        $p = (array) $post->pending_edits;
        self::applyEdits($post, (array) ($p['edits'] ?? []));
        $owner = User::find($p['by'] ?? null) ?? $post->reviewedBy ?? User::where('role', 'admin')->firstOrFail();
        $at = \Illuminate\Support\Carbon::parse($p['at'] ?? now());
        $post->forceFill(['pending_edits' => null])->save();
        Review::approve($post, $owner, $at->isPast() ? now() : $at, $p['slug'] ?? null, $p['note'] ?? null);
    }

    /** The writer objects: back to the owner's queue with the writer's words; nothing changed. */
    public static function writerObjects(Post $post, string $reason): void
    {
        $post->forceFill(['status' => 'pending', 'pending_edits' => null, 'review_note' => 'লেখকের আপত্তি: '.$reason])->save();
        AuditLog::record('submission.writer_objected', $post);
    }

    /** @return list<array> the edits that were found and put in */
    public static function applyEdits(Post $post, array $edits): array
    {
        if (! $edits) {
            return [];
        }
        $edits = array_values($edits);
        $result = CopyEdit::apply((string) $post->body_html, $edits);
        if ($result['applied']) {
            $post->forceFill(['body_html' => $result['html']])->save();   // sanitised + tidied on save
        }
        AuditLog::record('post.copyedits_applied', $post, ['applied' => count($result['applied']), 'not_found' => count($result['missing'])]);

        return array_values(array_intersect_key($edits, array_flip($result['applied'])));
    }

    /** Remember the small fixes, so the writer's e-mail and page can list them (D160). */
    private static function rememberFixes(Post $post, array $applied): void
    {
        // Fixes the editor already put in by hand this round (D192) are listed together with these, once.
        $earlier = array_values(array_filter((array) $post->review_fixes, fn ($e) => ! empty($e['desk'])));
        $all = [];
        foreach (array_merge($earlier, $applied) as $e) {
            $all[$e['original'].'→'.$e['replacement']] = ['original' => $e['original'], 'replacement' => $e['replacement']];
        }
        $post->forceFill(['review_fixes' => $all ? array_values($all) : null])->save();
    }

    /**
     * D192 (owner, 2026-11-14: "how to apply the corrections as an admin, without sending back to the writer?"): the
     * small fixes that are still waiting, i.e. whose words are still in the piece. Spelling, punctuation, spacing and
     * typos only; a change of the writer's words is never put in from here.
     *
     * @return array<int, string> index => "ভুল → ঠিক · কী ধরনের"
     */
    public static function waitingFixes(Post $post): array
    {
        return array_intersect_key(self::openOptions($post), array_flip(self::safeIndexes($post)));
    }

    /** @var array<string, list<int>> which corrections can still go in, per text (one request) */
    private static array $open = [];

    /**
     * The corrections that are still to do: those whose words are still in the piece, tried exactly the way they are
     * applied. One already put in (by "সংশোধন বসান", or by hand) is no longer offered anywhere: not in "মনোনীত", not in
     * "বদল চাই" (owner, 2026-11-14: "I already added the corrections; on approve the same corrections show again").
     *
     * @return list<int> indexes into edits()
     */
    public static function openIndexes(Post $post): array
    {
        $edits = self::edits($post);
        $key = $post->id.':'.md5((string) $post->body_html).':'.count($edits);

        return self::$open[$key] ??= array_values(array_filter(array_keys($edits), fn ($i) => CopyEdit::apply((string) $post->body_html, [$edits[$i]])['applied'] !== []));
    }

    /** @return array<int, string> index => "ভুল → ঠিক · কী ধরনের", only those still to do */
    public static function openOptions(Post $post): array
    {
        return array_intersect_key(self::editOptions($post), array_flip(self::openIndexes($post)));
    }

    /** The small fixes still to do (ticked by default in the dialogs). @return list<int> */
    public static function openSafe(Post $post): array
    {
        return array_values(array_intersect(self::safeIndexes($post), self::openIndexes($post)));
    }

    /** The changes of the writer's words the AI suggested: shown, never applied without the writer. @return list<string> */
    public static function wordChanges(Post $post): array
    {
        return array_values(array_diff_key(self::openOptions($post), array_flip(self::safeIndexes($post))));
    }

    /**
     * Put the ticked small fixes into the piece now. The piece stays where it is (no e-mail, no change of status); the
     * fixes are remembered, so the writer is told of them in the next letter about this piece. Returns how many went in.
     */
    public static function applyNow(Post $post, array $chosen): int
    {
        $edits = self::edits($post);
        $keep = array_intersect(array_map('intval', $chosen), array_keys(self::waitingFixes($post)));
        $applied = self::applyEdits($post, array_intersect_key($edits, array_flip($keep)));
        if ($applied) {
            $mine = array_map(fn ($e) => ['original' => $e['original'], 'replacement' => $e['replacement'], 'desk' => true], $applied);
            $post->forceFill(['review_fixes' => array_values(array_merge(array_filter((array) $post->review_fixes, fn ($e) => ! empty($e['desk'])), $mine))])->save();
        }

        return count($applied);
    }

    /** "ভুল → ঠিক" lines for the ticked small fixes, for the e-mail preview before anything is applied. */
    /**
     * The ticked small fixes as the e-mail lists them. Returned as pairs, never as joined text: a fix whose words hold a
     * line break (a poem's lines) broke the preview when the text was split again (owner, 2026-11-14: "error when
     * clicking request changes").
     *
     * @return list<array{original: string, replacement: string}>
     */
    public static function fixesPreview(Post $post, array $chosen): array
    {
        $edits = self::edits($post);
        $keep = array_intersect(array_map('intval', $chosen), self::safeIndexes($post));

        return collect($keep)->map(fn ($i) => ['original' => (string) $edits[$i]['original'], 'replacement' => (string) $edits[$i]['replacement']])->values()->all();
    }

    public static function applyMetadata(Post $post, array $r): void
    {
        $post->forceFill(array_filter([
            'seo_title' => mb_substr((string) ($r['seo_title'] ?? ''), 0, 70) ?: null,
            'seo_description' => mb_substr((string) ($r['seo_description'] ?? ''), 0, 160) ?: null,
            'excerpt' => $post->excerpt ?: (($r['excerpt'] ?? '') ?: null),
        ]))->save();
        $ids = [];
        foreach (array_slice((array) ($r['tags'] ?? []), 0, 5) as $name) {
            $name = trim(strip_tags((string) $name));
            if ($name === '' || mb_strlen($name) > 40) {
                continue;
            }
            $tag = Tag::where('name', $name)->first() ?? Tag::firstOrCreate(['slug' => Slug::normalize(str_replace(' ', '-', $name))], ['name' => $name]);
            $ids[] = $tag->id;
        }
        if ($ids) {
            $post->tags()->syncWithoutDetaching($ids);
        }
    }
}
