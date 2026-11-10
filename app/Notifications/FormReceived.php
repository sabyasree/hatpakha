<?php

namespace App\Notifications;

use App\Support\Url;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Owner, 2026-11-10 (D122): "we received it" after a form. For the contact and advertising forms it never repeats
 * the visitor's text or name (the address typed in may not be theirs, so the e-mail must be useless to a prankster),
 * and an address gets at most one a day. For a writing submission it goes to the writer's own verified account.
 */
class FormReceived extends Notification
{
    public function __construct(public string $kind, public ?string $title = null) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $m = (new MailMessage)->salutation('— হাতপাখা সম্পাদকমণ্ডলী');

        return match ($this->kind) {
            'submission' => $m->subject('আপনার লেখা পেয়েছি: '.$this->title)->greeting('নমস্কার, '.$notifiable->name)
                ->line('"'.$this->title.'" আমাদের কাছে পৌঁছেছে। ধন্যবাদ!')
                ->line('সম্পাদকমণ্ডলী লেখাটি পড়ে দেখবেন। কোন ধাপে আছে, নিজের অ্যাকাউন্টের "লেখা" পাতায় যে কোনও সময় দেখতে পাবেন; সিদ্ধান্ত হলে ই-মেলেও জানাব।')
                ->action('আমার লেখা', Url::to('/account/writings')),
            'advertise' => $m->subject('হাতপাখা: আপনার প্রস্তাব পেয়েছি')->greeting('নমস্কার')
                ->line('হাতপাখার ওয়েবসাইটের ফর্ম থেকে পাঠানো আপনার প্রস্তাব আমাদের কাছে পৌঁছেছে। আমরা দেখে ই-মেলে যোগাযোগ করব।')
                ->line('আপনি এই ফর্ম পূরণ না করে থাকলে এই মেলটি উপেক্ষা করুন; আর কিছু পাঠানো হবে না।'),
            default => $m->subject('হাতপাখা: আপনার বার্তা পেয়েছি')->greeting('নমস্কার')
                ->line('হাতপাখার ওয়েবসাইটের ফর্ম থেকে পাঠানো আপনার বার্তা আমাদের কাছে পৌঁছেছে। আমরা যত তাড়াতাড়ি সম্ভব ই-মেলে উত্তর দেব।')
                ->line('আপনি এই ফর্ম পূরণ না করে থাকলে এই মেলটি উপেক্ষা করুন; আর কিছু পাঠানো হবে না।'),
        };
    }
}
