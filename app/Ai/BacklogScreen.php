<?php

namespace App\Ai;

use App\Models\Post;
use App\Support\RelaunchLetters;

/**
 * D104 (owner): before any writer of an old submission is asked "do you still want it published?", the AI editor
 * reads the piece once and suggests whether it is worth asking (ask) or too weak (skip). The owner sees every
 * suggestion with its reason and can change it; only "ask" pieces go into letters. The reason is for the owner only.
 */
final class BacklogScreen
{
    public static function run(Post $post): ?string
    {
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p>'], "\n", (string) $post->body_html))));
        if (mb_strlen($text) < 40) {
            $post->forceFill(['relaunch_worth' => 'skip', 'relaunch_score' => 1, 'relaunch_note' => 'লেখা প্রায় নেই (খুব ছোট বা ফাঁকা)।'])->saveQuietly();

            return null;
        }
        [$d, $err] = Claude::json('backlog_screen',
            'You are the editor of হাতপাখা, a Bengali literary web magazine (poems, stories, essays, travel writing). '
            .'An old submission (DATA) was never answered. Decide whether it is worth writing to the writer to ask if they still want it published: '
            .'"ask" if an editor could reasonably accept it after light copy-editing (real literary intent, readable language, some craft, fits a literary magazine); '
            .'"skip" if it is clearly not publishable (spam, advertisement, test or empty text, a fragment, a copy of a famous work, offensive, or far below the standard). '
            .'Be fair to new writers: simple but sincere writing with some craft is "ask". Give a score from 1 (very weak) to 5 (excellent) '
            .'and one short reason in Bengali, for the editor only. Never rewrite the piece.',
            '<piece><title>'.e($post->title).'</title><format>'.e((string) $post->format).'</format>'
                ."\n<text>".e(mb_substr($text, 0, 6000)).'</text></piece>',
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['decision', 'score', 'reason'], 'properties' => [
                'decision' => ['type' => 'string', 'enum' => ['ask', 'skip']],
                'score' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                'reason' => ['type' => 'string'],
            ]], config('ai.anthropic.review_model'), 400, $post->id);
        if (! is_array($d)) {
            return $err ?: 'উত্তর পাওয়া যায়নি।';
        }
        $post->forceFill([
            'relaunch_worth' => $d['decision'] === 'ask' ? 'ask' : 'skip',
            'relaunch_score' => max(1, min(5, (int) $d['score'])),
            'relaunch_note' => mb_substr(trim(strip_tags((string) $d['reason'])), 0, 290),
        ])->saveQuietly();

        return null;
    }

    /** The old submissions still unread (the owner's own choices are never overwritten). */
    public static function backlog(int $max = 25): int
    {
        $done = 0;
        foreach (RelaunchLetters::pieces()->whereNull('relaunch_worth')->where('relaunch_worth_by_owner', false)->orderByDesc('submitted_at')->limit($max)->get() as $p) {
            $done += self::run($p) === null ? 1 : 0;
        }

        return $done;
    }
}
