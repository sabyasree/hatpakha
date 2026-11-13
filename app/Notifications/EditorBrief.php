<?php

namespace App\Notifications;

use App\Support\Bn;
use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The AI editor's morning note to the owner (admins only, one e-mail a day, D44). */
class EditorBrief extends Notification
{
    public function __construct(public array $b) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $n = fn ($k) => Bn::num((int) ($this->b[$k] ?? 0));

        return (new MailMessage)->subject('হাতপাখা: আজকের কাজ')
            ->greeting('নমস্কার, '.$notifiable->name)
            ->line("গত ২৪ ঘণ্টায় এআই সম্পাদক {$n('prepared')}টি লেখা তৈরি করেছে, {$n('pictures')}টি ছবি এঁকেছে, {$n('comments_screened')}টি মন্তব্য পড়েছে।")
            ->line("আপনার অপেক্ষায়: {$n('waiting_pieces')}টি লেখা, {$n('waiting_comments')}টি মন্তব্য, {$n('waiting_pictures')}টি ছবি"
                .($this->b['issue_proposed'] ? ', সংখ্যার প্রস্তাব' : '').($this->b['editorial_drafts'] ? ', সম্পাদকীয় খসড়া' : '').'।')
            ->when(($this->b['free_slots_week'] ?? 0) > 0, fn ($m) => $m->line("এই সপ্তাহে {$n('free_slots_week')}টি প্রকাশের দিন খালি।"))
            // D191: errors are said in the note, not left in a log file
            ->when(($this->b['errors_new'] ?? 0) > 0, fn ($m) => $m->line("গত ২৪ ঘণ্টায় সাইটে {$n('errors_new')}টি নতুন রকমের ত্রুটি হয়েছে। ডেস্কে “সাইটের ত্রুটি” পাতায় দেখুন।"))
            ->action('আজকের কাজ খুলুন', Url::to('/desk'))
            ->salutation('— এআই সম্পাদক, হাতপাখা');
    }
}
