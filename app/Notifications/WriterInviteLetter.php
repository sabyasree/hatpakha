<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\WriterInvite;
use App\Support\Bn;
use App\Support\WriterInvites;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/** D188: the personal invitation to a regular writer (or its one reminder), signed by the editor. */
class WriterInviteLetter extends Notification
{
    public function __construct(public WriterInvite $invite, public bool $reminder = false) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $inv = $this->invite;
        $name = $inv->author?->publicName() ?: (string) $notifiable->name;
        $last = Bn::date(Carbon::parse(WriterInvites::LAST_DATE, 'Asia/Kolkata'));
        $mail = (new MailMessage)->mailer(\App\Support\BulkMail::mailer())->greeting('প্রিয় '.$name.',');
        if ($this->reminder) {
            $mail->subject('আগের চিঠিটার কথা মনে করিয়ে দিচ্ছি')
                ->line('ক’দিন আগে ফিরে আসা সংখ্যার জন্য আপনাকে লিখেছিলাম। চিঠিটা হয়তো চোখ এড়িয়ে গেছে, তাই আর একবার।')
                ->line('আপনার একটা লেখা পেলে সংখ্যাটা আমাদের কাছে পূর্ণ হয়। লেখা পাঠানোর শেষ দিন '.$last.'। এবার সময় না হলেও জানাবেন, পরের সংখ্যায় অপেক্ষা করব।')
                ->line('এর পরে এ নিয়ে আর চিঠি যাবে না।');
        } else {
            $mail->subject($inv->subject);
            foreach (preg_split("/\n{2,}/u", (string) $inv->body) as $para) {
                if (trim($para) !== '') {
                    $mail->line(new HtmlString(nl2br(e(trim($para)))));
                }
            }
        }
        $signer = trim((string) Setting::get('certificates.signer_name'));

        return \App\Support\WriterLetters::mark($mail, 'writer_note')
            ->action('হ্যাঁ, লিখব', WriterInvites::link($inv, 'yes'))
            ->line(new HtmlString('<a href="'.e(WriterInvites::link($inv, 'later')).'">এবার হচ্ছে না, পরে লিখব</a> &nbsp;·&nbsp; <a href="'.e(WriterInvites::link($inv, 'no')).'">আর চিঠি চাই না</a>'))
            ->salutation(new HtmlString(($signer !== '' ? e($signer).'<br>' : '').'সম্পাদক, হাতপাখা'));
    }
}
