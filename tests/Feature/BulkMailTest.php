<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\NewsletterMail;
use App\Models\Newsletter;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\NewFromWriter;
use App\Support\BulkMail;
use App\Support\NewsletterLinks;
use App\Support\NewsletterSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** D117: newsletters through Amazon SES, a daily limit, counted clicks, and SES bounce/complaint reports. */
class BulkMailTest extends TestCase
{
    use RefreshDatabase;

    private const TOPIC = 'arn:aws:sns:ap-south-1:123456789012:hatpakha-ses';

    private function letter(int $readers = 3): Newsletter
    {
        config(['mail.default' => 'smtp']);
        for ($i = 0; $i < $readers; $i++) {
            NewsletterSubscriber::create(['email' => "r{$i}@example.test", 'status' => 'subscribed', 'consented_at' => now()]);
        }
        $post = $this->makePost(['status' => 'published', 'slug' => 'megh']);
        $n = Newsletter::create(['subject' => 'চিঠি', 'audience' => 'readers', 'status' => 'draft', 'intro' => 'নমস্কার',
            'items' => [['id' => $post->id, 'title' => 'মেঘ', 'url' => $post->url(), 'line' => 'পড়ুন']]]);
        NewsletterSender::queue($n, User::factory()->create(['role' => Role::Admin, 'mail_opt_out' => true]));

        return $n;
    }

    public function test_bulk_mail_uses_the_ses_mailer_only_when_it_is_set(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.bulk.host' => null]);
        $this->assertSame('smtp', BulkMail::mailer());
        config(['mail.mailers.bulk.host' => 'email-smtp.ap-south-1.amazonaws.com']);
        $this->assertSame('bulk', BulkMail::mailer());

        $writer = \App\Models\Author::create(['slug' => 'w', 'display_name' => 'W']);
        $mail = (new NewFromWriter($this->makePost(['status' => 'published']), $writer, 'https://x.test/'))->toMail(User::factory()->make());
        $this->assertSame('bulk', $mail->mailer);
    }

    public function test_the_daily_limit_spreads_a_big_letter_over_days(): void
    {
        Mail::fake();
        config(['mail.daily_limit' => 2]);
        $n = $this->letter(3);
        $this->assertSame(2, NewsletterSender::batch(30));
        $this->assertSame(0, NewsletterSender::batch(30), 'Limit reached for today');
        $this->travel(25)->hours();
        $this->assertSame(1, NewsletterSender::batch(30));
        $this->assertSame('sent', $n->fresh()->status);
    }

