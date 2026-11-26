<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\FestivalDesk;
use App\Ai\JanunDesk;
use App\Models\FestivalTime;
use App\Models\JanunPage;
use App\Support\FestivalPlan;
use App\Support\JanunPlan;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D302: festival times in any time zone, children's poems with our translation, the printable puja booklet. */
class FestivalTimesTest extends TestCase
{
    use RefreshDatabase;

    private array $answer = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 09:00:00');
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        JanunDesk::$resolve = fn ($host) => ['93.184.216.34'];
        Claude::$fake = fn ($purpose, $user) => $this->answer;
        // Saraswati Puja 2027: Panchami from 11 Feb 14:20 to 12 Feb 15:05 (IST); a.org in English, b.in in Bengali digits, c.net wrong
        Http::fake([
            'https://a.org/*' => Http::response('<p>Saraswati Puja on 12th February 2027. Panchami Tithi Begins - 02:20 PM on Feb 11, Panchami Tithi Ends - 03:05 PM on Feb 12</p>'),
            'https://b.in/*' => Http::response('<p>সরস্বতী পুজো ১২ ফেব্রুয়ারি। পঞ্চমী শুরু ১১ ফেব্রুয়ারি দুপুর ২টা ২০ মিনিট, শেষ ১২ ফেব্রুয়ারি বেলা ৩টা ৫ মিনিট।</p>'),
            'https://c.net/*' => Http::response('<p>Saraswati Puja 12 February, 10:00 to 11:00</p>'),
            'https://wikisource.test/*' => Http::response('<p>আমাদের ছোটো নদী চলে বাঁকে বাঁকে,<br>বৈশাখ মাসে তার হাঁটুজল থাকে।</p>'),
            'https://lit.test/*' => Http::response('<div>আমাদের ছোটো নদী চলে বাঁকে বাঁকে / বৈশাখ মাসে তার হাঁটুজল থাকে ।</div>'),
            'https://other.test/*' => Http::response('<p>আমাদের ছোট নদী চলে বাঁকে বাঁকে বৈশাখ মাসে তার হাঁটুজল থাকে</p>'),

            '*' => Http::response('', 404),
        ]);
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        JanunDesk::$resolve = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function saraswati(array $sources): void
    {
        $this->answer = ['events' => [['label' => 'পঞ্চমী তিথি', 'day' => '2027-02-12', 'start' => '2027-02-11 14:20', 'end' => '2027-02-12 15:05']],
            'sources' => array_map(fn ($s) => ['url' => 'https://'.$s.'/p'], $sources)];
    }

    public function test_a_time_is_kept_only_when_two_pages_show_it(): void
    {
        $this->saraswati(['a.org', 'b.in', 'c.net']);
        $this->assertSame(1, FestivalDesk::find('saraswati', 2027));
        $t = FestivalTime::first();
        $this->assertSame('verified', $t->status);
        $this->assertSame('2027-02-11 08:50', $t->starts_at->format('Y-m-d H:i'), 'stored in UTC');
        $this->assertSame(['https://a.org/p', 'https://b.in/p'], $t->sources);

        $this->saraswati(['a.org', 'c.net']);
        FestivalDesk::find('saraswati', 2027);
        $this->assertSame('held', FestivalTime::first()->status, 'one page is not enough');
    }

    public function test_times_that_make_no_sense_are_held(): void
    {
        $this->answer = ['events' => [['label' => 'পঞ্চমী তিথি', 'day' => '2027-02-12', 'start' => '2027-02-12 15:05', 'end' => '2027-02-11 14:20']], 'sources' => [['url' => 'https://a.org/p'], ['url' => 'https://b.in/p']]];
        $this->assertSame(0, FestivalDesk::find('saraswati', 2027));
        $this->answer = ['events' => [['label' => 'বানানো', 'day' => '2027-02-12', 'start' => '', 'end' => '']], 'sources' => []];
        FestivalDesk::find('saraswati', 2027);
        $this->assertSame(0, FestivalTime::where('label', 'বানানো')->count(), 'only the events asked for');
    }

    public function test_time_and_date_matching(): void
    {
        $t = CarbonImmutable::parse('2027-02-11 14:20', 'Asia/Kolkata');
        $this->assertTrue(FestivalDesk::hasTime('starts 14:20 IST', $t));
        $this->assertTrue(FestivalDesk::hasTime('02:20 PM', $t));
        $this->assertTrue(FestivalDesk::hasTime(FestivalDesk::digits('দুপুর ২টা ২০ মিনিট'), $t));
        $this->assertFalse(FestivalDesk::hasTime('12:20 or 4:20', $t));
        $this->assertFalse(FestivalDesk::hasTime('114:205', $t));
        $d = CarbonImmutable::parse('2027-02-12');
        $this->assertTrue(FestivalDesk::hasDate('12th February', $d));
        $this->assertTrue(FestivalDesk::hasDate('Feb 12, 2027', $d));
        $this->assertTrue(FestivalDesk::hasDate(FestivalDesk::digits('১২ ফেব্রুয়ারী'), $d));
        $this->assertFalse(FestivalDesk::hasDate('2 February', $d));
    }

