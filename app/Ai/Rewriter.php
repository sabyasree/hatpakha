<?php

namespace App\Ai;

use App\Models\AuditLog;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\WritingFeedback;

/**
 * D113 (owner): rewrite an AI-written piece to answer a human reader's feedback. The new version waits beside the old
 * one; the owner compares and accepts it (and can go back). Never used on a human writer's work.
 */
final class Rewriter
{
    public static function canRewrite(?Post $post): bool
    {
        if (self::isEditorial($post)) {
            return true;   // D247: the AI's editorial draft is signed with the owner's name, not the desk's
        }

        return $post && $post->ai_drafted_at && $post->authors()->exists() && ! $post->authors()->where('is_desk', false)->exists();
    }

    /**
     * D247 (owner: "how to rewrite the editorial? there is no option to ask to rewrite"): an editorial the AI drafted
     * (EditorialWriter) can be sent back with the owner's notes. The new version waits beside the old one until the
     * owner chooses it, as for every rewrite; nothing is published by this.
     */
    public static function isEditorial(?Post $post): bool
    {
        return $post && $post->ai_drafted_at && $post->section?->slug === 'editorial';
    }

    /** Every minute from the scheduler: queued rewrites and rules still to be written down. */
    public static function runQueued(int $max = 1): int
    {
        $done = 0;
        foreach (WritingFeedback::where('apply_future', true)->whereNull('lesson')->limit(5)->get() as $f) {
            FeedbackLessons::distill($f);
        }
        foreach (WritingFeedback::with('post')->where('rewrite_status', 'queued')->oldest()->limit($max)->get() as $f) {
            $f->forceFill(['rewrite_status' => 'running'])->save();
            $err = self::rewrite($f);
            $f->forceFill(['rewrite_status' => $err ? 'failed' : 'done', 'rewrite_error' => $err ? mb_substr($err, 0, 290) : null])->save();
            $done += $err ? 0 : 1;
        }

        return $done;
    }

    public static function rewrite(WritingFeedback $f): ?string
    {
        $post = $f->post;
        if (! self::canRewrite($post)) {
            return 'শুধু এআই-লেখা খসড়াই আবার লেখানো যায়।';
        }
        $slug = $post->section?->slug;
        $brief = $post->serial_id ? (SerialWriter::WRITERS[$post->serial?->writer][1] ?? '') : (DeskWriter::SECTIONS[$slug] ?? '');
        if (self::isEditorial($post)) {   // D247: the same rules the editorial was first written by
            $brief = 'This is the monthly editorial (সম্পাদকীয়), written as the magazine (আমরা) and published under the owner-editor\'s name after they approve it. '
                .'Keep it an editorial in the tradition of Bengali little magazines; do not list the contents; quote no line from a piece; invent no fact, event or number.';
        }
        $paras = collect(preg_split('~</p>~i', (string) $post->body_html))->map(fn ($p) => trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />'], "\n", $p)))))->filter()->values();
        [$d, $err] = Claude::json('rewrite',
            'You revise a piece written by the AI desk of Hatpakha (হাতপাখা), a Bengali literary web magazine, after a human reader\'s feedback. '
            .$brief.' '.Prompt::LITERARY.FeedbackLessons::forPrompt($slug, $post->serial_id).' '
            .'Revise the piece so it answers the feedback fully. Keep what already works; change what the feedback asks; keep the length similar unless the feedback asks otherwise. '
            .'Never add facts, names, dates or quotes that are not in the piece or the feedback. For a serial episode keep every name and event consistent with the story. '
            .'Return the revised title, the paragraphs, and what_changed: two or three lines in Bengali telling the editor what you changed.',
            '<piece><title>'.e($post->title).'</title>'."\n".$paras->map(fn ($p) => '<p>'.e($p).'</p>')->implode("\n").'</piece>'
                ."\n<feedback".($f->giver ? ' from="'.e($f->giver).'"' : '').'>'.e($f->body).'</feedback>',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'paragraphs', 'what_changed'], 'properties' => [
                'title' => ['type' => 'string'], 'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string']], 'what_changed' => ['type' => 'string'],
            ]], (string) config('ai.anthropic.writer_model'), 48000, $post->id);
        if (! is_array($d) || empty($d['paragraphs'])) {
            return $err ?: 'নতুন সংস্করণ তৈরি হয়নি।';
        }
        $post->forceFill([
            'rewrite_html' => collect((array) $d['paragraphs'])->map(fn ($p) => '<p>'.e(trim((string) $p)).'</p>')->implode(''),
            // A serial episode keeps its numbered title; other pieces may get a better one.
            'rewrite_title' => $post->serial_id ? null : mb_substr(trim((string) $d['title']), 0, 200),
            'rewrite_note' => mb_substr(trim(strip_tags((string) $d['what_changed'])), 0, 1000),
            'rewrite_kind' => 'proposed', 'rewrite_at' => now(),
        ])->saveQuietly();
        EditorTask::propose('rewrite', __('মতামত মেনে নতুন সংস্করণ: ').$post->title, [], $post);

        return null;
    }

    /** Use the other version: the proposal goes in, the current text is kept so the owner can go back. */
    public static function swap(Post $post): void
    {
        if (! $post->rewrite_html) {
            return;
        }
        $old = ['html' => $post->body_html, 'title' => $post->title];
        $post->forceFill(['body_html' => $post->rewrite_html, 'title' => $post->rewrite_title ?: $post->title, 'edited_in_desk_at' => now()])->save();   // sanitised on save; the owner chose it (D270)
        $post->forceFill(['rewrite_html' => $old['html'], 'rewrite_title' => $post->rewrite_title ? $old['title'] : null,
            'rewrite_kind' => $post->rewrite_kind === 'proposed' ? 'previous' : 'proposed'])->saveQuietly();
        EditorTask::open('rewrite')->where('subject_type', $post->getMorphClass())->where('subject_id', $post->id)->get()->each->close();
        AuditLog::record('post.rewrite_swapped', $post);
    }

    public static function discard(Post $post): void
    {
        $post->forceFill(['rewrite_html' => null, 'rewrite_title' => null, 'rewrite_note' => null, 'rewrite_kind' => null])->saveQuietly();
        EditorTask::open('rewrite')->where('subject_type', $post->getMorphClass())->where('subject_id', $post->id)->get()->each->close();
    }
}
