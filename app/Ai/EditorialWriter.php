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
    public static function draft(Issue $issue, User $owner): ?Post
    {
        $section = Section::where('slug', 'editorial')->first();
        if (! $section) {
            return null;
        }
        $pieces = Post::with('authors', 'section', 'latestAiReview')->whereIn('id', (array) ($issue->ai_plan['post_ids'] ?? []))->get()
            ->map(fn (Post $p) => '- '.$p->section?->name_bn.': "'.$p->title.'" — '.($p->authors->first()?->publicName() ?? '').'. '
                .mb_substr((string) ($p->latestAiReview?->report['summary'] ?? $p->excerpt ?? ''), 0, 200))->implode("\n");
        $past = Post::where('section_id', $section->id)->where('status', 'published')->latest('published_at')->limit(2)->get()
            ->map(fn (Post $p) => mb_substr(trim(strip_tags((string) $p->body_html)), 0, 1200))->implode("\n---\n");

        [$data] = Claude::json('editorial_draft',
            "You draft the monthly editorial (সম্পাদকীয়) of Hatpakha (হাতপাখা), a Bengali literary web magazine, for the owner-editor "
            ."{$owner->name}, who will edit it and publish it under their own name. Write in warm, simple, literary Bengali, 350-550 words, "
            .'first person plural (আমরা) as the magazine. Mention the season or festival, introduce the issue and a few pieces by title and writer, '
            .'thank readers and writers, invite new writing. Do not invent facts, events, quotes or numbers. Past editorials (if any) show the voice.',
            "Issue: {$issue->title}\nEditor's brief: ".($issue->ai_plan['editorial_brief'] ?? '')."\nPieces:\n{$pieces}\n\nPast editorials:\n".($past ?: '(none)'),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'paragraphs'], 'properties' => [
                'title' => ['type' => 'string'], 'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string']],
            ]], null, 6000);
        if (! is_array($data) || empty($data['paragraphs'])) {
            return null;
        }
        $author = Author::where('user_id', $owner->id)->first() ?? Author::create([
            'user_id' => $owner->id, 'display_name' => $owner->name, 'slug' => 'editor-'.$owner->id,
        ]);
        $html = collect((array) $data['paragraphs'])->map(fn ($p) => '<p>'.e(trim((string) $p)).'</p>')->implode('');
        $post = $issue->editorial ?? new Post();
        $post->forceFill([
            'title' => mb_substr(trim((string) $data['title']), 0, 200) ?: 'সম্পাদকীয়: '.$issue->title,
            'body_html' => $html,
            'section_id' => $section->id,
            'format' => 'prose',
            'language' => 'bn',
            'status' => 'draft',
            'slug' => $post->slug ?: 'sompadokiyo-'.$issue->slug,
            'ai_drafted_at' => now(),
        ])->save();
        $post->authors()->sync([$author->id => ['position' => 0]]);
        $issue->forceFill(['editorial_post_id' => $post->id])->save();
        \App\Models\AuditLog::record('editorial.drafted', $post, ['issue' => $issue->slug]);

        return $post;
    }
}