    public function test_one_search_an_hour_and_three_tries(): void
    {
        $this->answer = ['events' => [], 'sources' => []];
        $now = CarbonImmutable::now('Asia/Kolkata');
        $this->assertTrue(FestivalDesk::run($now));
        $this->assertFalse(FestivalDesk::run($now), 'the same hour');
        foreach (range(1, 70) as $h) {
            FestivalDesk::run($now->addHours($h));
        }
        $this->assertSame(3, (int) \App\Models\Setting::get('festival.tries.saraswati.2027'), 'in November only next year\'s festivals');
        $this->assertNull(\App\Models\Setting::get('festival.tries.saraswati.2026'));
        $this->assertFalse(FestivalDesk::run($now->addHours(200)), 'everything tried three times');
    }

    private function verified(string $festival, string $label, string $start, string $end, string $day): void
    {
        FestivalTime::create(['festival' => $festival, 'cycle' => 2027, 'label' => $label, 'day' => $day,
            'starts_at' => CarbonImmutable::parse($start, 'Asia/Kolkata')->utc(), 'ends_at' => CarbonImmutable::parse($end, 'Asia/Kolkata')->utc(), 'status' => 'verified', 'sources' => ['https://a.org/p', 'https://b.in/p']]);
    }

    public function test_the_time_zone_page(): void
    {
        $this->verified('saraswati', 'পঞ্চমী তিথি', '2027-02-11 14:20', '2027-02-12 15:05', '2027-02-12');
        $this->get('/janun/puja/pujor-somoy/')->assertNotFound();   // one festival is too thin
        $this->verified('shiboratri', 'চতুর্দশী তিথি', '2027-03-06 21:00', '2027-03-07 22:10', '2027-03-06');
        FestivalTime::create(['festival' => 'kali', 'cycle' => 2027, 'label' => 'অমাবস্যা তিথি', 'day' => '2027-10-29', 'status' => 'held']);
        $html = $this->get('/janun/puja/pujor-somoy/')->assertOk()
            ->assertSee('সরস্বতী পুজো ২০২৭')->assertSee('শিবরাত্রি')->assertDontSee('অমাবস্যা তিথি')
            ->assertSee('১১ ফেব্রুয়ারি ২০২৭, বৃহস্পতিবার, দুপুর ২:২০')
            ->assertSee('data-utc="2027-02-11T08:50:00+00:00"', false)
            ->assertSee('value="Asia/Tokyo"', false)->getContent();
        $this->assertStringContainsString('js/hp-tz.js', $html);
        $this->assertSame(1, substr_count($html, 'js/hp-tz.js'));
        $this->assertStringContainsString('/janun/puja/pujor-somoy/', $this->get('/sitemap-pages.xml')->getContent());
        // the past is not shown, but the page stays (an indexed address is not taken away between years)
        Carbon::setTestNow('2027-03-20 09:00:00');
        $this->get('/janun/puja/pujor-somoy/')->assertOk()->assertDontSee('পঞ্চমী তিথি')->assertSee('শিগগির এখানে দেওয়া হবে');
    }

    public function test_after_midnight_is_not_read_as_the_night_before(): void
    {
        // 08:50 UTC = 00:50 in Los Angeles: shown in India time here, the same rule in hp-tz.js
        $this->assertSame('মধ্যরাতের পর', FestivalPlan::part(0));
        $this->assertSame('ভোর', FestivalPlan::part(5));
        $this->assertSame('সকাল', FestivalPlan::part(6));
        $this->assertSame('রাত', FestivalPlan::part(23));
        $this->assertStringContainsString('মধ্যরাতের পর ১২:৩০', FestivalPlan::show(CarbonImmutable::parse('2027-03-06 19:00', 'UTC')));
        $js = file_get_contents(public_path('js/hp-tz.js'));
        $this->assertStringContainsString("h < 4 ? 'মধ্যরাতের পর' : h < 6 ? 'ভোর'", $js);
    }

