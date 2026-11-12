<?php

namespace App\Support;

use App\Models\Post;
use App\Models\SentLetter;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * D161 (owner: "where can I see the letters sent to writers?"): letters to writers are marked when they are written,
 * and a copy is kept the moment they are actually sent, for /desk → পাঠানো চিঠি and the piece's page.
 */
final class WriterLetters
{
    public const KIND_HEADER = 'X-Hatpakha-Letter';

    public const POST_HEADER = 'X-Hatpakha-Post';

    /** Plain names for the kinds of letter. */
    public const KINDS = [
        'decision_approved' => 'মনোনীত', 'decision_changes' => 'বদল চাওয়া', 'decision_confirm' => 'সম্মতি চাওয়া', 'decision_declined' => 'এবার নয়',
        'writer_note' => 'সম্পাদকের চিঠি', 'certificate_ready' => 'শংসাপত্র', 'honour' => 'সম্মান', 'issue_piece' => 'সংখ্যায় লেখা', 'anthology_piece' => 'সংকলনে লেখা',
    ];

    public static function mark(MailMessage $mail, string $kind, ?Post $post = null): MailMessage
    {
        return $mail->withSymfonyMessage(function (Email $m) use ($kind, $post) {
            $m->getHeaders()->addTextHeader(self::KIND_HEADER, $kind);
            if ($post?->id) {
                $m->getHeaders()->addTextHeader(self::POST_HEADER, (string) $post->id);
            }
        });
    }

    public static function record(MessageSent $event): void
    {
        $m = $event->message;
        $kind = $m->getHeaders()->get(self::KIND_HEADER)?->getBodyAsString();
        if (! $kind || ! $m instanceof Email) {
            return;
        }
        $to = collect($m->getTo())->map(fn ($a) => $a->getAddress())->first();
        $postId = (int) ($m->getHeaders()->get(self::POST_HEADER)?->getBodyAsString() ?? 0);
        rescue(fn () => SentLetter::create([
            'kind' => mb_substr($kind, 0, 40),
            'to_email' => mb_substr((string) $to, 0, 190),
            'user_id' => $to ? User::where('email', $to)->value('id') : null,
            'post_id' => $postId && Post::withTrashed()->whereKey($postId)->exists() ? $postId : null,
            'subject' => mb_substr((string) $m->getSubject(), 0, 300),
            'html' => (string) $m->getHtmlBody(),
            'sent_at' => now(),
        ]), null, false);
    }
}
