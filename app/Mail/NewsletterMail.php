<?php

namespace App\Mail;

use App\Models\Newsletter;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** One newsletter e-mail (D58). Sent synchronously by the scheduler's batch (no queue worker on the host). */
class NewsletterMail extends Mailable
{
    /** $deliveryId: set for real sends, so the links can be counted (D117); null for previews and test mails. */
    public function __construct(public Newsletter $newsletter, public string $unsubscribeUrl, public ?int $deliveryId = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->newsletter->subject);
    }

    public function headers(): Headers
    {
        // Brevo would otherwise rewrite every link through its own tracking address and add a hidden pixel:
        // links that say hatpakha.com but go elsewhere look like spam, and the privacy policy promises no hidden
        // tracking in letters (our own click count stays). Amazon SES ignores these headers.
        $text = ['X-Mailin-Track' => '0', 'X-Mailin-Track-Clicks' => '0', 'X-Mailin-Track-Opens' => '0', 'X-Mailin-Track-Links' => '0'];
        // One-click unsubscribe only with a real (signed) unsubscribe link; a test letter has none.
        if (str_contains($this->unsubscribeUrl, 'unsubscribe')) {
            $text += ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'];
        }

        return new Headers(text: $text);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.newsletter', text: 'emails.newsletter-text');
    }
}
