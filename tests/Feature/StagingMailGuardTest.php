<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\NewsletterSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Owner, 2026-11-09: staging must never e-mail readers or writers, even with real mail settings. */
class StagingMailGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.off_live' => 'block', 'app.url' => 'https://new.hatpakha.com', 'mail.default' => 'array', 'mail.mailers.bulk.host' => 'email-smtp.ap-south-1.amazonaws.com']);
    }

    public function test_staging_delivers_only_to_desk_staff(): void
    {
        $sent = [];
        Event::listen(MessageSent::class, function (MessageSent $e) use (&$sent) { $sent[] = $e->message->getTo()[0]->getAddress(); });
        User::factory()->create(['email' => 'editor@example.test', 'role' => Role::Editor]);
        User::factory()->create(['email' => 'writer@example.test', 'role' => Role::Writer]);

        Mail::mailer('array')->raw('code 123456', fn ($m) => $m->to('Editor@example.test')->subject('code'));
        Mail::mailer('array')->raw('hello', fn ($m) => $m->to('writer@example.test')->subject('hi'));
        Mail::mailer('array')->raw('hello', fn ($m) => $m->to('stranger@example.test')->subject('hi'));
        Mail::mailer('array')->raw('hello', fn ($m) => $m->to('editor@example.test')->bcc('writer@example.test')->subject('hi'));

        $this->assertSame(['Editor@example.test'], $sent);
    }

    public function test_newsletters_never_go_out_from_staging(): void
    {
        NewsletterSubscriber::create(['email' => 'r@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        $n = Newsletter::create(['subject' => 'চিঠি', 'audience' => 'readers', 'status' => 'draft', 'items' => []]);
        NewsletterSender::queue($n, User::factory()->create(['role' => Role::Admin]));
        $this->assertFalse(NewsletterSender::realMail());
        $this->assertSame(0, NewsletterSender::batch(30));
        $this->assertSame('queued', $n->fresh()->status);
    }

    public function test_the_live_site_sends_normally(): void
    {
        config(['app.url' => 'https://hatpakha.com']);
        $sent = 0;
        Event::listen(MessageSent::class, function () use (&$sent) { $sent++; });
        Mail::mailer('array')->raw('hello', fn ($m) => $m->to('stranger@example.test')->subject('hi'));
        $this->assertSame(1, $sent);
    }
}
