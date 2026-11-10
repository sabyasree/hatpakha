<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailBn extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return \App\Support\MailTemplates::mail('verify_email', ['{নাম}' => $notifiable->name], $this->verificationUrl($notifiable));   // D123
    }
}
