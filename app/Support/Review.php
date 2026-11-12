<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use App\Notifications\SubmissionDecision;
use Carbon\CarbonInterface;

/**
 * Phase 3c: an editor's decision on a submission. A human always decides (CLAUDE.md §2).
 * Kind message templates are starting points; the editor can change every word.
 */
final class Review
{
    public const CHANGE_TEMPLATES = [
        'বানান' => 'লেখাটি আমাদের ভালো লেগেছে। প্রকাশের আগে বানান আর যতিচিহ্নগুলো একবার দেখে নিলে খুব ভালো হয়।',
        'দৈর্ঘ্য' => 'লেখাটির ভাবনা সুন্দর। কয়েকটি জায়গা একটু ছোট করলে লেখাটি আরও জোরালো হবে বলে মনে হয়।',
        'শেষ' => 'লেখাটি পড়তে ভালো লাগল। শেষটা আর একটু ভেবে দেখবেন? পাঠকের মনে যেন রেশ থেকে যায়।',
    ];

    public const REJECT_TEMPLATES = [
        'এবার নয়' => 'আপনার লেখাটি এবার মনোনীত হয়নি। এটা আপনার লেখার মূল্যের বিচার নয়; এই সময়ের পরিকল্পনার সঙ্গে মেলেনি। নতুন লেখা পাঠাতে থাকুন, আমরা পড়ার অপেক্ষায় থাকব।',
        'আগে প্রকাশিত' => 'লেখাটি অন্য কোথাও আগে প্রকাশিত হয়েছে বলে এবার আমরা এটি নিতে পারছি না। নতুন, অপ্রকাশিত লেখা পাঠালে খুশি হব।',
        'নিয়ম' => 'লেখাটি আমাদের লেখা পাঠানোর নিয়মের সঙ্গে মেলেনি। নিয়মগুলো একবার দেখে নিয়ে আবার লেখা পাঠাতে পারেন।',
    ];

    /**
     * The whole message for "বদল চাই", ready to edit: the AI's note to the writer and then its numbered suggestions
     * (owner, 2026-11-12: "everything is not there"). Null when there is no AI report.
     */
    public static function changesMessage(Post $post): ?string
    {
        $r = \App\Ai\EditorPackage::report($post);
        if (! $r) {
            return null;
        }
        $note = trim((string) ($r['note_to_writer'] ?? ''));
        $tips = array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) ($r['suggestions'] ?? []))));
        if ($tips) {
            $note .= ($note === '' ? '' : "\n\n").'যে কথাগুলো ভেবে দেখতে বলছি:';
            foreach ($tips as $i => $tip) {
                $note .= "\n".Bn::num((string) ($i + 1)).'. '.$tip;
            }
        }

        return $note === '' ? null : $note;
    }

    /** Approve: publish now or on a date; give the post its readable Latin URL (D14). */
    public static function approve(Post $post, User $editor, ?CarbonInterface $at = null, ?string $slug = null, ?string $note = null): void
    {
        $at ??= now();
        $post->forceFill([
            'status' => $at->isFuture() ? 'scheduled' : 'published',
            'published_at' => $at,
            'slug' => self::uniqueSlug($slug ?: Transliterate::slug($post->title) ?: 'lekha-'.$post->id, $post->id),
            'review_note' => $note,
            'reviewed_at' => now(),
            'reviewed_by' => $editor->id,
        ])->save();
        $post->authors()->each(fn ($a) => $a->refreshCount());
        \App\Ai\EditorJobs::welcome($post);   // first published piece → a welcome note for the owner to send (D48)
        self::finish($post, $editor, 'approved');
        // The SEO expert finishes the search & share details once the owner has approved (D46).
        $id = $post->id;
        dispatch(fn () => ($p = Post::find($id)) ? \App\Ai\SeoExpert::run($p) : null)->afterResponse();
    }

    public static function requestChanges(Post $post, User $editor, string $note): void
    {
        $post->forceFill(['status' => 'changes', 'review_note' => $note, 'reviewed_at' => now(), 'reviewed_by' => $editor->id])->save();
        self::finish($post, $editor, 'changes');
    }

    public static function reject(Post $post, User $editor, string $note): void
    {
        $post->forceFill(['status' => 'rejected', 'review_note' => $note, 'reviewed_at' => now(), 'reviewed_by' => $editor->id])->save();
        self::finish($post, $editor, 'rejected');
    }

    public static function uniqueSlug(string $slug, int $ignoreId): string
    {
        $slug = Slug::normalize($slug) ?: 'lekha';
        $candidate = $slug;
        for ($i = 2; Post::withTrashed()->where('slug', $candidate)->whereKeyNot($ignoreId)->exists()
            || \App\Models\Page::where('slug', $candidate)->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
    }

    private static function finish(Post $post, User $editor, string $decision): void
    {
        AuditLog::record("submission.{$decision}", $post, ['by' => $editor->id]);
        // One transactional email to the writer about their own piece (not a bulk mailing).
        if ($writer = $post->submittedBy) {
            $writer->notify(new SubmissionDecision($post, $decision));
        }
    }
}
