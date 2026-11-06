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

        return (new MailMessage)
            ->subject('হাতপাখা: নতুন পাসওয়ার্ড বানানোর লিংক')
            ->greeting('নমস্কার,')
            ->line('আপনার হাতপাখা অ্যাকাউন্টের পাসওয়ার্ড বদলানোর অনুরোধ এসেছে।')
            ->action('নতুন পাসওয়ার্ড বানান', $url)
            ->line('লিংকটি '.config('auth.passwords.users.expire').' মিনিট কাজ করবে।')
            ->line('আপনি অনুরোধ না করে থাকলে কিছু করতে হবে না; পাসওয়ার্ড যেমন আছে তেমনই থাকবে।')
            ->salutation('— হাতপাখা');
    }
}
