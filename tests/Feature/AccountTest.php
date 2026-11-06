<?php

namespace Tests\Feature;

use App\Auth\LegacyWordPressPassword;
use App\Enums\Role;
use App\Models\Author;
use App\Models\User;
use App\Notifications\ResetPasswordBn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Phase 3a writer accounts (docs/SECURITY.md §2). */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function started(int $secondsAgo = 10): string
    {
        return Crypt::encryptString((string) (now()->timestamp - $secondsAgo));
    }

    public function test_register_login_logout_and_account_page(): void
    {
        $this->get('/register/')->assertOk()->assertSee('noindex', false);
        $this->post('/register/', ['name' => 'রিনা দাস', 'email' => 'Rina@Example.com', 'password' => 'kobita12345', 'password_confirmation' => 'kobita12345', 'terms' => '1', 'started' => $this->started()])
            ->assertRedirect(url('/account').'/');
        $user = User::where('email', 'rina@example.com')->first();
        $this->assertSame(Role::Reader, $user->role);
        $this->get('/account/')->assertOk()->assertSee('আমার লেখা')->assertSee('রিনা দাস');

        $this->post('/logout/')->assertRedirect(url('/').'/');
        $this->get('/account/')->assertRedirect(url('/login'));
        $this->post('/login/', ['email' => 'rina@example.com', 'password' => 'wrong-pass1'])->assertSessionHasErrors('email');
        $this->post('/login/', ['email' => 'RINA@example.com', 'password' => 'kobita12345'])->assertRedirectContains('/account');
    }

    public function test_bots_are_stopped_by_honeypot_and_speed_check(): void
    {
        $base = ['name' => 'Bot', 'email' => 'bot@example.com', 'password' => 'kobita12345', 'password_confirmation' => 'kobita12345', 'terms' => '1'];
        $this->post('/register/', $base + ['website' => 'http://spam', 'started' => $this->started()])->assertSessionHasErrors('email');
        $this->post('/register/', $base + ['started' => $this->started(1)])->assertSessionHasErrors('email');
        $this->post('/register/', $base + ['started' => 'forged'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_old_wordpress_password_logs_in_and_is_upgraded(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->forceFill(['password' => null, 'legacy_password' => '$P$6zzzz1234TCTAWDr01Il3OqCLyHfkl/'])->save();

        $this->post('/login/', ['email' => 'old@example.com', 'password' => 'secret'])->assertRedirect(url('/account').'/');
        $this->assertNull($user->fresh()->legacy_password);
    }

    public function test_password_reset_does_not_reveal_accounts_and_works(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'w@example.com']);
        $same = 'এই ইমেলে অ্যাকাউন্ট থাকলে';
        $this->post('/forgot-password/', ['email' => 'nobody@example.com'])->assertSessionHas('status', fn ($s) => str_contains($s, $same));
        $this->post('/forgot-password/', ['email' => 'w@example.com'])->assertSessionHas('status', fn ($s) => str_contains($s, $same));

        $token = null;
        Notification::assertSentTo($user, ResetPasswordBn::class, function ($n) use (&$token) { $token = $n->token; return true; });
        $this->post('/reset-password/', ['token' => $token, 'email' => 'w@example.com', 'password' => 'notunpass123', 'password_confirmation' => 'notunpass123'])
            ->assertRedirect(url('/login').'/');
        $this->post('/login/', ['email' => 'w@example.com', 'password' => 'notunpass123'])->assertRedirect(url('/account').'/');
    }

    public function test_account_lists_only_my_writings(): void
    {
        $me = User::factory()->create();
        $mine = $this->makePost(['slug' => 'mine', 'title' => 'আমার কবিতা', 'status' => 'pending']);
        $mine->authors()->sync([Author::create(['slug' => 'me', 'display_name' => 'Me', 'user_id' => $me->id])->id]);
        $this->makePost(['slug' => 'theirs', 'title' => 'অন্যের লেখা']);

        $this->actingAs($me)->get('/account/')->assertOk()->assertSee('আমার কবিতা')->assertSee('জমা পড়েছে')->assertDontSee('অন্যের লেখা');
    }

    public function test_every_post_status_has_a_bengali_label(): void
    {
        $this->assertEqualsCanonicalizing(\App\Models\Post::STATUSES, array_keys(\App\Filament\Resources\Posts\Tables\PostsTable::STATUS));
        $this->assertEqualsCanonicalizing(\App\Models\Post::STATUSES, array_keys(\App\Http\Controllers\AccountController::STATUS));
    }

    public function test_old_login_url_is_a_real_page_again(): void
    {
        $this->get('/login/')->assertOk()->assertSee('লগ-ইন');
    }
}
