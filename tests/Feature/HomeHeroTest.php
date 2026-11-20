<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** The home page's pop-up magazine (D242): five headlines, one real heading, drawn by the page itself. */
class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_real_heading_and_four_decorative_ones(): void
    {
        Cache::flush();
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'the page keeps a single h1');
        $this->assertMatchesRegularExpression('/<h1><b[^>]*>বাংলা সাহিত্যের<\/b> <b[^>]*>নতুন হাওয়া<\/b><\/h1>/u', $html);
        foreach (['লিখুন প্রাণ খুলে', 'মন জুড়োক', 'মন ভালো করা', 'অবসরের সঙ্গী'] as $line) {
            $this->assertStringContainsString($line, $html);
        }
        $this->assertSame(4, substr_count($html, '<p aria-hidden="true"><b class="l1"'), 'the other headlines are hidden from screen readers');
    }

    public function test_it_is_light_and_still_for_readers_who_switch_motion_off(): void
    {
        Cache::flush();
        $html = $this->get('/')->assertOk()->getContent();
        $hero = substr($html, strpos($html, 'data-hero'), 40000);

        $this->assertStringNotContainsString('<img', substr($hero, 0, strpos($hero, '</section>')), 'no picture files in the hero');
        $this->assertStringContainsString('prefers-reduced-motion: no-preference', $html);
        $this->assertStringContainsString('aria-hidden="true"', $hero);
        // Owner: no "since 2012" in the hero.
        $this->assertStringNotContainsString('২০১২', substr($hero, 0, strpos($hero, '</section>')));
        $this->assertStringNotContainsString('বছর ধরে', substr($hero, 0, strpos($hero, '</section>')));
    }
}
