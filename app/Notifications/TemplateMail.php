<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D123: an e-mail written from one of the owner's templates (MailTemplates), with its placeholders filled in. */
class TemplateMail extends Notification
{
    /** @param array<string, string> $values */
    public function __construct(public string $key, public array $values = [], public ?string $url = null) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return \App\Support\MailTemplates::mail($this->key, ['{নাম}' => (string) ($notifiable->name ?? '')] + $this->values, $this->url);
    }
}
