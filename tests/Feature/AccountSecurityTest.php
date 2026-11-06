<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\User;
use App\Notifications\AccountExistsNotice;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** D59: verify before anything, no account probing, optional 2FA, safe profile pictures. */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PASS = 'Kobita-Brishti-2026x';

    private function register(array $extra = [])
    {
        $form = $this->get('/register/')->getContent();
        preg_match('/name="started" value="([^"]+)"/', $form, $m);
        $this->travel(5)->seconds();

        return $this->post('/register/', array_merge(['name' => 'নতুন লেখক', 'email' => 'new@example.test', 'password' => self::PASS,
            'password_confirmation' => self::PASS, 'terms' => '1', 'started' => html_entity_decode($m[1])], $extra));
    }

    public function test_signup_signs_in_only_through_the_email_link_and_never_reveals_existing_accounts(): void
    {
        config(['mail.default' => 'smtp']);
        Notification::fake();
        $this->register()->assertRedirect();
        $this->assertGuest();
        $user = User::firstWhere('email', 'new@example.test');
        Notification::assertSentTo($user, \App\Notifications\VerifyEmailBn::class);

        // Same address again: the same answer, and only the owner of the address is told.
        $again = $this->register(['name' => 'অন্য কেউ']);
        $again->assertRedirect();
        $this->assertSame(1, User::where('email', 'new@example.test')->count());
        Notification::assertSentTo($user, AccountExistsNotice::class);

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($link)->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);

        auth()->logout();
        $this->get($link)->assertRedirect();
        $this->assertGuest();   // a used link is not a password
    }

    public function test_unverified_account_can_do_nothing_until_verified(): void
    {
        config(['mail.default' => 'smtp']);
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $this->get('/account/')->assertRedirect();
        $this->get('/submit/')->assertRedirect();
        $this->get('/email/verify/')->assertOk()->assertSee($user->email);
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $this->post('/comment/'.$post->id, ['body' => 'ভালো লেখা'])->assertSessionHasErrors('body');
    }

    public function test_login_with_two_factor_needs_the_code_and_recovery_codes_work_once(): void
    {
        $mfa = AppAuthentication::make();
        $secret = $mfa->generateSecret();
        $user = User::factory()->create(['email' => 'safe@example.test', 'password' => self::PASS,
            'app_authentication_secret' => $secret, 'app_authentication_recovery_codes' => [Hash::make('rescue-code-1')]]);

        $this->post('/login/', ['email' => 'safe@example.test', 'password' => self::PASS])->assertRedirect();
        $this->assertGuest();
        $this->post('/login/2fa/', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/login/2fa/', ['code' => 'rescue-code-1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->fresh()->app_authentication_recovery_codes);
    }

    public function test_writer_can_turn_on_two_factor_from_the_account_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account/')->assertOk()->assertSee('অ্যাকাউন্টটি আরও নিরাপদ করুন');
        $this->post('/account/2fa/start/')->assertRedirect();
        $secret = session('2fa_setup');
        $this->get('/account/')->assertSee('QR');
        $this->post('/account/2fa/confirm/', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->post('/account/2fa/confirm/', ['code' => AppAuthentication::make()->getCurrentCode($user, $secret)])->assertSessionHas('recovery_codes');
        $this->assertSame($secret, $user->fresh()->app_authentication_secret);
        $this->get('/account/')->assertDontSee('অ্যাকাউন্টটি আরও নিরাপদ করুন');
    }

    public function test_profile_photo_and_cover_are_re_encoded_and_shown_on_the_author_page(): void
    {
        $user = User::factory()->create(['name' => 'Rina Sen']);
        $this->actingAs($user)->post('/account/profile/', [
            'name' => 'Rina Sen', 'name_bn' => 'রিনা সেন', 'bio' => 'কবিতা লিখি। <script>x</script>', 'city' => 'কলকাতা',
            'website' => 'https://rina.example', 'facebook' => 'javascript:alert(1)',
            'avatar' => UploadedFile::fake()->image('me.jpg', 600, 600), 'cover' => UploadedFile::fake()->image('c.png', 1600, 600),
        ])->assertSessionHasErrors('facebook');

        $this->post('/account/profile/', ['name' => 'Rina Sen', 'name_bn' => 'রিনা সেন', 'bio' => 'কবিতা লিখি। <script>x</script>', 'city' => 'কলকাতা',
            'website' => 'https://rina.example', 'avatar' => UploadedFile::fake()->image('me.jpg', 600, 600), 'cover' => UploadedFile::fake()->image('c.png', 1600, 600)])
            ->assertSessionHasNoErrors();
        $a = Author::firstWhere('user_id', $user->id);
        $this->assertStringStartsWith('/media/authors/', $a->avatar_path);
        $this->assertSame([480, 480], array_slice(getimagesize(public_path(ltrim($a->avatar_path, '/'))), 0, 2));
        $this->assertSame('কবিতা লিখি। x', $a->bio);

        $this->post('/account/profile/', ['name' => 'Rina Sen', 'avatar' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo 1;')])->assertSessionHasErrors('avatar');

        $this->makePost(['status' => 'published', 'slug' => 'r1', 'published_at' => now()], $a);
        $a->update(['published_count' => 1]);
        $this->get($a->url())->assertOk()->assertSee('রিনা সেন')->assertSee($a->avatar_path)->assertSee('কলকাতা')->assertSee('https://rina.example')->assertSee('প্রোফাইল সাজান');
        @unlink(public_path(ltrim($a->avatar_path, '/')));
        @unlink(public_path(ltrim($a->cover_path, '/')));
    }

    public function test_old_usernames_read_as_names(): void
    {
        $this->assertSame('Sambuddha Chatterjee', (new Author(['display_name' => 'sambuddha_chatterjee']))->publicName());
        $this->assertSame('Rabindranath', (new Author(['display_name' => 'Rabindranath']))->publicName());
    }
}
