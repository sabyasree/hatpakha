<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * 2026-11-26 (owner: "when the email is sent, it breaks the next lines… email is removing the format"): Laravel's
 * MailMessage::line() joins every line of a text into one. Text the editor writes in the desk keeps its shape here:
 * a blank line starts a new paragraph, a single line break stays a line break. Everything is escaped.
 */
final class MailText
{
    public static function add(MailMessage $mail, string $text): MailMessage
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        foreach (preg_split('/\n\s*\n/u', $text) ?: [] as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            $mail->line(new HtmlString(implode('<br>', array_map(fn ($l) => e(trim($l)), explode("\n", $para)))));
        }

        return $mail;
    }
}
