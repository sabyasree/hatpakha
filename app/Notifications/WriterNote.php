<?php

namespace App\Notifications;

use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One personal note from the editors to a writer (follow-up or welcome), sent by the owner's click (D48). */
class WriterNote extends Notification
{
    public function __construct(public string $subject, public string $message, public string $link, public string $button = 'আমার লেখা') {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->subject)
            ->greeting('নমস্কার, '.$notifiable->name)
            ->line($this->message)
            ->action($this->button, $this->link ?: Url::to('/account'))
            ->salutation('— হাতপাখা সম্পাদকমণ্ডলী');
    }
}
