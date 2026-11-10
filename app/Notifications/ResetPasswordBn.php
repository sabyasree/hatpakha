<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** Public-site password reset email, in Bengali. (The /desk panel keeps Filament's own email.) */
class ResetPasswordBn extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return \App\Support\MailTemplates::mail('reset_password', ['{মিনিট}' => \App\Support\Bn::num((string) config('auth.passwords.users.expire'))], $url);   // D123
    }
}
