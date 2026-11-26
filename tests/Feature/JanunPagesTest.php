<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\JanunDesk;
use App\Models\JanunPage;
use App\Support\JanunPlan;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D301: the "জেনে রাখুন" pages: written, checked and scheduled by the desk; readers see only what is live. */
class JanunPagesTest extends TestCase
{
    use RefreshDatabase;

    private array $mantraSites = [];

    private array $problems = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 09:00:00');   // a Thursday
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        Claude::$fake = function ($purpose, $user) {
            if ($purpose === 'janun') {
                return str_contains($user, 'প্রবাদ') && ! str_contains($user, 'মন্ত্র') ? $this->listBody(45) : $this->guideBody();
            }
            if ($purpose === 'factcheck') {
                return str_contains($user, '<mantra')
                    ? ['exact_matches' => array_map(fn ($s) => ['url' => 'https://'.$s.'/x', 'site' => $s], array_shift($this->mantraSites) ?? [])]
                    : ['problems' => $this->problems];
            }

            return null;
        };
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function listBody(int $n): array
    {
        $words = ['অ', 'আ', 'ক', 'খ', 'গ'];

        return ['description' => 'বাংলা প্রবাদ অর্থসহ।', 'sources' => ['বাংলা একাডেমি'], 'faq' => [['q' => 'প্রবাদ কী?', 'a' => 'লোকমুখে চলা কথা।']],
            'intro' => 'প্রবাদের তালিকা।',
            'entries' => array_map(fn ($i) => ['term' => $words[$i % 5].'প্রবাদ'.$i, 'meaning' => 'অর্থ'.$i, 'example' => 'উদাহরণ'.$i], range(1, $n))];
    }

    private function guideBody(): array
    {
        return ['description' => 'পুজোর নিয়ম।', 'sources' => ['পুরোহিত দর্পণ'], 'faq' => [], 'intro' => 'সরস্বতী পুজোর কথা।',
            'facts' => [['label' => 'তিথি', 'value' => 'মাঘ মাসের শুক্লা পঞ্চমী']],
            'sections' => [
                ['heading' => 'পুজোর নিয়ম', 'paragraphs' => ['ভোরে স্নান করে পুজো।'], 'steps' => ['ফুল সাজান'], 'mantras' => [
                    ['name' => 'প্রণাম মন্ত্র', 'text' => 'ওঁ সরস্বতী মহাভাগে', 'roman' => 'Om Saraswati Mahabhage', 'meaning' => 'হে দেবী', 'english' => 'O great goddess Saraswati.'],
                    ['name' => 'অচেনা মন্ত্র', 'text' => 'ভুল মন্ত্র', 'meaning' => 'জানা নেই'],
                ]],
                ['heading' => 'পুষ্পাঞ্জলি', 'paragraphs' => ['অঞ্জলি দেওয়া হয়।'], 'steps' => [], 'mantras' => []],
            ]];
    }

    private function live(string $key, array $body, string $kind = 'list'): JanunPage
    {
        JanunDesk::seed();
        $p = JanunPage::where('key', $key)->firstOrFail();
        $p->forceFill(['body' => $body, 'status' => 'published', 'publish_at' => now()->subDay(), 'checked_at' => now()->subDay(), 'seo_description' => 'বর্ণনা'])->save();

        return $p;
    }

