<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Http\Controllers\CommentController;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** D89: contact and advertising forms instead of an e-mail address, with the same guards as comments. */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Page::create(['slug' => 'contact-us', 'title' => 'যোগাযোগ', 'body_html' => '<p>নিচের ফর্মে লিখুন।</p>', 'status' => 'published']);
        Page::create(['slug' => 'advertise', 'title' => 'বিজ্ঞাপন ও সহযোগিতা', 'body_html' => '<p>নিচের ফর্মে জানান।</p>', 'status' => 'published']);
    }

    private function started(): string
    {
        $this->travel(-10)->seconds();
        $t = CommentController::startedToken();
        $this->travel(10)->seconds();

        return $t;
    }

    private function contact(array $over = []): array
    {
        return array_merge(['started' => $this->started(), 'name' => 'অমিত রায়', 'email' => 'amit@example.com', 'topic' => 'correction',
            'link' => ' hatpakha.com/megh/ ', 'body' => 'দ্বিতীয় স্তবকে একটি বানান ভুল আছে, দেখে নেবেন।'], $over);
    }

    public function test_pages_show_the_form_not_an_email_button(): void
    {
        $this->get('/contact-us/')->assertOk()->assertSee('আমাদের লিখুন')->assertSee('name="website"', false)->assertSee('name="started"', false)->assertDontSee('mailto:', false);
        $this->get('/advertise/')->assertOk()->assertSee('প্রস্তাব পাঠান')->assertSee('name="organisation"', false);
    }

    public function test_a_message_is_saved_plain_and_the_admin_gets_a_notice_without_the_text(): void
    {
        $this->freezeTime();
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->post('/contact/contact/', $this->contact(['body' => 'বানান ভুল <script>alert(1)</script> আছে, দেখে নেবেন।']))
            ->assertRedirect(url('/contact-us').'/?sent='.now()->timestamp.'#form')->assertSessionHas('contact_sent');
        $m = ContactMessage::sole();
        $this->assertSame('new', $m->status);
        $this->assertSame('https://hatpakha.com/megh/', $m->link, 'A link typed without https:// still works');
        $this->assertStringNotContainsString('<script>', $m->body);
        $this->assertNotSame('127.0.0.1', $m->ip_hash, 'Never the raw IP');
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\WriterNote::class, fn ($n) => ! str_contains($n->message, 'বানান'));
        $this->get('/contact-us/')->assertSee('পৌঁছেছে!');
    }

    public function test_advertising_needs_an_organisation_and_keeps_its_details(): void
    {
        $this->post('/contact/advertise/', ['started' => $this->started(), 'name' => 'রিয়া', 'email' => 'riya@example.com', 'topic' => 'sponsor_issue', 'body' => 'পুজো সংখ্যার স্পনসর হতে চাই, খরচ জানাবেন।'])
            ->assertSessionHasErrors('organisation')->assertRedirect(url('/advertise').'/#form');
        $this->post('/contact/advertise/', ['started' => $this->started(), 'name' => 'রিয়া', 'email' => 'riya@example.com', 'topic' => 'sponsor_issue', 'organisation' => 'বইঘর প্রকাশনী',
            'phone' => '+91 98300 00000', 'when' => 'পুজো সংখ্যা ২০২৭', 'body' => 'পুজো সংখ্যার স্পনসর হতে চাই, খরচ জানাবেন।'])->assertSessionHas('contact_sent');
        $m = ContactMessage::sole();
        $this->assertSame(['advertise', 'বইঘর প্রকাশনী', 'পুজো সংখ্যা ২০২৭'], [$m->kind, $m->organisation, $m->interests['when']]);
    }

    public function test_bots_and_floods_are_stopped(): void
    {
        $this->post('/contact/contact/', $this->contact(['website' => 'http://spam.example']))->assertSessionHasErrors('body');
        $this->post('/contact/contact/', $this->contact(['started' => CommentController::startedToken()]))->assertSessionHasErrors('body');   // too fast
        $this->post('/contact/contact/', $this->contact(['topic' => 'sponsor_issue']))->assertSessionHasErrors('topic');
        $this->post('/contact/contact/', $this->contact(['body' => 'দেখুন https://a.example https://b.example https://c.example এখানে']))->assertSessionHasErrors('body');
        $this->post('/contact/contact/', $this->contact(['name' => 'www.spam.example']))->assertSessionHasErrors('name');
        $this->post('/contact/other/', $this->contact())->assertStatus(405);   // no such form
        $this->assertSame(0, ContactMessage::count());

        $this->post('/contact/contact/', $this->contact())->assertStatus(429);   // 5 a minute per connection
        $this->assertSame(0, ContactMessage::count());
        $this->travel(2)->minutes();

        // Turnstile, once switched on, must pass for this form's own action
        config(['services.turnstile' => ['site_key' => 'site', 'secret' => 'secret', 'hostnames' => 'new.hatpakha.com']]);   // as TurnstileTest: never rely on .env
        $host = 'new.hatpakha.com';
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => true, 'action' => 'comment', 'hostname' => $host])
            ->push(['success' => true, 'action' => 'contact', 'hostname' => $host])]);
        $this->post('/contact/contact/', $this->contact(['cf-turnstile-response' => 'tok']))->assertSessionHasErrors('body');
        $this->assertSame(0, ContactMessage::count(), 'A token made for another form is refused');
        $this->post('/contact/contact/', $this->contact(['cf-turnstile-response' => 'tok2']))->assertSessionHas('contact_sent');
    }

    public function test_ten_a_day_per_connection(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->travel(2)->minutes();
            $this->post('/contact/contact/', $this->contact())->assertSessionHas('contact_sent');
        }
        $this->travel(2)->minutes();
        $this->post('/contact/contact/', $this->contact())->assertSessionHasErrors('body');
        $this->assertSame(10, ContactMessage::count());
    }

    public function test_desk_inbox_reads_and_marks_messages(): void
    {
        $m = ContactMessage::create(['kind' => 'contact', 'topic' => 'question', 'name' => 'অমিত', 'email' => 'amit@example.com', 'body' => 'প্রশ্ন <b>আছে</b>']);
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(ListContactMessages::class)->assertCanSeeTableRecords([$m])
            ->callTableAction('replied', $m)->assertHasNoTableActionErrors();
        $this->assertSame('replied', $m->fresh()->status);
        $this->assertStringContainsString('প্রশ্ন &lt;b&gt;আছে&lt;/b&gt;', view('filament.partials.contact-message', ['m' => $m->fresh()])->render());
        $this->actingAs(User::factory()->create(['role' => Role::Writer]));
        $this->get(\App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl('index'))->assertForbidden();
    }

    public function test_the_sender_gets_one_text_free_receipt_a_day(): void
    {
        // D122: "we received it", without anything typed into the form, at most once a day per address.
        \Illuminate\Support\Facades\Notification::fake();
        $this->post('/contact/contact/', $this->contact(['body' => 'আপনাদের সাইটে একটা ভুল দেখলাম, ঠিক করে দেবেন।']));
        $this->post('/contact/contact/', $this->contact(['body' => 'আরও একটা কথা জানানোর ছিল, দেখে নেবেন।']));
        \Illuminate\Support\Facades\Notification::assertSentOnDemandTimes(\App\Notifications\FormReceived::class, 1);
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\FormReceived::class, function ($n, $channels, $notifiable) {
            $mail = $n->toMail($notifiable)->render();

            return $notifiable->routes['mail'] === 'amit@example.com' && str_contains($mail, 'বার্তা আমাদের কাছে পৌঁছেছে')
                && ! str_contains($mail, 'ভুল দেখলাম') && ! str_contains($mail, 'অমিত');
        });
    }
}