    public function test_the_puja_page_shows_its_times_and_the_booklet(): void
    {
        JanunDesk::seed();
        $this->verified('saraswati', 'পঞ্চমী তিথি', '2027-02-11 14:20', '2027-02-12 15:05', '2027-02-12');
        JanunPage::where('key', 'saraswati-mantra')->first()->forceFill(['status' => 'published', 'publish_at' => now()->subDay(), 'checked_at' => now()->subDay(), 'body' => [
            'intro' => 'সরস্বতী পুজোর কথা।', 'facts' => [], 'faq' => [], 'sources' => [],
            'sections' => [['heading' => 'অঞ্জলি', 'paragraphs' => ['অঞ্জলি দেওয়া হয়।'], 'steps' => ['ফুল নিন'], 'mantras' => [
                ['name' => 'প্রণাম মন্ত্র', 'text' => 'ওঁ সরস্বতী মহাভাগে', 'roman' => 'Om Saraswati Mahabhage', 'meaning' => 'হে দেবী', 'english' => 'O goddess.', 'checked_on' => ['a.org', 'b.in']]]]],
        ]])->save();
        $this->get('/janun/puja/saraswati-puja-mantra/')->assertOk()->assertSee('এবারের তিথি ও সময়')->assertSee('পঞ্চমী তিথি')
            ->assertSee('/janun/puja/saraswati-puja-mantra/chhapun/', false);
        $res = $this->get('/janun/puja/saraswati-puja-mantra/chhapun/')->assertOk()
            ->assertSee('ওঁ সরস্বতী মহাভাগে')->assertSee('Om Saraswati Mahabhage')->assertSee('O goddess.')->assertSee('ফুল নিন')->assertSee('পঞ্চমী তিথি')
            ->assertSee('data:image/svg+xml;base64', false)->assertSee('<link rel="canonical" href="'.\App\Support\Url::to('/janun/puja/saraswati-puja-mantra').'"', false);
        $this->assertStringContainsString('noindex', (string) $res->headers->get('X-Robots-Tag'));
        $this->get('/janun/puja/durga-puja-anjali-mantra/chhapun/')->assertNotFound();   // not live
    }

    public function test_poems_are_public_domain_and_checked_letter_for_letter(): void
    {
        foreach (JanunPlan::PAGES as $key => [$section, $kind, , $title]) {
            if ($kind === 'poem') {
                $poet = trim(explode(':', $title)[1]);
                $this->assertArrayHasKey($poet, JanunPlan::POETS, $key);
                $this->assertLessThan(2026, JanunPlan::POETS[$poet] + 60, $key.': out of copyright in India and Bangladesh');
            }
        }
        $poem = "আমাদের ছোটো নদী চলে বাঁকে বাঁকে\nবৈশাখ মাসে তার হাঁটুজল থাকে।";
        JanunDesk::seed();
        $p = JanunPage::where('key', 'chhoto-nodi')->first();
        $body = ['description' => 'কবিতা', 'sources' => [], 'faq' => [], 'intro' => 'রবীন্দ্রনাথ ঠাকুর ছোটদের জন্য অনেক লিখেছেন।', 'poet' => 'রবীন্দ্রনাথ ঠাকুর', 'book' => '',
            'text' => $poem, 'roman' => "Amader chhoto nodi chole banke banke", 'english' => "Our little river winds and bends", 'words' => [['term' => 'হাঁটুজল', 'meaning' => 'হাঁটু পর্যন্ত জল', 'english' => 'knee-deep water']], 'about' => ['নদীর ছবি।']];
        $p->forceFill(['body' => $body, 'status' => 'drafted'])->save();

        Claude::$fake = fn ($purpose, $user) => str_contains($user, '<poem') ? ['pages' => [['url' => 'https://other.test/x'], ['url' => 'https://wikisource.test/x']]] : ['problems' => []];
        JanunDesk::check($p, CarbonImmutable::now('Asia/Kolkata'));
        $this->assertSame('held', $p->fresh()->status, 'a different spelling on one page: not two exact matches');

        $p->forceFill(['body' => $body, 'status' => 'drafted'])->save();
        Claude::$fake = fn ($purpose, $user) => str_contains($user, '<poem') ? ['pages' => [['url' => 'https://wikisource.test/x'], ['url' => 'https://lit.test/x']]] : ['problems' => []];
        JanunDesk::check($p->fresh(), CarbonImmutable::now('Asia/Kolkata'));
        $p->refresh();
        $this->assertSame('scheduled', $p->status);
        $this->assertSame(['wikisource.test', 'lit.test'], $p->body['text_checked_on']);

        $p->forceFill(['status' => 'published', 'publish_at' => now()->subHour()])->save();
        $html = $this->get('/janun/chhora/amader-chhoto-nodi/')->assertOk()
            ->assertSee('বৈশাখ মাসে তার হাঁটুজল থাকে।')->assertSee('Our little river winds and bends')->assertSee('knee-deep water')
            ->assertSee('Amader chhoto nodi')->assertSee('অনুবাদ: হাতপাখা ডেস্ক')->getContent();
        $this->assertStringContainsString('"CreativeWork"', $html);
        $this->assertStringContainsString('রবীন্দ্রনাথ ঠাকুর', $html);
    }
}
