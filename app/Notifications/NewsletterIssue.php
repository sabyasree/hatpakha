<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The weekly newsletter (D48): only to readers who confirmed, with a one-click unsubscribe link. */
class NewsletterIssue extends Notification
{
    public function __construct(public array $issue, public string $unsubscribeUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $m = (new MailMessage)->subject($this->issue['subject'])->greeting('নমস্কার')->line($this->issue['intro']);
        foreach ($this->issue['items'] as $it) {
            $m->line('**'.$it['title'].'** — '.$it['author'])->line($it['line'].' '.$it['url']);
        }

        return $m->action('হাতপাখা খুলুন', url('/').'/')
            ->line('আর চিঠি চান না? এক ক্লিকে বন্ধ করুন: '.$this->unsubscribeUrl)
            ->salutation('— হাতপাখা');
    }
}
