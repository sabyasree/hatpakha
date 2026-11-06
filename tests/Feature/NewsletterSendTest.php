<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\EditorJobs;
use App\Enums\Role;
use App\Filament\Resources\Newsletters\Pages\EditNewsletter;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Mail\NewsletterMail;
use App\Models\Author;
use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\NewsletterSender;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** D58: AI drafts, the owner sends; readers need opt-in, writers can opt out; nobody twice. */
class NewsletterSendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'mail.default' => 'smtp']);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_ai_drafts_weekly_letter_and_owner_sends_in_batches_to_the_right_people(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'w1', 'title' => 'সপ্তাহের লেখা', 'published_at' => now()->subDay()]);
        $reader = NewsletterSubscriber::create(['email' => 'reader@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        NewsletterSubscriber::create(['email' => 'pending@example.test', 'status' => 'unconfirmed']);
        $writer = User::factory()->create(['email' => 'writer@example.test', 'role' => Role::Writer, 'email_verified_at' => now()]);
        User::factory()->create(['email' => 'quiet@example.test', 'role' => Role::Writer, 'email_verified_at' => now(), 'mail_opt_out' => true]);
        NewsletterSubscriber::create(['email' => 'writer@example.test', 'status' => 'subscribed', 'consented_at' => now()]);   // both lists

        Claude::$fake = fn () => ['subject' => 'হাতপাখার চিঠি', 'intro' => 'এই সপ্তাহে…', 'closing' => 'ভালো থাকবেন', 'items' => [['id' => $post->id, 'line' => 'পড়ুন']]];
        $n = EditorJobs::newsletter();
        $this->assertSame('draft', $n->status);
        $this->assertSame('সপ্তাহের লেখা', $n->items[0]['title']);
        Mail::assertNothingSent();

        $n->update(['audience' => 'both']);
        $this->assertSame(['reader@example.test', 'writer@example.test'], NewsletterSender::recipients('both')->pluck('email')->sort()->values()->all());

        $owner = User::factory()->create(['role' => Role::Admin]);
        $this->assertSame(2, NewsletterSender::queue($n, $owner));
        $this->assertSame(1, NewsletterSender::batch(1));
        $this->assertSame(1, NewsletterSender::batch(5));
        $this->assertSame(0, NewsletterSender::batch(5), 'Nobody twice');
        Mail::assertSent(NewsletterMail::class, 2);
        $this->assertSame('sent', $n->fresh()->status);

        $this->get(URL::signedRoute('newsletter.unsubscribe.writer', ['user' => $writer->id]))->assertOk();
        $this->assertTrue($writer->fresh()->mail_opt_out);
        $this->get('/newsletter/unsubscribe/w/'.$writer->id)->assertForbidden();
    }

    public function test_nothing_is_sent_on_a_server_without_real_mail(): void
    {
        config(['mail.default' => 'log']);
        NewsletterSubscriber::create(['email' => 'reader@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        $n = Newsletter::create(['subject' => 'x', 'audience' => 'readers', 'status' => 'queued']);
        $this->assertSame(0, NewsletterSender::batch());
    }

    public function test_desk_pages_draft_preview_and_send(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        NewsletterSubscriber::create(['email' => 'reader@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        Claude::$fake = fn () => ['subject' => 'লেখকদের চিঠি', 'intro' => 'নতুন লেখা পাঠান।', 'closing' => 'শুভেচ্ছা'];
        Livewire::test(ListNewsletters::class)->assertOk()->callAction('aiDraft', ['kind' => 'writers'])->assertHasNoActionErrors();
        $n = Newsletter::firstWhere('source', 'ai_writers');
        $this->assertSame('writers', $n->audience);

        $n->update(['audience' => 'readers']);
        Livewire::test(EditNewsletter::class, ['record' => $n->id])->assertOk()->callAction('send');
        $this->assertSame('queued', $n->fresh()->status);
        $this->assertSame(1, $n->fresh()->recipients);
    }
}
