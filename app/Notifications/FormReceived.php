<?php

namespace App\Notifications;

use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Owner, 2026-11-10 (D122): "we received it" after a form. For the contact and advertising forms it never repeats
 * the visitor's text or name (the address typed in may not be theirs, so the e-mail must be useless to a prankster),
 * and an address gets at most one a day. For a writing submission it goes to the writer's own verified account.
 */
class FormReceived extends Notification
{
    public function __construct(public string $kind, public ?string $title = null) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return match ($this->kind) {
            'submission' => \App\Support\MailTemplates::mail('submission_received', ['{নাম}' => $notifiable->name, '{লেখা}' => $this->title], Url::to('/account/writings')),
            'advertise' => \App\Support\MailTemplates::mail('advertise_received', []),
            default => \App\Support\MailTemplates::mail('contact_received', []),
        };
    }
}
