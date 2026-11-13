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

        Claude::$fake = fn () => ['subject' => 'হাতপাখার চিঠি', 'preheader' => 'এই সপ্তাহের লেখা', 'paragraphs' => ['প্রিয় পাঠক,', 'এই সপ্তাহে… দেখুন https://evil.example'],
            'closing' => 'ভালো থাকবেন', 'cta_label' => 'পড়ুন', 'cta_target' => 'home', 'pieces' => [['id' => $post->id, 'line' => 'পড়ুন']]];
        \App\Models\Setting::put(EditorJobs::RHYTHM, 'weekly');   // D190: every two weeks by default; this test is about the weekly draft
        $n = EditorJobs::newsletter();
        $this->assertSame('draft', $n->status);
        $this->assertSame('সপ্তাহের লেখা', $n->items[0]['title']);
        $this->assertStringNotContainsString('evil.example', $n->intro, 'The copywriter never puts links in the text');
        $this->assertSame('এই সপ্তাহের লেখা', $n->preheader);
        Mail::assertNothingSent();

        $n->update(['audience' => 'both']);
        // D59: every verified account is a reader by default unless it switched the letter off.
        $this->assertSame(['reader@example.test', 'writer@example.test'], NewsletterSender::recipients('both')->pluck('email')->sort()->values()->all());
        $this->assertNotContains('quiet@example.test', NewsletterSender::recipients('readers')->pluck('email')->all());

        $owner = User::factory()->create(['role' => Role::Admin, 'mail_opt_out' => true]);
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
        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'mail_opt_out' => true]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        NewsletterSubscriber::create(['email' => 'reader@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        Claude::$fake = fn () => ['subject' => 'লেখকদের চিঠি', 'preheader' => '', 'paragraphs' => ['প্রিয় লেখক বন্ধু,', 'নতুন লেখা পাঠান।'], 'closing' => 'শুভেচ্ছা',
            'cta_label' => 'লেখা পাঠান', 'cta_target' => 'submit', 'pieces' => []];
        Livewire::test(ListNewsletters::class)->assertOk()->callAction('aiDraft', ['kind' => 'writers'])->assertHasNoActionErrors();
        $n = Newsletter::firstWhere('source', 'ai_writers');
        $this->assertSame('writers', $n->audience);
        $this->assertStringContainsString('/submit/', \App\Ai\Copywriter::ctaUrl($n));
        $html = view('emails.newsletter', ['newsletter' => $n, 'unsubscribeUrl' => '#'])->render();
        $this->assertStringContainsString('লেখা পাঠান', $html);

        $n->update(['audience' => 'readers']);
        Livewire::test(EditNewsletter::class, ['record' => $n->id])->assertOk()->callAction('send');
        $this->assertSame('queued', $n->fresh()->status);
        $this->assertSame(1, $n->fresh()->recipients);
    }

    /** D190: the readers' letter every two weeks (1st and 3rd Sunday) unless the owner chooses weekly; a monthly allowance. */
    public function test_letter_rhythm_is_every_two_weeks_unless_set_weekly_and_the_month_has_a_limit(): void
    {
        $this->assertSame('biweekly', EditorJobs::rhythm());
        $sat = fn (string $d) => \Illuminate\Support\Carbon::parse($d.' 18:00', 'Asia/Kolkata');
        // November 2026: Sundays are 1, 8, 15, 22, 29. Drafted the Saturday before.
        $this->assertTrue(EditorJobs::letterDue($sat('2026-11-14')), 'For the 3rd Sunday (15th)');
        $this->assertFalse(EditorJobs::letterDue($sat('2026-11-21')), 'Not for the 4th');
        $this->assertFalse(EditorJobs::letterDue($sat('2026-11-28')), 'Not for a 5th Sunday: never three in a month');
        $this->assertTrue(EditorJobs::letterDue($sat('2026-12-05')), 'For the 1st Sunday of December (6th)');

        \Illuminate\Support\Carbon::setTestNow($sat('2026-11-21'));
        $this->makePost(['status' => 'published', 'slug' => 'w2', 'title' => 'লেখা', 'published_at' => now()->subDays(10)]);
        Claude::$fake = fn () => ['subject' => 'চিঠি', 'preheader' => '', 'paragraphs' => ['ক'], 'closing' => 'খ', 'cta_label' => 'পড়ুন', 'cta_target' => 'home', 'pieces' => []];
        $this->assertNull(EditorJobs::newsletter(), 'Not this week');
        $this->assertSame('2026-12-06', EditorJobs::nextLetter()->toDateString());
        $this->assertNotNull(EditorJobs::newsletter(force: true), 'The owner can still ask for one');

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Newsletters\Pages\ListNewsletters::class)->assertSee('দু সপ্তাহে একবার')
            ->callAction('rhythm', ['rhythm' => 'weekly']);
        $this->assertSame('weekly', EditorJobs::rhythm());
        $this->assertTrue(EditorJobs::letterDue());

        // The month's allowance through Brevo.
        config(['mail.mailers.brevo.host' => 'smtp-relay.brevo.com', 'mail.brevo_daily_limit' => 2000, 'mail.brevo_monthly_limit' => 3]);
        \App\Models\Setting::put('mail.bulk_provider', 'brevo');
        $n = \App\Models\Newsletter::where('source', 'ai_weekly')->latest('id')->firstOrFail();
        foreach (['a', 'b'] as $x) {
            \App\Models\NewsletterDelivery::create(['newsletter_id' => $n->id, 'email' => $x.'@example.test', 'kind' => 'subscriber', 'recipient_id' => 1, 'sent_at' => now(), 'via' => 'brevo']);
        }
        $this->assertSame(1, \App\Support\BulkMail::monthLeft());
        $this->assertSame(1, \App\Support\BulkMail::remainingToday(), 'The nearer of the day\'s and the month\'s limit');
        \Illuminate\Support\Carbon::setTestNow();
    }
}
