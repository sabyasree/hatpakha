<?php

namespace App\Notifications;

use App\Models\Post;
use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a writer what happened to their piece (Bengali). Sent only by an editor's action. */
class SubmissionDecision extends Notification
{
    public function __construct(public Post $post, public string $decision) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->post;
        $v = ['{নাম}' => $notifiable->name, '{লেখা}' => $p->title, '{সম্পাদকের_কথা}' => (string) $p->review_note];

        return match ($this->decision) {
            'approved' => \App\Support\MailTemplates::mail('decision_approved', $v + ['{প্রকাশ}' => $p->published_at?->isFuture() ? 'প্রকাশের তারিখ: '.\App\Support\Bn::date($p->published_at) : 'লেখাটি এখন হাতপাখায় পড়া যাচ্ছে।'],
                $p->published_at?->isFuture() ? Url::to('/account') : $p->url()),
            'confirm' => \App\Support\MailTemplates::mail('decision_confirm', $v, Url::to('/submit/'.$p->id)),
            'changes' => \App\Support\MailTemplates::mail('decision_changes', $v, Url::to('/submit/'.$p->id)),
            default => \App\Support\MailTemplates::mail('decision_declined', $v, Url::to('/account')),
        };
    }
}
