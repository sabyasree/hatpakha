<?php

namespace App\Notifications;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D64: a writer the reader follows has a new piece. One short note, with a one-click way to stop. */
class NewFromWriter extends Notification
{
    public function __construct(public Post $post, public Author $author, public string $unfollowUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return \App\Support\MailTemplates::mail('new_from_writer', ['{লেখক}' => $this->author->publicName(), '{লেখা}' => $this->post->title, '{পরিচিতি}' => (string) $this->post->share_text,
            '{বন্ধের_লিংক}' => $this->unfollowUrl], $this->post->url(), (new MailMessage)->mailer(\App\Support\BulkMail::mailer()));   // D123
    }
}
