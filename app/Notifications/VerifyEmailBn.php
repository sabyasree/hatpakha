<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailBn extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('হাতপাখা: ইমেল যাচাই করুন')
            ->greeting('নমস্কার, '.$notifiable->name)
            ->line('হাতপাখায় অ্যাকাউন্ট খোলার জন্য ধন্যবাদ। লেখা পাঠানোর আগে নিচের বোতামে ক্লিক করে ইমেলটি যাচাই করুন।')
            ->action('ইমেল যাচাই করুন', $this->verificationUrl($notifiable))
            ->line('আপনি অ্যাকাউন্ট না খুলে থাকলে এই ইমেলটি উপেক্ষা করুন।')
            ->salutation('— হাতপাখা');
    }
}
