<?php

namespace App\Notifications;

use App\Support\Bn;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** D104: the apology letter to a writer of old unanswered submissions, with a link to answer per piece. */
class RelaunchLetter extends Notification
{
    /** @param array{subject:string, body:string} $letter */
    public function __construct(public array $letter, public array $titles, public string $link) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $list = collect($this->titles)->take(10)->map(fn ($t) => '• '.$t)->implode("\n");
        if (count($this->titles) > 10) {
            $list .= "\n".'এবং আরও '.Bn::num((string) (count($this->titles) - 10)).'টি লেখা';
        }
        $body = strtr($this->letter['body'], ['{লেখা}' => $list, '{নাম}' => (string) $notifiable->name]);
        $mail = (new MailMessage)->mailer(\App\Support\BulkMail::mailer())->subject($this->letter['subject'])->greeting('নমস্কার, '.$notifiable->name);
        foreach (preg_split("/\n{2,}/u", $body) as $para) {
            $mail->line(new \Illuminate\Support\HtmlString(nl2br(e(trim($para)))));
        }

        return $mail->action('উত্তর দিন', $this->link)->salutation('— হাতপাখা সম্পাদকমণ্ডলী');
    }
}
