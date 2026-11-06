<?php

namespace App\Notifications;

use App\Models\Post;
use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a writer what happened to their piece (Bengali). Sent only by an editor's action. */
class SubmissionDecision extends Notification
{
    public function __construct(public Post $post, public string $decision) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $m = (new MailMessage)->greeting('নমস্কার, '.$notifiable->name)->salutation('— হাতপাখা সম্পাদকমণ্ডলী');
        $title = '"'.$this->post->title.'"';

        return match ($this->decision) {
            'approved' => $m->subject("হাতপাখা: {$title} মনোনীত হয়েছে")
                ->line("আনন্দের খবর! আপনার লেখা {$title} হাতপাখায় প্রকাশের জন্য মনোনীত হয়েছে।")
                ->line($this->post->published_at?->isFuture() ? 'প্রকাশের তারিখ: '.\App\Support\Bn::date($this->post->published_at) : 'লেখাটি এখন প্রকাশিত।')
                ->when($this->post->review_note, fn ($m) => $m->line('সম্পাদকের কথা: '.$this->post->review_note))
                ->action('লেখাটি দেখুন', $this->post->published_at?->isFuture() ? Url::to('/account') : $this->post->url()),
            'changes' => $m->subject("হাতপাখা: {$title} — একটু বদল চাই")
                ->line("আপনার লেখা {$title} পড়ে সম্পাদক কয়েকটি কথা লিখেছেন:")
                ->line($this->post->review_note)
                ->action('লেখাটি খুলে ঠিক করুন', Url::to('/submit/'.$this->post->id))
                ->line('ঠিক করে আবার জমা দিলেই আমরা আবার পড়ব।'),
            default => $m->subject("হাতপাখা: {$title} সম্পর্কে")
                ->line($this->post->review_note)
                ->line('লেখার জন্য ধন্যবাদ।')
                ->action('আমার লেখা', Url::to('/account')),
        };
    }
}