    public function test_the_plan_is_complete_and_valid(): void
    {
        $this->assertGreaterThanOrEqual(22, count(JanunPlan::PAGES));
        $slugs = [];
        foreach (JanunPlan::PAGES as $key => [$section, $kind, $slug, $title, $seo, $brief]) {
            $this->assertArrayHasKey($section, JanunPlan::SECTIONS, $key);
            $this->assertContains($kind, ['list', 'guide', 'study'], $key);
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug, $key);
            $this->assertNotContains($section.'/'.$slug, $slugs, $key);
            $slugs[] = $section.'/'.$slug;
            $this->assertNotSame('', trim($title.$seo.$brief));
            $this->assertDoesNotMatchRegularExpression('/[0-9]/', $title, $key.': Bengali digits on the page');
        }
    }

    public function test_nothing_is_public_until_published(): void
    {
        JanunDesk::seed();
        $this->get('/janun/')->assertNotFound();
        $this->get('/janun/bhasha/probad/')->assertNotFound();
        JanunPage::where('key', 'probad')->update(['status' => 'scheduled', 'publish_at' => now()->addDay(), 'body' => json_encode($this->listBody(45))]);
        $this->get('/janun/bhasha/probad/')->assertNotFound();
        $this->assertStringNotContainsString('/janun/', $this->get('/sitemap-pages.xml')->getContent());
    }

    public function test_drafting_checking_and_scheduling(): void
    {
        JanunDesk::run(CarbonImmutable::now('Asia/Kolkata'));
        $p = JanunPage::where('key', 'probad')->first();
        $this->assertSame('drafted', $p->status);
        $this->assertCount(45, $p->body['entries']);
        $this->assertSame(1, JanunPage::where('status', 'drafted')->count(), 'one draft at a time');

        $this->problems = [['claim' => 'অপ্রবাদ5 — অর্থ5', 'why' => 'wrong']];
        JanunDesk::run(CarbonImmutable::now('Asia/Kolkata'));
        $p->refresh();
        $this->assertSame('scheduled', $p->status);
        $this->assertCount(44, $p->body['entries'], 'the wrong entry is taken out');
        // Thursday 26 Nov → Friday 27 Nov, 10am India
        $this->assertSame('2026-11-27 10:00', $p->publish_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'));
        $this->get('/janun/bhasha/probad/')->assertNotFound();

        Carbon::setTestNow('2026-11-27 05:00:00');   // 10:30 India
        JanunDesk::publishDue();
        $this->assertSame('published', $p->fresh()->status);
        $this->get('/janun/bhasha/probad/')->assertOk()->assertSee('অপ্রবাদ10');
    }

    public function test_slots_are_spread_three_a_week(): void
    {
        JanunDesk::seed();
        $now = CarbonImmutable::parse('2026-11-26 14:30', 'Asia/Kolkata');
        $days = [];
        foreach (['probad', 'saraswati-mantra', 'bhabsomprosaron-niyom', 'bagdhara'] as $k) {
            $at = JanunDesk::nextSlot($now);
            JanunPage::where('key', $k)->update(['status' => 'scheduled', 'publish_at' => $at]);
            $days[] = $at->timezone('Asia/Kolkata')->format('D d H:i');
        }
        $this->assertSame(['Fri 27 10:00', 'Mon 30 10:00', 'Wed 02 10:00', 'Fri 04 10:00'], $days);
    }

    public function test_a_mantra_needs_two_independent_sources(): void
    {
        JanunDesk::seed();
        $p = JanunPage::where('key', 'saraswati-mantra')->first();
        JanunDesk::draft($p);
        $this->mantraSites = [['a.org', 'www.b.in', 'hatpakha.com'], ['a.org', 'hatpakha.com']];
        JanunDesk::check($p->fresh(), CarbonImmutable::now('Asia/Kolkata'));
        $p->refresh();
        $this->assertSame('scheduled', $p->status);
        $mantras = $p->body['sections'][0]['mantras'];
        $this->assertCount(1, $mantras);
        $this->assertSame('প্রণাম মন্ত্র', $mantras[0]['name']);
        $this->assertSame(['a.org', 'b.in'], $mantras[0]['checked_on']);
        $this->assertFalse($p->check['mantras'][1]['kept']);
    }

    public function test_a_thin_page_is_held_not_published(): void
    {
        JanunDesk::seed();
        $p = JanunPage::where('key', 'probad')->first();
        $p->forceFill(['body' => $this->listBody(30), 'status' => 'drafted'])->save();
        JanunDesk::check($p, CarbonImmutable::now('Asia/Kolkata'));
        $this->assertSame('held', $p->fresh()->status);
        $this->assertNull($p->fresh()->publish_at);
    }

    public function test_nothing_happens_when_the_switch_is_off(): void
    {
        config(['ai.enabled' => false]);
        JanunDesk::run();
        $this->assertSame(0, JanunPage::where('status', '!=', 'planned')->count());
    }

    public function test_a_live_list_page(): void
    {
        $p = $this->live('probad', $this->listBody(45));
        $html = $this->get('/janun/bhasha/probad/')->assertOk()
            ->assertSee('অপ্রবাদ5')->assertSee('উদাহরণ5')->assertSee('খুঁজুন')->assertSee('৪৫টি')->assertSee('হাতপাখা ডেস্ক')
            ->assertSee('প্রবাদ কী?')->assertSee('<link rel="canonical" href="'.$p->url().'"', false)->getContent();
        $this->assertStringContainsString('"DefinedTermSet"', $html);
        $this->assertStringContainsString('"BreadcrumbList"', $html);
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">\s*\(\(\) =>/', $html, 'the search script carries the CSP nonce');
        $this->get('/janun/')->assertOk()->assertSee($p->title);
        $this->get('/janun/bhasha/')->assertOk()->assertSee($p->title);
        $this->get('/janun/puja/')->assertNotFound();
        $this->get('/janun/bhasha/probad')->assertRedirect($p->url());
        $sitemap = $this->get('/sitemap-pages.xml')->getContent();
        $this->assertStringContainsString($p->url(), $sitemap);
        $this->assertStringContainsString(\App\Support\Url::to('/janun/bhasha'), $sitemap);
        $this->assertStringNotContainsString(\App\Support\Url::to('/janun/puja'), $sitemap);
    }

    public function test_a_live_guide_page_keeps_ads_away_from_mantras(): void
    {
        config(['ads.enabled' => true]);
        $this->live('saraswati-mantra', $this->guideBody());
        $html = $this->get('/janun/puja/saraswati-puja-mantra/')->assertOk()
            ->assertSee('মাঘ মাসের শুক্লা পঞ্চমী')->assertSee('ওঁ সরস্বতী মহাভাগে')->assertSee('ফুল সাজান')->assertSee('Om Saraswati Mahabhage')->assertSee('O great goddess Saraswati.')->getContent();
        $this->assertStringContainsString('lang="sa"', $html);
        // no ad block inside a mantra box
        $this->assertDoesNotMatchRegularExpression('/bg-peach-light p-4">(?:(?!<\/div>\s*<\/div>).)*ad-slot/s', $html);
    }
}
