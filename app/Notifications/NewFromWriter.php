<?php

namespace App\Notifications;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D64: a writer the reader follows has a new piece. One short note, with a one-click way to stop. */
class NewFromWriter extends Notification
{
    public function __construct(public Post $post, public Author $author, public string $unfollowUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->author->publicName().'-এর নতুন লেখা: '.$this->post->title)->greeting('নমস্কার')
            ->line('আপনি যাঁকে অনুসরণ করেন, সেই '.$this->author->publicName().'-এর নতুন লেখা হাতপাখায় প্রকাশিত হয়েছে।')
            ->line('"'.$this->post->title.'"'.($this->post->share_text ? ' — '.$this->post->share_text : ''))
            ->action('লেখাটি পড়ুন', $this->post->url())
            ->line('এই লেখকের খবর আর চান না? এক ক্লিকে অনুসরণ বন্ধ করুন: '.$this->unfollowUrl)
            ->salutation('— হাতপাখা');
    }
}
