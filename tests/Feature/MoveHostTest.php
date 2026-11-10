<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D90: production starts as a copy of staging; the saved staging host is rewritten everywhere, once, safely. */
class MoveHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewrites_text_and_json_only_on_the_new_site(): void
    {
        $page = Page::create(['slug' => 'about', 'title' => 'কথা', 'status' => 'published', 'body_html' => '<p><a href="https://new.hatpakha.com/kobita/">পড়ুন</a></p>']);
        $s = SocialPost::create(['kind' => 'piece', 'title' => 't', 'texts' => ['facebook' => 'পড়ুন https://new.hatpakha.com/megh/']]);

        config(['app.url' => 'https://new.hatpakha.com']);
        $this->artisan('hatpakha:move-host')->assertFailed();   // never on staging
        $this->artisan('hatpakha:move-host --dry')->expectsOutputToContain('Would change 2 rows')->assertSuccessful();
        $this->assertStringContainsString('new.hatpakha.com', $page->fresh()->body_html, 'Dry run changes nothing');

        config(['app.url' => 'https://hatpakha.com']);
        $this->artisan('hatpakha:move-host')->expectsOutputToContain('Changed 2 rows')->assertSuccessful();
        $this->assertStringContainsString('href="https://hatpakha.com/kobita/"', $page->fresh()->body_html);
        $this->assertStringNotContainsString('new.hatpakha.com', $page->fresh()->body_html);
        $this->assertSame('পড়ুন https://hatpakha.com/megh/', $s->fresh()->texts['facebook']);
        $this->artisan('hatpakha:move-host')->expectsOutputToContain('Changed 0 rows')->assertSuccessful();
    }
}
