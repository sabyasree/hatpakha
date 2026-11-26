<?php

namespace App\Ai;

use App\Models\Author;
use App\Models\Issue;
use App\Models\Post;
use App\Models\Section;
use App\Models\User;

/**
 * Drafts the month's editorial (সম্পাদকীয়) for the owner (D42): published under the owner's name
 * only after they edit and approve it. Saved as a draft in the editorial section, never public.
 */
final class EditorialWriter
{
    /** @param ?string $brief the owner's own words for this editorial (D274) @param ?Post $into an empty editorial to fill */
    public static function draft(Issue $issue, User $owner, ?string $brief = null, ?Post $into = null): ?Post
    {
        // D270 (owner: "I have the editorial scheduled, but it is showing draft"): a new AI draft only replaces the AI's own
        // untouched draft. An editorial the owner wrote, edited, approved or scheduled is never overwritten or unlinked.
        $current = $into ?? $issue->editorialPost();
        if ($current && ! self::replaceable($current)) {
            return null;
        }
        $section = Section::where('slug', 'editorial')->first();
        if (! $section) {
            return null;
        }
        // the AI's proposal for a monthly issue; a special issue (ফিরে আসা) has its pieces attached instead (D274)
        $pieces = Post::with('authors', 'section', 'latestAiReview')->where(fn ($q) => $q->whereIn('id', (array) ($issue->ai_plan['post_ids'] ?? []))
            ->orWhere(fn ($q) => $q->where('issue_id', $issue->id)->whereNotIn('status', ['draft', 'rejected', 'archived'])->where('section_id', '!=', $section->id)))->limit(40)->get()
            ->map(fn (Post $p) => '- '.$p->section?->name_bn.': "'.$p->title.'" — '.($p->authors->first()?->publicName() ?? '').'. '
                .mb_substr((string) ($p->latestAiReview?->report['summary'] ?? $p->excerpt ?? ''), 0, 200))->implode("\n");
        $past = Post::where('section_id', $section->id)->where('status', 'published')->latest('published_at')->limit(2)->get()
            ->map(fn (Post $p) => mb_substr(trim(strip_tags((string) $p->body_html)), 0, 1200))->implode("\n---\n");

        [$data] = Claude::json('editorial_draft',
            "You draft the monthly editorial (সম্পাদকীয়) of Hatpakha (হাতপাখা), a Bengali literary web magazine, for the owner-editor "
            ."{$owner->name}, who will edit it and publish it under their own name, first person plural (আমরা) as the magazine. "
            .Prompt::LITERARY.FeedbackLessons::forPrompt('editorial').' '
            // D107 (owner): a literary editorial, not an issue announcement.
            .'An editorial in the tradition of Bengali little magazines: begin from one small, exact image of this month (the light, a sound in the lane, the first cold) and let it lead, '
            .'without a jump, to what this issue is about. Do not list the contents: choose two or three pieces and say in a line each what stays with you, quoting nothing you were not given '
            .'(title and writer only, no invented lines from the pieces). Thank readers and writers in one sentence, plainly, and invite new writing once, briefly, without slogans. '
            .'300-450 words. Do not invent facts, events, quotes or numbers. If past editorials are given, keep their voice but do not repeat their openings.',
            "Issue: {$issue->title}\nEditor's brief: ".($issue->ai_plan['editorial_brief'] ?? '').($brief ? "\nThe owner-editor's own instructions (follow them): ".$brief : '')."\nPieces:\n{$pieces}\n\nPast editorials:\n".($past ?: '(none)'),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'paragraphs'], 'properties' => [
                'title' => ['type' => 'string'], 'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]], (string) config('ai.anthropic.writer_model'), 32000);
        if (! is_array($data) || empty($data['paragraphs'])) {
            return null;
        }
        $author = Author::where('user_id', $owner->id)->first() ?? Author::create([
            'user_id' => $owner->id, 'display_name' => $owner->name, 'slug' => 'editor-'.$owner->id,
        ]);
        $html = collect((array) $data['paragraphs'])->map(fn ($p) => '<p>'.e(trim((string) $p)).'</p>')->implode('');
        $post = $current ?? new Post();
        $fill = [
            'title' => mb_substr(trim((string) $data['title']), 0, 200) ?: 'সম্পাদকীয়: '.$issue->title,
            'body_html' => $html,
            'section_id' => $section->id,
            'format' => 'prose',
            'language' => 'bn',
            'status' => 'draft',
            'slug' => $post->slug ?: \App\Support\Review::uniqueSlug('sompadokiyo-'.$issue->slug, 0),
            'ai_drafted_at' => now(),
        ];
        // 2026-11-26 (site errors): two clicks at once made the same new address twice; the second one gets the next free address.
        try {
            $post->forceFill($fill)->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $post->forceFill(['slug' => \App\Support\Review::uniqueSlug('sompadokiyo-'.$issue->slug, (int) $post->id)])->save();
        }
        $post->authors()->sync([$author->id => ['position' => 0]]);
        $issue->forceFill(['editorial_post_id' => $post->id])->save();
        \App\Models\AuditLog::record('editorial.drafted', $post, ['issue' => $issue->slug]);
        // Owner (2026-11-08): the AI-written editorial also gets a cover from the designer, for the owner to approve.
        if (! $post->featured_image_path && ! $post->proposed_image_path) {
            Illustrator::queue($post, 'The mood of this month and season in Bengal, symbolic and calm.');
        }

        return $post;
    }

    /** Only the AI's own draft that nobody has edited, approved or scheduled. */
    public static function replaceable(Post $post): bool
    {
        if ($post->status === 'draft' && blank(trim(strip_tags((string) $post->body_html)))) {
            return true;   // D274: an empty editorial (started by hand, nothing written yet) may be filled
        }

        return $post->status === 'draft' && $post->ai_drafted_at !== null
            && ($post->edited_in_desk_at === null || $post->edited_in_desk_at->lte($post->ai_drafted_at->copy()->addMinute()));
    }
}
