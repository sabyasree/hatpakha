<?php

namespace Tests\Feature;

use App\Support\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D60: Turnstile passes only for success + the form's action + our hostname; everything else fails closed. */
class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.turnstile' => ['site_key' => '0x4AAAAAAF1_eC6U92vDq8HQ', 'secret' => 'test-secret', 'hostnames' => 'new.hatpakha.com, hatpakha.com']]);
    }

    private function reply(array $body, int $status = 200): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response($body, $status)]);
    }

    public function test_good_token_for_this_form_and_host_passes_and_is_sent_correctly(): void
    {
        $this->reply(['success' => true, 'action' => 'login', 'hostname' => 'new.hatpakha.com']);
        $this->assertTrue(Turnstile::passes('tok', '1.2.3.4', 'login'));
        Http::assertSent(fn ($r) => $r->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $r['secret'] === 'test-secret' && $r['response'] === 'tok' && $r['remoteip'] === '1.2.3.4' && $r->isForm());
    }

    public function test_wrong_action_wrong_host_or_failure_is_refused(): void
    {
        $this->reply(['success' => true, 'action' => 'newsletter', 'hostname' => 'new.hatpakha.com']);
        $this->assertFalse(Turnstile::passes('tok', null, 'login'));
        $this->reply(['success' => true, 'action' => 'login', 'hostname' => 'evil.example']);
        $this->assertFalse(Turnstile::passes('tok', null, 'login'));
        $this->reply(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);   // replayed token
        $this->assertFalse(Turnstile::passes('tok', null, 'login'));
    }

    public function test_fails_closed_on_errors_and_bad_input(): void
    {
        $this->reply(['success' => true, 'action' => 'login', 'hostname' => 'hatpakha.com'], 500);
        $this->assertFalse(Turnstile::passes('tok', null, 'login'));
        Http::fake(['challenges.cloudflare.com/*' => fn () => throw new ConnectionException('down')]);
        $this->assertFalse(Turnstile::passes('tok', null, 'login'));
        $this->reply(['success' => true, 'action' => 'login', 'hostname' => 'hatpakha.com']);
        $this->assertFalse(Turnstile::passes('', null, 'login'));
        $this->assertFalse(Turnstile::passes(str_repeat('a', 2049), null, 'login'));
        $this->assertFalse(Turnstile::passes('tok', null, 'not-a-form'));
        config(['services.turnstile.hostnames' => '']);
        $this->assertFalse(Turnstile::passes('tok', null, 'login'), 'No allowlist: refuse');
    }

    public function test_forms_render_the_widget_with_their_action(): void
    {
        $this->get('/login/')->assertSee('data-action="login"', false)->assertSee('0x4AAAAAAF1_eC6U92vDq8HQ');
        $this->get('/register/')->assertSee('data-action="register"', false);
        $this->get('/forgot-password/')->assertSee('data-action="forgot"', false);
        $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $this->get('/p/')->assertSee('data-action="comment"', false)->assertSee('data-action="newsletter"', false)->assertSee('cf-turnstile-lazy', false);
    }

    public function test_login_is_refused_without_a_valid_token(): void
    {
        $this->reply(['success' => false]);
        $this->post('/login/', ['email' => 'a@example.test', 'password' => 'whatever-123'])->assertSessionHasErrors('email');
    }
}
