<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Only explicit, unsuppressed consent is mailable (CLAUDE.md §3: bulk email needs the owner). */
class NewsletterSubscriberTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_consented_unsuppressed_subscribers_are_mailable(): void
    {
        NewsletterSubscriber::create(['email' => 'a@x.in', 'status' => 'subscribed', 'source' => 'form', 'consented_at' => now()]);
        NewsletterSubscriber::create(['email' => 'b@x.in', 'status' => 'subscribed', 'source' => 'wordpress_user']); // auto-added
        NewsletterSubscriber::create(['email' => 'c@x.in', 'status' => 'unsubscribed', 'source' => 'form', 'suppressed' => true, 'consented_at' => now()]);
        NewsletterSubscriber::create(['email' => 'd@x.in', 'status' => 'bounced', 'source' => 'form', 'suppressed' => true]);

        $this->assertSame(['a@x.in'], NewsletterSubscriber::mailable()->pluck('email')->all());
    }
}
