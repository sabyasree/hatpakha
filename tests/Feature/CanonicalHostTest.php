<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D125: on the live site every page has exactly one address, https://hatpakha.com/… (found on production). */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_www_and_hosting_folders_redirect_to_the_one_address(): void
    {
        config(['app.url' => 'https://hatpakha.com']);
        $this->makePost(['status' => 'published', 'slug' => 'megh', 'published_at' => now()->subDay()]);

        $this->get('https://www.hatpakha.com/megh/?x=1')->assertStatus(301)->assertRedirect('https://hatpakha.com/megh/?x=1');
        $this->call('GET', 'https://hatpakha.com/site/megh/', [], [], [], ['SCRIPT_NAME' => '/site/index.php', 'SCRIPT_FILENAME' => base_path('public/index.php'), 'REQUEST_URI' => '/site/megh/'])
            ->assertStatus(301)->assertRedirect('https://hatpakha.com/megh/');
        $this->get('https://hatpakha.com/megh/')->assertOk();
    }

    public function test_staging_reached_through_the_main_domain_sends_readers_to_the_real_site(): void
    {
        config(['app.url' => 'https://new.hatpakha.com']);
        $this->call('GET', 'https://hatpakha.com/new/megh/', [], [], [], ['SCRIPT_NAME' => '/new/index.php', 'SCRIPT_FILENAME' => base_path('public/index.php'), 'REQUEST_URI' => '/new/megh/'])
            ->assertStatus(301)->assertRedirect('https://hatpakha.com/megh/');
    }

    public function test_staging_and_local_are_left_alone(): void
    {
        config(['app.url' => 'https://new.hatpakha.com']);
        $this->get('https://new.hatpakha.com/')->assertOk();
        config(['app.url' => 'http://127.0.0.1:8123']);
        $this->get('http://localhost/')->assertOk();
    }

    public function test_the_home_page_canonical_ends_with_a_slash(): void
    {
        config(['app.url' => 'https://hatpakha.com']);
        $this->get('https://hatpakha.com/')->assertSee('<link rel="canonical" href="https://hatpakha.com/">', false);
        $this->assertSame('https://hatpakha.com/sitemap.xml', \App\Support\Url::to('/sitemap.xml'));
    }
}
