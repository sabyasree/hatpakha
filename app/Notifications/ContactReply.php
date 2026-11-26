<?php

namespace App\Notifications;

use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D280: the editor's answer to a message sent through the contact form, written in the desk (one e-mail, one person). */
class ContactReply extends Notification
{
    public function __construct(public string $name, public string $topic, public string $message) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('হাতপাখা: আপনার বার্তার উত্তর ('.$this->topic.')')
            ->greeting('নমস্কার, '.$this->name);
        \App\Support\MailText::add($mail, $this->message);   // the editor's own line breaks are kept (2026-11-26)

        return \App\Support\WriterLetters::mark($mail
            ->action('হাতপাখা', Url::to('/'))
            ->salutation('— হাতপাখা সম্পাদকমণ্ডলী'), 'contact_reply');
    }
}
