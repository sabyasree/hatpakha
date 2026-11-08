<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Section;
use App\Models\Serial;
use App\Support\HomeRotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Owner (2026-11-08): the home shelves change daily instead of showing the same pieces until something new appears. */
class HomeRotationTest extends TestCase
{
    use RefreshDatabase;

    private array $old = [];

    protected function setUp(): void
    {
        parent::setUp();
        $sec = Section::updateOrCreate(['slug' => 'short-stories'], ['name_bn' => 'গল্প', 'format' => 'prose']);
        foreach (range(1, 30) as $i) {
            $this->old[] = $this->makePost(['slug' => 'old-'.$i, 'title' => 'পুরনো গল্প '.$i, 'format' => 'prose', 'section_id' => $sec->id, 'published_at' => now()->subYears(3)->subDays($i)])->id;
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_pieces_first_then_archive_that_changes_daily_without_repeats(): void
    {
        $sec = Section::where('slug', 'short-stories')->first();
        $new = $this->makePost(['slug' => 'fresh', 'title' => 'নতুন গল্প', 'format' => 'prose', 'section_id' => $sec->id, 'published_at' => now()->subDays(2)])->id;

        $day1 = HomeRotation::today();
        $this->assertSame($new, $day1['stories'][0], 'A piece from the last three weeks comes first');
        $this->assertContains($new, $day1['fresh']);
        $this->assertCount(4 + HomeRotation::SPARE, $day1['stories'], 'Four shown plus spares for pieces this reader has read');
        $this->assertSame($day1, HomeRotation::today(), 'The same all day, so every cached copy agrees');

        Carbon::setTestNow(now()->addDay());
        $day2 = HomeRotation::today();
        $this->assertSame($new, $day2['stories'][0]);
        $this->assertEmpty(array_intersect(array_slice($day1['stories'], 1, 3), array_slice($day2['stories'], 1, 3)), 'Archive pieces shown yesterday rest for two weeks');
    }

    public function test_home_page_marks_new_and_archive_pieces_and_offers_spares(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('আর্কাইভ থেকে', $html);
        $this->assertStringContainsString('data-rotate="4"', $html);
        $this->assertSame(4 + HomeRotation::SPARE, substr_count(explode('data-rotate="3"', explode('data-rotate="4"', $html)[1])[0], 'data-pid='));
    }

    public function test_serials_are_featured_near_the_top_with_the_newest_episode(): void
    {
        $sec = Section::firstOrCreate(['slug' => 'dharabahik'], ['name_bn' => 'ধারাবাহিক', 'format' => 'prose']);
        $s = Serial::create(['title' => 'ছায়ার বাড়ি', 'logline' => 'রাতে টোকা পড়ে।', 'genre' => 'অলৌকিক', 'status' => 'active', 'slug' => 'chhayar-bari', 'day' => 6]);
        $this->makePost(['slug' => 'ep1', 'title' => 'ছায়ার বাড়ি (পর্ব ১): বন্ধ দরজা', 'serial_id' => $s->id, 'episode_no' => 1, 'section_id' => $sec->id, 'published_at' => now()->subDays(10)]);
        $this->makePost(['slug' => 'ep2', 'title' => 'ছায়ার বাড়ি (পর্ব ২): দ্বিতীয় রাত', 'serial_id' => $s->id, 'episode_no' => 2, 'section_id' => $sec->id, 'published_at' => now()->subDay()]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('পর্ব ২: দ্বিতীয় রাত', $html, 'Straight to the newest episode');
        $this->assertStringContainsString('নতুন পর্ব', $html);
        $this->assertLessThan(strpos($html, 'গল্পের পাতা'), strpos($html, 'id="serials-h"'), 'Serials come before the story shelf');
    }
}
