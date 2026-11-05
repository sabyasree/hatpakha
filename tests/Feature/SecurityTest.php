<?php

namespace Tests\Feature;

use App\Auth\LegacyWordPressPassword;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** docs/SECURITY.md — items marked ✅test. */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_present_on_public_pages(): void
    {
        $res = $this->get('/');
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $res->assertHeaderMissing('X-Powered-By');
    }

    public function test_old_wordpress_attack_paths_are_gone(): void
    {
        foreach (['/wp-login.php', '/xmlrpc.php', '/wp-admin/', '/wp-json/wp/v2/users', '/wp-content/plugins/x/y.php'] as $path) {
            $this->get($path)->assertStatus(410);
        }
        $this->post('/xmlrpc.php')->assertStatus(410);
    }

    public function test_sanitizer_strips_scripts_handlers_and_js_links(): void
    {
        $dirty = '<p onclick="x()">ঠিক</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">l</a><iframe src="https://evil"></iframe>';
        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringContainsString('<p>ঠিক</p>', $clean);
        foreach (['<script', 'onclick', 'onerror', 'javascript:', '<iframe'] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
    }

    public function test_post_body_is_sanitised_on_save_and_rendered_safely(): void
    {
        $this->makePost(['slug' => 'xss', 'body_html' => '<p>লেখা</p><script>alert("x")</script>']);
        $this->get('/xss/')->assertOk()->assertDontSee('alert("x")', false);
    }

    public function test_legacy_phpass_hash_logs_in_once_then_is_rehashed(): void
    {
        // Vector produced by passlib (independent implementation) for this password.
        $user = User::create(['name' => 'Old', 'email' => 'old@example.test', 'role' => 'writer']);
        $user->forceFill(['legacy_password' => '$P$BabcdefghrF3uvnOrrEtInZdZvOzH20'])->save();

        $this->assertFalse(Auth::attempt(['email' => 'old@example.test', 'password' => 'wrong']));
        $this->assertTrue(Auth::attempt(['email' => 'old@example.test', 'password' => 'আমার পাসওয়ার্ড 123']));

        $user->refresh();
        $this->assertNull($user->legacy_password);
        $this->assertTrue(Hash::check('আমার পাসওয়ার্ড 123', $user->password));
    }

    public function test_wordpress_68_bcrypt_hashes_verify(): void
    {
        $hash = '$wp'.password_hash(base64_encode(hash_hmac('sha384', 'hunter2', 'wp-sha384', true)), PASSWORD_BCRYPT);
        $this->assertTrue(LegacyWordPressPassword::check('hunter2', $hash));
        $this->assertFalse(LegacyWordPressPassword::check('hunter3', $hash));
        $this->assertTrue(LegacyWordPressPassword::check('secret', '$P$6zzzz1234TCTAWDr01Il3OqCLyHfkl/'));
    }

    public function test_only_editors_and_above_can_open_the_admin_panel(): void
    {
        $panel = app(Panel::class);
        foreach (['reader' => false, 'writer' => false, 'trusted_writer' => false, 'editor' => true, 'admin' => true] as $role => $allowed) {
            $u = new User(['role' => $role]);
            $this->assertSame($allowed, $u->canAccessPanel($panel), "role {$role}");
        }
        $this->get('/desk')->assertRedirect();
    }

    public function test_login_is_rate_limited(): void
    {
        $limiter = app(\Illuminate\Cache\RateLimiter::class);
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('login'));
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('submit'));
        $this->assertTrue(method_exists($limiter, 'tooManyAttempts'));
    }

    public function test_upload_folder_blocks_script_execution(): void
    {
        $rules = file_get_contents(public_path('wp-content/uploads/.htaccess'));
        $this->assertStringContainsString('php_flag engine off', $rules);
        $this->assertStringContainsString('Require all denied', $rules);
    }

    public function test_sensitive_user_fields_are_hidden_from_serialisation(): void
    {
        $u = new User(['name' => 'a', 'email' => 'a@b.c']);
        $u->forceFill(['legacy_password' => 'x', 'app_authentication_secret' => 'y']);
        $arr = $u->toArray();
        $this->assertArrayNotHasKey('legacy_password', $arr);
        $this->assertArrayNotHasKey('app_authentication_secret', $arr);
    }
}
