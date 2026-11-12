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

        self::applyEdits($post, array_intersect_key($edits, array_flip($safe)));
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
        self::applyEdits($post, array_intersect_key($edits, array_flip($keep)));

        return count($keep);
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

    public static function applyEdits(Post $post, array $edits): void
    {
        if (! $edits) {
            return;
        }
        $result = CopyEdit::apply((string) $post->body_html, array_values($edits));
        if ($result['applied']) {
            $post->forceFill(['body_html' => $result['html']])->save();   // sanitised + tidied on save
        }
        AuditLog::record('post.copyedits_applied', $post, ['applied' => count($result['applied']), 'not_found' => count($result['missing'])]);
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
