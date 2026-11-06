<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterConfirm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** D58: double opt-in, no address probing, spam trap. */
class NewsletterSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_needs_confirmation_before_any_letter(): void
    {
        Notification::fake();
        $this->from('/archive/')->post('/newsletter/subscribe', ['email' => 'Reader@Example.com', 'name' => 'পাঠক', 'from' => 'footer'])
            ->assertRedirect()->assertSessionHas('newsletter_from', 'footer');
        $sub = NewsletterSubscriber::firstWhere('email', 'reader@example.com');
        $this->assertSame('unconfirmed', $sub->status);
        $this->assertNull($sub->consented_at);
        $this->assertSame(0, NewsletterSubscriber::mailable()->count());
        Notification::assertSentOnDemand(NewsletterConfirm::class);

        $this->get('/newsletter/confirm/'.$sub->id)->assertForbidden();   // unsigned link
        $this->get(URL::temporarySignedRoute('newsletter.confirm', now()->addDay(), ['subscriber' => $sub->id]))->assertOk()->assertSee('চিঠি চালু হয়েছে');
        $this->assertSame(1, NewsletterSubscriber::mailable()->count());
    }

    public function test_same_answer_for_known_addresses_and_spam_trap(): void
    {
        Notification::fake();
        NewsletterSubscriber::create(['email' => 'known@example.com', 'status' => 'subscribed', 'consented_at' => now()]);
        $a = $this->post('/newsletter/subscribe', ['email' => 'known@example.com'])->getSession()->get('newsletter');
        $b = $this->post('/newsletter/subscribe', ['email' => 'new@example.com'])->getSession()->get('newsletter');
        $this->assertSame($a, $b);
        $this->post('/newsletter/subscribe', ['email' => 'bot@example.com', 'website' => 'http://spam'])->assertRedirect();
        $this->assertNull(NewsletterSubscriber::firstWhere('email', 'bot@example.com'));
        $this->post('/newsletter/subscribe', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }

    public function test_form_shows_on_post_page_and_footer(): void
    {
        $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $this->get('/p/')->assertOk()->assertSee('id="newsletter-card"', false)->assertSee('id="newsletter-footer"', false);
    }
}
