<?php

namespace App\Notifications;

use App\Models\Post;
use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a writer what happened to their piece (Bengali). Sent only by an editor's action. */
class SubmissionDecision extends Notification
{
    public const SERIAL_NOTE = 'ধারাবাহিকের পরের পর্বগুলো প্রতি শনিবার প্রকাশ হবে। তাই প্রতিটি পর্ব প্রকাশের অন্তত এক সপ্তাহ আগে জমা দেওয়ার চেষ্টা করবেন, যাতে আমরা সময় নিয়ে পড়ে ঠিক শনিবারে প্রকাশ করতে পারি। পরের পর্ব জমা দেবেন লেখা জমা দেওয়ার পাতা থেকেই: "এটি কি ধারাবাহিকের পর্ব?" প্রশ্নে "হ্যাঁ, আমার ধারাবাহিক …-এর পরের পর্ব" বেছে নেবেন। পর্বের নম্বর নিজে থেকেই বসে যাবে। পর্ব সময়ে না এলে আমরা ই-মেলে মনে করিয়ে দেব।';

    public function __construct(public Post $post, public string $decision) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $p = $this->post;
        $v = ['{নাম}' => $notifiable->name, '{লেখা}' => $p->title, '{সম্পাদকের_কথা}' => (string) $p->review_note, '{সংশোধন}' => $p->fixesText()];

        $mail = match ($this->decision) {
            'approved' => \App\Support\MailTemplates::mail('decision_approved', $v + ['{প্রকাশ}' => $p->published_at?->isFuture() ? 'প্রকাশের তারিখ: '.\App\Support\Bn::date($p->published_at) : 'লেখাটি এখন হাতপাখায় পড়া যাচ্ছে।'],
                $p->published_at?->isFuture() ? Url::to('/account') : $p->url()),
            'confirm' => \App\Support\MailTemplates::mail('decision_confirm', $v, Url::to('/submit/'.$p->id)),
            'changes' => \App\Support\MailTemplates::mail('decision_changes', $v, Url::to('/submit/'.$p->id)),
            default => \App\Support\MailTemplates::mail('decision_declined', $v, Url::to('/account')),
        };
        // D268 (owner: "when a serial is approved we add this by hand"): the first episode of a writer's own serial
        // brings the weekly rhythm with it, in the owner's words.
        if ($this->decision === 'approved' && $p->serial_id && (int) $p->episode_no === 1 && $p->serial?->user_id) {
            $mail->line(new \Illuminate\Support\HtmlString('<strong>একটা অনুরোধ:</strong> '.e(self::SERIAL_NOTE)));
        }

        return \App\Support\WriterLetters::mark($mail, 'decision_'.(in_array($this->decision, ['approved', 'confirm', 'changes'], true) ? $this->decision : 'declined'), $p);
    }
}
