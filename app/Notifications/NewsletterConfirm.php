<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Double opt-in (D58): nobody gets the newsletter until they click this link. */
class NewsletterConfirm extends Notification
{
    public function __construct(public string $url) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return \App\Support\MailTemplates::mail('newsletter_confirm', [], $this->url);   // D123
    }
}
