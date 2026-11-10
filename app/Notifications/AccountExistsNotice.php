<?php

namespace App\Notifications;

use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D59: someone tried to register with an address that already has an account. Told only to its owner. */
class AccountExistsNotice extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return \App\Support\MailTemplates::mail('account_exists', [], Url::to('/login'));   // D123
    }
}
