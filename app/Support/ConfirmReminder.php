<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Post;

/**
 * D256 (owner, 2026-11-23: "yes, build both"): a writer asked to agree to the editor's word changes ("মনোনীত, সম্মতি
 * চাই") who has not answered in three days gets one gentle reminder. Only one, ever, per request; nothing is changed or
 * published by it. The editor's own decision (D42) is untouched: the writer's answer still decides.
 */
final class ConfirmReminder
{
    public const AFTER_DAYS = 3;

    public static function send(): int
    {
        $sent = 0;
        $due = Post::with('submittedBy')->where('status', 'confirm')->whereNotNull('submitted_by')
            ->where('reviewed_at', '<=', now()->subDays(self::AFTER_DAYS))->limit(30)->get();
        foreach ($due as $post) {
            $p = (array) $post->pending_edits;
            if (! $p || isset($p['reminded_at'])) {
                continue;
            }
            $p['reminded_at'] = now()->toIso8601String();
            $post->forceFill(['pending_edits' => $p])->saveQuietly();   // marked first: never twice, even if the mail fails
            $writer = $post->submittedBy;
            if (! $writer || blank($writer->email) || $writer->spam_reason) {
                continue;
            }
            $sent += (int) rescue(function () use ($writer, $post) {
                $writer->notify(new \App\Notifications\TemplateMail('confirm_reminder', ['{লেখা}' => $post->title], Url::to('/submit/'.$post->id)));
                AuditLog::record('submission.confirm_reminded', $post);

                return true;
            }, false, true);
        }

        return $sent;
    }
}
