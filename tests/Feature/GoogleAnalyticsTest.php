<?php

namespace Tests\Feature;

use App\Ai\Knowledge;
use App\Support\GoogleData;
use App\Support\ReaderInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D119: GA4 tag on the live site (not on children's pages, no private query strings) and Google data for the AI team. */
class GoogleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function account(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);

        return base64_encode(json_encode(['client_email' => 'reader@hatpakha.iam.gserviceaccount.com', 'private_key' => $pem]));
    }

    public function test_the_tag_is_only_on_the_live_site_with_a_valid_id_and_sends_only_the_path(): void
    {
        $this->get('/')->assertDontSee('googletagmanager.com/gtag', false);   // no id

        config(['services.google.measurement_id' => 'G-ABC123XYZ9']);
        $this->get('/')->assertDontSee('googletagmanager.com/gtag', false);   // staging / local

        config(['app.url' => 'https://hatpakha.com']);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('gtag/js?id=G-ABC123XYZ9', $html);
        $this->assertStringContainsString("location.origin + location.pathname", $html);
        $this->assertStringContainsString('allow_google_signals: false', $html);
        $this->assertStringContainsString('"GB"', $html);   // consent first in Europe

        config(['services.google.measurement_id' => '"><script>x</script>']);
        $this->get('/')->assertDontSee('gtag/js', false);
    }

    public function test_no_tag_on_childrens_pieces(): void
    {
        config(['services.google.measurement_id' => 'G-ABC123XYZ9', 'app.url' => 'https://hatpakha.com']);
        $kids = \App\Models\Section::firstOrCreate(['slug' => \App\Models\Section::KIDS], ['name_bn' => 'ছোটদের হাতপাখা', 'format' => 'prose']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'chhoto', 'section_id' => $kids->id, 'published_at' => now()->subDay()]);
        $this->get('/chhoto/')->assertOk()->assertDontSee('gtag/js', false);
    }

    public function test_daily_snapshot_reaches_the_ai_team(): void
    {
        config(['services.google.service_account' => $this->account(), 'services.google.ga_property' => '123456', 'services.google.search_console_site' => 'sc-domain:hatpakha.com']);
        Cache::flush();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'analyticsdata.googleapis.com/*' => function (Request $r) {
                $dims = array_column($r['dimensions'] ?? [], 'name');
                $row = fn (array $d, array $m) => ['dimensionValues' => array_map(fn ($v) => ['value' => $v], $d), 'metricValues' => array_map(fn ($v) => ['value' => (string) $v], $m)];

                return Http::response(['rows' => match ($dims) {
                    [] => [$row([], [1200, 800, 2000, 5000, 0.61, 95])],
                    ['sessionDefaultChannelGroup'] => [$row(['Organic Search'], [900]), $row(['Organic Social'], [600])],
                    ['sessionSource'] => [$row(['google'], [880]), $row(['facebook.com'], [500])],
                    ['pagePath', 'pageTitle'] => [$row(['/megh/', 'মেঘ — হাতপাখা'], [400, 300])],
                    ['hour'] => [$row(['21'], [300]), $row(['22'], [250])],
                    default => [$row([$dims[0] === 'country' ? 'India' : 'x'], [10])],
                }]);
            },
            'www.googleapis.com/webmasters/*' => fn (Request $r) => Http::response(['rows' => ($r['dimensions'][0] ?? '') === 'query'
                ? [['keys' => ['বাংলা কবিতা'], 'clicks' => 40, 'impressions' => 900, 'ctr' => 0.044, 'position' => 6.2]]
                : [['keys' => ['https://hatpakha.com/megh/'], 'clicks' => 2, 'impressions' => 500, 'ctr' => 0.004, 'position' => 8.1]]]),
        ]);

        $this->assertSame([], ReaderInsights::refresh());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'properties/123456:runReport') && $r->hasHeader('Authorization', 'Bearer tok'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && str_contains((string) $r['grant_type'], 'jwt-bearer'));

        $brief = ReaderInsights::brief();
        $this->assertStringContainsString('1200 readers', $brief);
        $this->assertStringContainsString('"মেঘ" 400', $brief);
        $this->assertStringContainsString('21:00', $brief);
        $this->assertStringContainsString('"বাংলা কবিতা" 40/900', $brief);
        $this->assertStringContainsString('https://hatpakha.com/megh/ (500 views, 0.4% clicked', $brief);

        $this->assertStringContainsString('What readers did', Knowledge::forPurpose('marketing'));
        $this->assertStringContainsString('"বাংলা কবিতা"', Knowledge::forPurpose('seo'));
        $this->assertStringNotContainsString('What readers did', Knowledge::forPurpose('review'), 'Reviews of a writer\'s piece never see traffic numbers');
    }

    public function test_a_failed_day_keeps_the_last_good_numbers(): void
    {
        config(['services.google.service_account' => $this->account(), 'services.google.ga_property' => '123456']);
        \App\Models\Setting::put('analytics.snapshot', json_encode(['at' => '2026-11-08', 'ga' => ['now' => ['activeUsers' => 5, 'newUsers' => 1, 'sessions' => 6, 'screenPageViews' => 9, 'engagementRate' => 0.5, 'averageSessionDuration' => 60], 'before' => null, 'channels' => [], 'sources' => [], 'pages' => [], 'countries' => [], 'cities' => [], 'devices' => [], 'returning' => [], 'hours' => [], 'weekdays' => []], 'sc' => null]));
        Cache::flush();
        Http::fake(['*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT'], 400)]);

        $errors = ReaderInsights::refresh();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid JWT', $errors[0]);
        $this->assertStringContainsString('5 readers', ReaderInsights::brief());
        $this->assertFalse(GoogleData::configured() && config('services.google.ga_property') === null);
    }
}
