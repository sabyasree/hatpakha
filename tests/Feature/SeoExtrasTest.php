<?php

namespace Tests\Feature;

use App\Ai\JanunDesk;
use App\Models\JanunPage;
use App\Support\IndexNow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D312: the small SEO extras: share pictures per page type, IndexNow when a page goes live. */
class SeoExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_pictures_and_the_indexnow_key(): void
    {
        $this->get('/janun/bangla-calendar/')->assertSee('images/og/card-calendar.jpg', false);
        $this->get('/janun/')->assertSee('images/og/card-janun.jpg', false);
        foreach (['card-calendar', 'card-puja', 'card-janun'] as $f) {
            [$w, $h] = getimagesize(public_path('images/og/'.$f.'.jpg'));
            $this->assertSame([1200, 630], [$w, $h], $f);
        }
        $this->get('/indexnow.txt')->assertOk()->assertSee(IndexNow::key());
        $this->assertSame(32, strlen(IndexNow::key()));
    }

    public function test_a_page_going_live_is_sent_to_indexnow_on_the_live_site_only(): void
    {
        Carbon::setTestNow('2026-11-27 05:00:00');
        Http::fake(['https://api.indexnow.org/*' => Http::response('', 200)]);
        JanunDesk::seed();
        JanunPage::where('key', 'probad')->update(['status' => 'scheduled', 'publish_at' => '2026-11-27 04:30:00']);
        JanunDesk::publishDue();
        Http::assertNothingSent();   // not the live site

        config(['app.url' => 'https://hatpakha.com', 'services.indexnow.test' => true]);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://hatpakha.com');   // as on the live server
        \Illuminate\Support\Facades\URL::forceScheme('https');
        JanunPage::where('key', 'bagdhara')->update(['status' => 'scheduled', 'publish_at' => '2026-11-27 04:30:00']);
        JanunDesk::publishDue();
        Http::assertSent(fn ($r) => $r->url() === 'https://api.indexnow.org/indexnow' && in_array('https://hatpakha.com/janun/bhasha/bagdhara/', $r['urlList'], true)
            && $r['key'] === IndexNow::key() && $r['keyLocation'] === 'https://hatpakha.com/indexnow.txt');
        \Illuminate\Support\Facades\URL::forceRootUrl(null);
        \Illuminate\Support\Facades\URL::forceScheme(null);
        Carbon::setTestNow();
    }
}
