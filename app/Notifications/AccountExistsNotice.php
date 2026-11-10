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
        return (new MailMessage)->subject('হাতপাখা: আপনার অ্যাকাউন্ট আগে থেকেই আছে')->greeting('নমস্কার')
            ->line('এই ই-মেলে নতুন অ্যাকাউন্ট খোলার চেষ্টা হয়েছে, কিন্তু এই ঠিকানায় হাতপাখার অ্যাকাউন্ট আগে থেকেই আছে।')
            ->action('লগ-ইন করুন', Url::to('/login'))
            ->line('পাসওয়ার্ড মনে না থাকলে লগ-ইন পাতার "পাসওয়ার্ড ভুলে গেছেন?" থেকে নতুন পাসওয়ার্ড নিন। আপনি চেষ্টা না করে থাকলে এই ই-মেলটি উপেক্ষা করুন।')
            ->salutation('— হাতপাখা');
    }
}
