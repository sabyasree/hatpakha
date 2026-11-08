<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D108: a serial the reader follows has a new episode. One short note, with a one-click way to stop. */
class NewEpisode extends Notification
{
    public function __construct(public Post $post, public string $unfollowUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $serial = $this->post->serial;

        return (new MailMessage)->subject($serial->title.': '.$this->post->episodeLabel())->greeting('নমস্কার')
            ->line('আপনি যে ধারাবাহিক পড়ছেন, "'.$serial->title.'"-এর নতুন পর্ব এসেছে।')
            ->action('নতুন পর্ব পড়ুন', $this->post->url())
            ->line('এই ধারাবাহিকের খবর আর চান না? এক ক্লিকে বন্ধ করুন: '.$this->unfollowUrl)
            ->salutation('— হাতপাখা');
    }
}
