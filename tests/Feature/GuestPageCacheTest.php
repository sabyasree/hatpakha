<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D65: guests' public pages are shareable by Cloudflare (no cookies); everyone else's never are. */
class GuestPageCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_guest_visit_is_cacheable_and_cookie_free(): void
    {
        $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $res = $this->get('/p/');
        $res->assertOk();
        $this->assertStringContainsString('s-maxage=600', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $res->headers->get('Cache-Control'));
        // A browser never shows a stale guest copy (a reader who just signed in would see it); only the edge may.
        $this->assertStringContainsString('must-revalidate', $res->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('stale-while-revalidate', $res->headers->get('Cache-Control'));
        $this->assertSame('max-age=600, stale-while-revalidate=60, stale-if-error=86400', $res->headers->get('CDN-Cache-Control'));
        $this->assertSame(2, substr_count($this->get('/sw.js')->getContent(), "credentials: 'omit', cache: 'no-store'"), 'The app\'s guest copies never enter the browser cache');
        $this->assertSame([], $res->headers->getCookies(), 'No session cookie on a shareable page');
        $this->assertStringContainsString('s-maxage=300', $this->get('/')->headers->get('Cache-Control'));
    }

    public function test_signed_in_and_private_pages_are_never_shared(): void
    {
        $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $this->assertStringNotContainsString('public', (string) $this->withCookie(config('session.cookie'), 'x')->get('/p/')->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $this->actingAs(User::factory()->create())->get('/p/')->headers->get('Cache-Control'));
        auth()->logout();
        $this->assertStringNotContainsString('public', (string) $this->get('/login/')->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $this->get('/search/?q=ab')->headers->get('Cache-Control'));
        $token = $this->get('/csrf-token/');
        $token->assertOk()->assertJsonStructure(['token']);
        $this->assertStringContainsString('no-store', $token->headers->get('Cache-Control'));
    }
}
