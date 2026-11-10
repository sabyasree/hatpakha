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
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->unsubscribeUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.newsletter', text: 'emails.newsletter-text');
    }
}
