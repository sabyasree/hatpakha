<?php

namespace App\Ai;

use App\Models\Post;
use App\Models\WritingFeedback;

/**
 * D113 (owner): human readers' feedback becomes standing rules for the AI writers. Each rule is scoped (one section,
 * one serial, or all AI writing), can be edited or switched off by the owner, and is added to the writers' instructions.
 */
final class FeedbackLessons
{
    /** Turn the reader's words into 1-3 short, general rules (the owner can edit them). Falls back to the words themselves. */
    public static function distill(WritingFeedback $f): void
    {
        [$d] = Claude::json('feedback',
            'A human reader gave feedback on a piece written by the AI writers of Hatpakha, a Bengali literary magazine. '
            .'Turn it into 1-3 short, general rules the writers should follow from now on: imperative, concrete, in English or Bengali. '
            .'Keep the reader\'s intent exactly; add nothing they did not ask for; if it only concerns this one piece, make the rule about the kind of thing it concerns.',
            '<feedback>'.e($f->body).'</feedback>'.($f->post ? '<piece_title>'.e($f->post->title).'</piece_title>' : ''),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['rules'], 'properties' => ['rules' => ['type' => 'array', 'items' => ['type' => 'string']]]],
            (string) config('ai.anthropic.review_model'), 2000, $f->post_id);
        $rules = collect((array) ($d['rules'] ?? []))->map(fn ($r) => trim(strip_tags((string) $r)))->filter()->take(3);
        $f->forceFill(['lesson' => $rules->isNotEmpty() ? $rules->map(fn ($r) => '• '.$r)->implode("\n") : trim($f->body)])->save();
    }

    /** The active rules that apply to a piece in this section / serial, newest last, for the writers' instructions. */
    public static function forPrompt(?string $sectionSlug = null, ?int $serialId = null): string
    {
        $sectionId = $sectionSlug ? \App\Models\Section::where('slug', $sectionSlug)->value('id') : null;
        $rules = WritingFeedback::where('apply_future', true)->where('active', true)->whereNotNull('lesson')
            ->where(fn ($q) => $q->where('scope', 'all')
                ->when($sectionId, fn ($q) => $q->orWhere(fn ($q) => $q->where('scope', 'section')->where('section_id', $sectionId)))
                ->when($serialId, fn ($q) => $q->orWhere(fn ($q) => $q->where('scope', 'serial')->where('serial_id', $serialId))))
            ->oldest()->limit(40)->pluck('lesson');

        return $rules->isEmpty() ? '' : ' Standing notes from the editor and from human readers of earlier pieces. Follow them; if two conflict, the later one wins: '
            .$rules->map(fn ($l) => str_replace("\n", ' ', (string) $l))->implode(' | ').'.';
    }

    /** Where a piece's future-rule scope points by default. */
    public static function defaults(Post $post): array
    {
        return ['section_id' => $post->section_id, 'serial_id' => $post->serial_id];
    }
}