    public function test_links_are_counted_once_per_person_and_never_redirect_off_site(): void
    {
        Mail::fake();
        $n = $this->letter(2);
        NewsletterSender::batch(30);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $m) => $m->deliveryId !== null
            && str_contains($m->render(), '/chithi/'.$n->id.'/'.$m->deliveryId.'/0/'));

        [$a, $b] = NewsletterDelivery::where('newsletter_id', $n->id)->pluck('id')->all();
        $link = fn ($d, $l) => NewsletterLinks::tracked($n, $d, $l);
        $utm = '?utm_source=hatpakha-chithi&utm_medium=email&utm_campaign=chithi-'.$n->id;
        $this->get($link($a, '0'))->assertRedirect($n->items[0]['url'].$utm);
        $this->get($link($a, '0'));
        $this->get($link($b, 'cta'))->assertRedirect(url('/').'/'.$utm);
        $this->get('/chithi/'.$n->id.'/'.$b.'/0/0000000000000000/')->assertRedirect($n->items[0]['url'].$utm);   // forged: goes on, not counted

        $s = NewsletterLinks::stats($n);
        $this->assertSame(2, $s['clickers']);
        $this->assertSame(['0' => 1, 'cta' => 1], $s['links']);

        $n->forceFill(['items' => [['title' => 'x', 'url' => 'https://evil.example/']]])->save();
        $this->get($link($a, '0'))->assertRedirect(url('/').'/'.$utm);
    }

    public function test_test_mails_and_previews_have_plain_links(): void
    {
        $n = Newsletter::create(['subject' => 'চিঠি', 'audience' => 'readers', 'status' => 'draft', 'items' => []]);
        $this->assertStringNotContainsString('/chithi/', (new NewsletterMail($n, url('/').'/'))->render());
    }

    /** @return array{0: string, 1: \OpenSSLAsymmetricKey} */
    private function signer(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $key), null, $key, 1);
        openssl_x509_export($cert, $pem);

        return [$pem, $key];
    }

    private function sns(array $m, $key): array
    {
        $m += ['TopicArn' => self::TOPIC, 'MessageId' => 'm1', 'Timestamp' => '2026-11-09T00:00:00Z', 'SignatureVersion' => '2',
            'SigningCertURL' => 'https://sns.ap-south-1.amazonaws.com/SimpleNotificationService-x.pem'];
        $keys = $m['Type'] === 'Notification' ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'] : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $text = '';
        foreach ($keys as $k) {
            if (isset($m[$k])) {
                $text .= $k."\n".$m[$k]."\n";
            }
        }
        openssl_sign($text, $sig, $key, OPENSSL_ALGO_SHA256);

        return $m + ['Signature' => base64_encode($sig)];
    }

    public function test_bounces_and_complaints_from_our_own_signed_topic_stop_bulk_mail(): void
    {
        config(['mail.ses_topic_arn' => self::TOPIC]);
        [$pem, $key] = $this->signer();
        Http::fake(['sns.ap-south-1.amazonaws.com/SimpleNotificationService-x.pem' => Http::response($pem), 'sns.ap-south-1.amazonaws.com/*' => Http::response('ok')]);
        $sub = NewsletterSubscriber::create(['email' => 'gone@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        $user = User::factory()->create(['email' => 'angry@example.test']);

        $bounce = json_encode(['notificationType' => 'Bounce', 'bounce' => ['bounceType' => 'Permanent', 'bouncedRecipients' => [['emailAddress' => 'Gone@example.test']]]]);
        $this->call('POST', '/webhooks/ses', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode($this->sns(['Type' => 'Notification', 'Message' => $bounce], $key)))->assertOk();
        $this->assertTrue($sub->fresh()->suppressed);

        $complaint = json_encode(['notificationType' => 'Complaint', 'complaint' => ['complainedRecipients' => [['emailAddress' => 'angry@example.test']]]]);
        $this->call('POST', '/webhooks/ses', [], [], [], [], json_encode($this->sns(['Type' => 'Notification', 'Message' => $complaint], $key)))->assertOk();
        $this->assertTrue($user->fresh()->mail_opt_out);

        // A soft bounce (mailbox full) changes nothing.
        $other = NewsletterSubscriber::create(['email' => 'full@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        $soft = json_encode(['notificationType' => 'Bounce', 'bounce' => ['bounceType' => 'Transient', 'bouncedRecipients' => [['emailAddress' => 'full@example.test']]]]);
        $this->call('POST', '/webhooks/ses', [], [], [], [], json_encode($this->sns(['Type' => 'Notification', 'Message' => $soft], $key)))->assertOk();
        $this->assertFalse($other->fresh()->suppressed);

        // The subscription is confirmed only at Amazon's own SNS host.
        $this->call('POST', '/webhooks/ses', [], [], [], [], json_encode($this->sns(['Type' => 'SubscriptionConfirmation', 'Token' => 't', 'Message' => 'confirm',
            'SubscribeURL' => 'https://sns.ap-south-1.amazonaws.com/?Action=ConfirmSubscription&Token=t'], $key)))->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'Action=ConfirmSubscription'));
    }

    public function test_forged_or_foreign_reports_are_refused(): void
    {
        config(['mail.ses_topic_arn' => self::TOPIC]);
        [$pem, $key] = $this->signer();
        [, $otherKey] = $this->signer();
        Http::fake(['*' => Http::response($pem)]);
        $sub = NewsletterSubscriber::create(['email' => 'reader@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        $bounce = json_encode(['notificationType' => 'Bounce', 'bounce' => ['bounceType' => 'Permanent', 'bouncedRecipients' => [['emailAddress' => 'reader@example.test']]]]);

        $this->call('POST', '/webhooks/ses', [], [], [], [], json_encode($this->sns(['Type' => 'Notification', 'Message' => $bounce], $otherKey)))->assertForbidden();
        $this->call('POST', '/webhooks/ses', [], [], [], [], json_encode($this->sns(['Type' => 'Notification', 'Message' => $bounce, 'TopicArn' => 'arn:aws:sns:x:1:other'], $key)))->assertForbidden();
        $this->call('POST', '/webhooks/ses', [], [], [], [], json_encode($this->sns(['Type' => 'Notification', 'Message' => $bounce, 'SigningCertURL' => 'https://evil.example/cert.pem'], $key)))->assertForbidden();
        config(['mail.ses_topic_arn' => null]);
        $this->call('POST', '/webhooks/ses', [], [], [], [], json_encode($this->sns(['Type' => 'Notification', 'Message' => $bounce], $key)))->assertForbidden();
        $this->assertFalse($sub->fresh()->suppressed);
    }

    /** Owner (2026-11-11): 149 letters failed, the desk didn't say why, and sending carried on. */
    public function test_a_failing_batch_pauses_the_letter_explains_why_and_can_be_resent(): void
    {
        $n = $this->letter(12);
        Mail::extend('broken', fn () => new class extends \Symfony\Component\Mailer\Transport\AbstractTransport
        {
            protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
            {
                throw new \RuntimeException('Expected response code "250" but got code "554", with message "554 Message rejected: Email address is not verified. The following identities failed the check in region AP-SOUTH-1: '.$message->getEnvelope()->getRecipients()[0]->getAddress().'".');
            }

            public function __toString(): string
            {
                return 'broken';
            }
        });
        config(['mail.mailers.bulk' => ['transport' => 'broken', 'host' => 'email-smtp.ap-south-1.amazonaws.com']]);

        $this->assertSame(0, NewsletterSender::batch(30));
        $n->refresh();
        $this->assertSame('cancelled', $n->status, 'Every letter in the batch failed: paused, not carried on');
        $this->assertGreaterThanOrEqual(10, $n->failed);
        $why = NewsletterSender::failures($n);
        $this->assertSame($n->failed, array_sum($why));
        $this->assertCount(1, $why, 'One cause, one line, whatever address each message names');
        $this->assertStringNotContainsString('@example.test', array_key_first($why));
        $this->assertStringContainsString('স্যান্ডবক্সে', NewsletterSender::explain(array_key_first($why)));

        Mail::fake();
        $this->assertSame(12, NewsletterSender::retry($n));
        $this->assertSame('sending', $n->fresh()->status);
        $this->assertSame(0, $n->fresh()->failed);
        NewsletterSender::batch(30);
        $this->assertSame('sent', $n->fresh()->status);
        $this->assertSame(12, $n->fresh()->sent);
    }

    /** Owner (2026-11-11): "add duplicate newsletter option". */
    public function test_a_letter_can_be_duplicated_as_a_fresh_draft(): void
    {
        Mail::fake();
        $n = $this->letter(2);
        $n->forceFill(['preheader' => 'ভেতরে সব খবর', 'closing' => 'ধন্যবাদ', 'cta_label' => 'পড়ুন'])->save();
        NewsletterSender::batch(30);
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Newsletters\Pages\EditNewsletter::class, ['record' => $n->id])->callAction('duplicate');

        $copy = Newsletter::latest('id')->first();
        $this->assertNotSame($n->id, $copy->id);
        $this->assertSame('draft', $copy->status);
        $this->assertSame([$n->subject, $n->preheader, $n->intro, $n->closing, $n->cta_label, $n->audience, $n->items],
            [$copy->subject, $copy->preheader, $copy->intro, $copy->closing, $copy->cta_label, $copy->audience, $copy->items]);
        $this->assertSame([0, 0, 0], [$copy->sent, $copy->failed, $copy->recipients]);
        $this->assertSame(0, $copy->deliveries()->count());
        $this->assertSame('sent', $n->fresh()->status, 'The original is untouched');
    }

    /** Owner (2026-11-11): both Amazon SES and Brevo in ENV_FILE, the road chosen in /desk; each with its own daily limit. */
    public function test_the_owner_chooses_ses_or_brevo_and_each_keeps_its_own_daily_limit(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.bulk.host' => 'email-smtp.ap-south-1.amazonaws.com', 'mail.mailers.brevo.host' => null]);
        $this->assertSame(['ses'], BulkMail::available());
        $this->assertSame('bulk', BulkMail::mailer());

        config(['mail.mailers.brevo.host' => 'smtp-relay.brevo.com', 'mail.brevo_daily_limit' => 3, 'mail.daily_limit' => 0]);
        $this->assertSame(['ses', 'brevo'], BulkMail::available());
        $this->assertSame('ses', BulkMail::provider(), 'Amazon SES stays first until the owner picks');

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Newsletters\Pages\ListNewsletters::class)
            ->assertActionVisible('provider')
            ->callAction('provider', ['provider' => 'brevo']);
        $this->assertSame('brevo', BulkMail::provider());
        $this->assertSame('brevo', BulkMail::mailer());
        $this->assertDatabaseHas('audit_logs', ['action' => 'mail.provider']);

        Mail::fake();
        $n = $this->letter(5);
        $this->assertSame(3, NewsletterSender::batch(30), 'Brevo: 3 a day here');
        $this->assertSame(3, $n->deliveries()->where('via', 'brevo')->count());
        $this->assertSame(0, NewsletterSender::batch(30));
        $this->assertNull(BulkMail::remainingToday('ses'), 'Amazon SES has its own (here: no) limit');

        BulkMail::choose('ses', $admin->id);
        $this->assertSame($n->fresh()->recipients - 3, NewsletterSender::batch(30), 'Switching road carries on with the same letter');
        $this->assertSame('sent', $n->fresh()->status);
        $this->assertSame(3, $n->deliveries()->where('via', 'brevo')->count());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        BulkMail::choose('postmark', $admin->id);   // only a road that is set up
    }

    /** Gmail's one-click unsubscribe (RFC 8058) POSTs to the List-Unsubscribe link, without a form token. */
    public function test_one_click_unsubscribe_by_post_works_and_needs_the_signature(): void
    {
        $sub = NewsletterSubscriber::create(['email' => 'one@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        $url = NewsletterSender::unsubscribeUrl('subscriber', $sub->id);
        $this->post(preg_replace('/signature=[a-f0-9]+/', 'signature=00', $url), ['List-Unsubscribe' => 'One-Click'])->assertForbidden();
        $this->assertSame('subscribed', $sub->fresh()->status);
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertSame('unsubscribed', $sub->fresh()->status);

        $writer = User::factory()->create(['role' => Role::Writer]);
        $this->post(NewsletterSender::unsubscribeUrl('writer', $writer->id), ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertTrue((bool) $writer->fresh()->mail_opt_out);
    }

    /** From the owner's test (2026-11-11): Brevo rewrote links and added a pixel; the text part showed &amp; in links. */
    public function test_letters_ask_for_no_provider_tracking_and_have_clean_text_links(): void
    {
        $n = Newsletter::create(['subject' => 'চিঠি', 'audience' => 'readers', 'status' => 'draft', 'items' => [], 'intro' => 'নমস্কার']);
        $real = new NewsletterMail($n, NewsletterSender::unsubscribeUrl('subscriber', 1), 5);
        $h = $real->headers()->text;
        $this->assertSame('0', $h['X-Mailin-Track']);
        $this->assertStringContainsString('unsubscribe', $h['List-Unsubscribe']);
        $test = (new NewsletterMail($n, url('/').'/'))->headers()->text;
        $this->assertArrayNotHasKey('List-Unsubscribe', $test, 'A test letter has no real unsubscribe link, so no one-click header');

        $text = view('emails.newsletter-text', ['newsletter' => $n, 'unsubscribeUrl' => url('/').'/', 'deliveryId' => null])->render();
        $this->assertStringContainsString('utm_medium=email&utm_campaign', $text);
        $this->assertStringNotContainsString('&amp;', $text);
    }
}
