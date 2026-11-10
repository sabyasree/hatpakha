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
}
