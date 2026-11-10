<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D108: a serial the reader follows has a new episode. One short note, with a one-click way to stop. */
class NewEpisode extends Notification
{
    public function __construct(public Post $post, public string $unfollowUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return \App\Support\MailTemplates::mail('new_episode', ['{ধারাবাহিক}' => $this->post->serial->title, '{পর্ব}' => $this->post->episodeLabel(), '{বন্ধের_লিংক}' => $this->unfollowUrl],
            $this->post->url(), (new MailMessage)->mailer(\App\Support\BulkMail::mailer()));   // D123
    }
}
