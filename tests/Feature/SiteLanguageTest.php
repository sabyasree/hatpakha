<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D169 (owner): sign-up, sign-in, account and menus in plain Bengali or English, each person's choice. */
class SiteLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_switch_the_sign_in_pages_to_english_and_back(): void
    {
        $this->get('/login/')->assertOk()->assertSee('সাইন ইন')->assertSee('English')->assertSee('<html lang="bn">', false);

        $switch = $this->get('/language/en/?back='.rawurlencode('/login/'))->assertRedirect('/login/');
        $cookie = collect($switch->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'hp_lang');
        $this->assertNotNull($cookie, 'The choice is remembered in a cookie');

        $this->withCookie('hp_lang', 'en')->get('/login/')->assertOk()
            ->assertSee('Sign in')->assertSee('Forgot password?')->assertSee('বাংলা')->assertSee('<html lang="en">', false)
            ->assertDontSee('পাসওয়ার্ড ভুলে গেছেন?');
        $this->withCookie('hp_lang', 'en')->get('/register/')->assertOk()->assertSee('Sign up');
    }

    public function test_guests_public_pages_stay_bengali_because_they_are_shared_from_the_cache(): void
    {
        $this->makePost(['slug' => 'pub']);
        $this->withCookie('hp_lang', 'en')->get('/pub/')->assertOk()->assertSee('সাইন ইন')->assertDontSee('>Sign in<', false);
    }

    public function test_a_signed_in_person_keeps_their_choice_on_the_account_and_menus(): void
    {
        $reader = User::factory()->create(['role' => Role::Reader, 'name' => 'Mita Sen']);
        $this->actingAs($reader)->get('/language/en/?back=/account/reading/')->assertRedirect('/account/reading/');
        $this->assertSame('en', $reader->fresh()->site_locale);

        $this->get('/account/reading/')->assertOk()->assertSee('Library')->assertSee('Profile')->assertSee('Settings')->assertSee('Log out');
        $this->makePost(['slug' => 'pub']);
        $this->get('/pub/')->assertOk()->assertSee('Account')->assertSee('Menu');   // their pages are never shared

        $this->get('/language/bn/?back=/account/settings/')->assertRedirect('/account/settings/');
        $this->assertNull($reader->fresh()->site_locale);
        $this->get('/account/settings/')->assertSee('সেটিংস')->assertSee('লগ আউট');
    }

    public function test_the_switch_only_goes_back_to_this_site(): void
    {
        $this->get('/language/en/?back='.rawurlencode('//evil.example/x'))->assertRedirect('/');
        $this->get('/language/en/?back='.rawurlencode('https://evil.example/'))->assertRedirect('/');
        $this->get('/language/fr/')->assertNotFound();
    }
}
