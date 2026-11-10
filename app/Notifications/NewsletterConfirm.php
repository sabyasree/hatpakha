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
        return (new MailMessage)->subject('হাতপাখার চিঠি: একবার নিশ্চিত করুন')->greeting('নমস্কার')
            ->line('হাতপাখার সাপ্তাহিক চিঠি পেতে নিচের বোতামে একবার চাপ দিন। সপ্তাহে একটির বেশি চিঠি নয়, যখন খুশি বন্ধ করা যায়।')
            ->action('হ্যাঁ, চিঠি চাই', $this->url)
            ->line('আপনি না চাইলে এই ই-মেলটি উপেক্ষা করুন; কিছুই হবে না।')
            ->salutation('— হাতপাখা');
    }
}
