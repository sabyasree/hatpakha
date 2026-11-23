<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\EditorTask;
use App\Models\User;

/** D52, D224, D257: the editor's answer to a writer's chat question, by e-mail, kept with the question. */
final class WriterAnswer
{
    public static function send(EditorTask $task, string $message): void
    {
        abort_unless($task->type === 'question' && $task->subject instanceof User, 404);
        $again = $task->status !== 'open';
        $task->subject->notify(new \App\Notifications\WriterNote('হাতপাখা: আপনার প্রশ্নের উত্তর', $message, Url::to('/account')));
        $task->forceFill(['payload' => ['reply' => $message, 'replied_at' => now()->toIso8601String(), 'replied_by' => auth()->id()] + (array) $task->payload])->save();
        $again ? AuditLog::record('task.question.resent', $task->subject, ['task' => $task->id]) : $task->close();
    }
}
