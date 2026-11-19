<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D235 (owner: "Can I do Google login? ... build it"). Security rules of the flow: docs/SECURITY.md §2. */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'cid.apps.googleusercontent.com', 'services.google.client_secret' => 'sec']);
    }

    /** Start at /login/google/ and come back as Google would, with these claims in the ID token. */
    private function back(array $claims = [], ?string $state = null)
    {
        $to = $this->get('/login/google/')->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($to, PHP_URL_QUERY), $q);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $to);
        $this->assertSame([url('/login/google/callback').'/', 'openid email profile', 'code'], [$q['redirect_uri'], $q['scope'], $q['response_type']]);
        $claims += ['iss' => 'https://accounts.google.com', 'aud' => 'cid.apps.googleusercontent.com', 'exp' => time() + 600, 'nonce' => $q['nonce'],
            'sub' => 'g-100', 'email' => 'Rina@Gmail.com', 'email_verified' => true, 'name' => 'রিনা <b>বসু</b>'];
        $b64 = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');
        Http::swap(new \Illuminate\Http\Client\Factory);   // a fake set earlier would answer again with its old nonce
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['id_token' => $b64(['alg' => 'RS256']).'.'.$b64($claims).'.sig'])]);

        return $this->get('/login/google/callback/?code=abc&state='.($state ?? $q['state']));
    }

    public function test_a_new_person_gets_a_verified_account_in_one_step_and_comes_back_to_the_same_one(): void
    {
        $this->get('/login/')->assertSee('Google দিয়ে ঢুকুন');
        $this->get('/register/')->assertSee('Google দিয়ে অ্যাকাউন্ট খুলুন');
        $this->back()->assertRedirect(url('/account').'/');
        $user = User::where('email', 'rina@gmail.com')->firstOrFail();
        $this->assertSame(['রিনা বসু', 'g-100', Role::Reader], [$user->name, $user->google_id, $user->role]);
        $this->assertNotNull($user->email_verified_at, 'Google vouches for the address: no verification e-mail');
        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn ($r) => $r['client_secret'] === 'sec' && $r['code'] === 'abc' && $r['grant_type'] === 'authorization_code');

        auth()->logout();
        $this->back(['email' => 'new-address@gmail.com'])->assertRedirect(url('/account').'/');   // known by the Google account, whatever its address now
        $this->assertSame(1, User::count());
    }

    public function test_nothing_but_a_sound_answer_from_google_signs_anyone_in(): void
    {
        $this->back([], 'someone-elses-state')->assertRedirect(url('/login').'/');
        $this->get('/login/google/callback/?code=abc&state=x')->assertRedirect(url('/login').'/');   // never started here
        foreach ([['email_verified' => false], ['aud' => 'another-app'], ['iss' => 'https://evil.example'], ['exp' => time() - 5], ['nonce' => 'replayed']] as $bad) {
            $this->back($bad)->assertRedirect(url('/login').'/')->assertSessionHasErrors('email');
        }
        $this->assertGuest();
        $this->assertSame(0, User::count());

        config(['services.google.client_secret' => null]);
        $this->get('/login/google/')->assertNotFound();
        $this->get('/login/')->assertDontSee('Google দিয়ে ঢুকুন');
    }

    public function test_an_existing_account_is_joined_safely_and_the_desk_is_never_opened(): void
    {
        // Verified already: the same account, password untouched.
        $known = User::factory()->create(['email' => 'rina@gmail.com', 'password' => 'old-password-123', 'email_verified_at' => now()]);
        $this->back()->assertRedirect(url('/account').'/');
        $this->assertAuthenticatedAs($known);
        $this->assertSame('g-100', $known->fresh()->google_id);
        $this->assertTrue(Hash::check('old-password-123', $known->fresh()->password));
        auth()->logout();

        // Never verified (a stranger may have opened it with this address): the old password stops working.
        $squat = User::factory()->create(['email' => 'mita@gmail.com', 'password' => 'strangers-password-1', 'email_verified_at' => null]);
        $this->back(['sub' => 'g-200', 'email' => 'mita@gmail.com'])->assertRedirect(url('/account').'/');
        $this->assertFalse(Hash::check('strangers-password-1', $squat->fresh()->password));
        $this->assertNotNull($squat->fresh()->email_verified_at);
        auth()->logout();

        // An editor's account: password and second step only.
        $editor = User::factory()->create(['email' => 'editor@gmail.com', 'role' => Role::Admin, 'email_verified_at' => now()]);
        $this->back(['sub' => 'g-300', 'email' => 'editor@gmail.com'])->assertRedirect(url('/login').'/')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($editor->fresh()->google_id);

        // A reader with two-step sign-in on is still asked for the code.
        $careful = User::factory()->create(['email' => 'careful@gmail.com', 'email_verified_at' => now()]);
        $careful->forceFill(['app_authentication_secret' => 'SECRET'])->save();
        $this->back(['sub' => 'g-400', 'email' => 'careful@gmail.com'])->assertRedirect(url('/login/2fa').'/');
        $this->assertGuest();

        // An account the owner marked as a bot stays out.
        $bot = User::factory()->create(['email' => 'bot@gmail.com', 'email_verified_at' => now(), 'bot_review' => 'bot']);
        $this->back(['sub' => 'g-500', 'email' => 'bot@gmail.com']);
        $this->assertGuest();
    }
}
