<?php

namespace Tests\Feature;

use App\Ai\Analyst;
use App\Ai\Claude;
use App\Models\EditorTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D54: privacy-friendly read counter and monthly insights. */
class AnalystTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_beacon_counts_people_not_bots_and_stores_nothing_personal(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()->subDay()]);
        $draft = $this->makePost(['status' => 'pending', 'slug' => 'd']);
        $ua = ['User-Agent' => 'Mozilla/5.0 (X11; Linux) Firefox/140'];
        $this->post('/v/'.$post->id, ['s' => 'search'], $ua)->assertNoContent();
        $this->post('/v/'.$post->id, ['s' => 'search'], $ua)->assertNoContent();   // same reader again: not counted twice
        $this->post('/v/'.$post->id, [], ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();
        $this->post('/v/'.$draft->id, [], $ua)->assertNoContent();
        $row = DB::table('post_views')->where('post_id', $post->id)->first();
        $this->assertSame(1, (int) $row->views);
        $this->assertSame(1, (int) $row->search);
        $this->assertSame(['post_id', 'day', 'views', 'search', 'social', 'internal'], array_keys((array) $row), 'No reader data columns');
        $this->assertSame(0, DB::table('post_views')->where('post_id', $draft->id)->count());
        $this->assertEmpty($this->post('/v/'.$post->id, [], $ua)->headers->getCookies(), 'No cookies');
    }

    public function test_post_page_has_the_beacon_hook_only_when_live(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'live-one', 'published_at' => now()->subDay()]);
        $this->get('/live-one/')->assertOk()->assertSee('data-view="'.$post->id.'"', false);
    }

    public function test_monthly_insights_use_real_numbers(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $month = now('Asia/Kolkata')->subMonthNoOverflow()->startOfMonth();
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()->subYears(3), 'title' => 'পুরনো প্রিয়']);
        DB::table('post_views')->insert(['post_id' => $post->id, 'day' => $month->copy()->addDays(3)->toDateString(), 'views' => 40, 'search' => 30, 'social' => 5, 'internal' => 5]);
        Claude::$fake = fn () => ['points' => ['পুরনো লেখা এখনও পড়া হচ্ছে।']];
        $task = Analyst::monthly();
        $this->assertSame(40, $task->payload['views']);
        $this->assertSame('পুরনো প্রিয়', $task->payload['top'][0]['title']);
        $this->assertSame('পুরনো প্রিয়', $task->payload['old'][0]['title']);
        $this->assertNull(Analyst::monthly(), 'Once a month');
        $this->assertSame(1, EditorTask::where('type', 'insights')->count());
    }
}
