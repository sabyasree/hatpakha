<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The archive redesign the owner approved (2026-11-12): issues, collections, years with pieces only, the year page, /sonkha/. */
class ArchivePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_shows_the_newest_issue_large_the_older_ones_small_and_only_years_with_pieces(): void
    {
        $this->makePost(['slug' => 'a', 'published_at' => '2019-03-07 10:00:00']);
        $this->makePost(['slug' => 'b', 'published_at' => '2021-05-01 10:00:00']);
        $old = Issue::create(['slug' => '2020-10', 'title' => 'শারদীয়া ১৪২৭', 'status' => 'published', 'published_at' => '2020-10-01']);
        $new = Issue::create(['slug' => '2026-11', 'title' => 'হেমন্ত সংখ্যা ১৪৩৩', 'status' => 'published', 'published_at' => now()->subDays(3)]);
        Issue::create(['slug' => '2026-12', 'title' => 'পৌষ সংখ্যা', 'status' => 'proposed']);   // not out: never listed
        \Illuminate\Support\Facades\Cache::flush();

        $this->get('/archive/')->assertOk()
            ->assertSeeInOrder(['সংখ্যা', 'হেমন্ত সংখ্যা ১৪৩৩', 'নতুন সংখ্যা', 'আগের সংখ্যা', 'শারদীয়া ১৪২৭', 'বছর ধরে', '২০২১', '২০১৯', 'বিভাগ ধরে'])
            ->assertDontSee('পৌষ সংখ্যা')->assertDontSee(url('/archive/2020').'/', false)   // no year without published pieces
            ->assertSee(url('/sonkha').'/', false)->assertSee('name="q"', false);

        $this->get('/sonkha/')->assertOk()->assertSeeInOrder(['সব সংখ্যা', '২০২৬', 'হেমন্ত সংখ্যা ১৪৩৩', '২০২০', 'শারদীয়া ১৪২৭'])
            ->assertDontSee('পৌষ সংখ্যা')->assertDontSee('noindex', false)
            ->assertSee('<link rel="canonical" href="'.url('/sonkha').'/">', false);
        $this->get('/sitemap-sections.xml')->assertSee(url('/sonkha').'/');
        $this->get('/sonkha/'.$new->slug.'/')->assertOk();   // each issue keeps its address
    }

    public function test_year_page_filters_by_section_without_being_indexed_and_shows_the_most_read(): void
    {
        $poems = Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'format' => 'poem']);
        $stories = Section::firstOrCreate(['slug' => 'short-stories'], ['name_bn' => 'গল্প', 'format' => 'story']);
        $read = $this->makePost(['slug' => 'pora', 'title' => 'সবচেয়ে পড়া গল্প', 'section_id' => $stories->id, 'published_at' => '2019-01-24 10:00:00', 'featured_image_path' => 'media/x.jpg']);
        $this->makePost(['slug' => 'kobita', 'title' => 'সাগরের ডাকবাক্স', 'section_id' => $poems->id, 'published_at' => '2019-01-23 10:00:00']);
        $this->makePost(['slug' => 'agey', 'title' => 'আগের বছরের লেখা', 'published_at' => '2018-06-01 10:00:00']);
        DB::table('post_views')->insert(['post_id' => $read->id, 'day' => '2019-02-01', 'views' => 90]);
        // Checked on the live site: these never become picks, however often they are read.
        $english = Section::firstOrCreate(['slug' => 'english'], ['name_bn' => 'English', 'format' => 'prose']);
        foreach ([['seo', 'Mariah Carey\'s Sister: A Look into the Life', $english->id], ['porbo', 'ছুটি (৩য় পর্ব)', $stories->id], ['kom', 'কম পড়া গল্প', $stories->id]] as $i => [$slug, $title, $sec]) {
            $p = $this->makePost(['slug' => $slug, 'title' => $title, 'section_id' => $sec, 'published_at' => '2019-02-0'.($i + 1).' 10:00:00', 'featured_image_path' => 'media/y.jpg']);
            DB::table('post_views')->insert(['post_id' => $p->id, 'day' => '2019-02-01', 'views' => $slug === 'kom' ? 2 : 500]);
        }
        \Illuminate\Support\Facades\Cache::flush();

        $html = $this->get('/archive/2019/')->assertOk()
            ->assertSee('এ বছরের বাছাই')->assertSee('সাগরের ডাকবাক্স')->assertSee('Mariah Carey', false)   // every piece stays in the list
            ->assertSee('?section=poetry', false)->assertSee('← ২০১৮', false)
            ->assertDontSee('noindex', false)->getContent();
        $picks = substr($html, strpos($html, 'id="picks"'), strpos($html, '</ul>', strpos($html, 'id="picks"')) - strpos($html, 'id="picks"'));
        $this->assertStringContainsString('সবচেয়ে পড়া গল্প', $picks);
        foreach (['Mariah Carey', 'ছুটি (৩য় পর্ব)', 'কম পড়া গল্প'] as $never) {
            $this->assertStringNotContainsString($never, $picks, $never.' must not be a pick');
        }
        $this->assertFalse(\App\Http\Controllers\ArchiveController::laterEpisode('সিটি অফ জয় (Part 1)'));
        $this->assertFalse(\App\Http\Controllers\ArchiveController::laterEpisode('শীল vs. শীল (প্রথম পর্ব)'));
        $this->assertTrue(\App\Http\Controllers\ArchiveController::laterEpisode('সাদা কালো--তৃতীয় পর্ব'));
        $this->assertTrue(\App\Http\Controllers\ArchiveController::laterEpisode('লাল কাচ (পর্ব ৩): আলতা'));
        $this->assertFalse(\App\Http\Controllers\ArchiveController::laterEpisode('মনে পড়ে'));

        $this->get('/archive/2019/?section=poetry')->assertOk()
            ->assertSee('সাগরের ডাকবাক্স')->assertDontSee('সবচেয়ে পড়া গল্প')
            ->assertSee('noindex', false)->assertSee('<link rel="canonical" href="'.url('/archive/2019').'/">', false);
        $this->get('/archive/2019/?section=nonsense')->assertOk()->assertSee('সবচেয়ে পড়া গল্প')->assertDontSee('noindex', false);
    }

    public function test_a_big_month_shows_twenty_then_the_rest_behind_a_tap(): void
    {
        for ($i = 1; $i <= 45; $i++) {
            $this->makePost(['slug' => 'p'.$i, 'title' => 'লেখা '.$i, 'published_at' => sprintf('2020-04-%02d 10:00:00', min(28, $i))]);
        }
        \Illuminate\Support\Facades\Cache::flush();

        $html = $this->get('/archive/2020/')->assertOk()->assertSee('আরও ২৫টি দেখুন')->getContent();
        for ($i = 1; $i <= 45; $i++) {
            $this->assertStringContainsString('>লেখা '.$i.'</span>', $html, 'Every piece stays in the page (for readers and Google)');
        }
    }
}
