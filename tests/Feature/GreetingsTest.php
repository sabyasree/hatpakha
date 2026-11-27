<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\GreetingDesk;
use App\Ai\JanunDesk;
use App\Models\GreetingSet;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D315: festival greetings: our own lines, checked; the poet's line only from two pages letter for letter; held when thin. */
class GreetingsTest extends TestCase
{
    use RefreshDatabase;

    private array $drop = [];

    private string $poem = "এসো হে বৈশাখ, এসো এসো\nতাপসনিশ্বাসবায়ে মুমূর্ষুরে দাও উড়ায়ে";

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 06:00:00');
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        JanunDesk::$resolve = fn ($h) => ['93.184.216.34'];
        Claude::$fake = function ($purpose, $user, $schema) {
            if (str_contains($user, '<occasion')) {
                return $this->draft();
            }
            if (str_contains($user, '<g n=')) {
                return ['drop' => $this->drop];
            }
            if (str_contains($user, '<lines')) {
                return ['pages' => [['url' => 'https://a.org/p'], ['url' => 'https://www.b.in/p'], ['url' => 'https://hatpakha.com/x']]];
            }

            return null;
        };
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        JanunDesk::$resolve = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function draft(): array
    {
        $groups = [];
        foreach (['chhoto' => 'ছোট', 'poribar' => 'পরিবার', 'bondhu' => 'বন্ধু', 'office' => 'অফিস'] as $k => $w) {
            $lines = [];
            for ($i = 1; $i <= 6; $i++) {
                $lines[] = ['bn' => "নতুন বছর আনন্দে কাটুক, {$w} তালিকার ".['এক', 'দুই', 'তিন', 'চার', 'পাঁচ', 'ছয়'][$i - 1].' নম্বর শুভেচ্ছা রইল', 'en' => 'Happy new year'];
            }
            $groups[] = ['key' => $k, 'lines' => $lines];
        }
        $groups[0]['lines'][] = ['bn' => 'শুভ নববর্ষ 🎉', 'en' => 'emoji'];
        $groups[0]['lines'][] = ['bn' => 'দেখুন https://example.com শুভ নববর্ষ', 'en' => 'link'];
        $groups[1]['lines'][] = $groups[1]['lines'][0];   // a repeat

        return ['intro' => "প্রথম অনুচ্ছেদ।\n\nদ্বিতীয় অনুচ্ছেদ।", 'description' => 'পয়লা বৈশাখের শুভেচ্ছা বার্তা, ছবিসহ।', 'groups' => $groups,
            'poet' => ['text' => $this->poem, 'poet' => 'রবীন্দ্রনাথ ঠাকুর', 'work' => 'এসো হে বৈশাখ'], 'faq' => [['q' => 'বড়দের কী লিখব?', 'a' => 'প্রণাম জানিয়ে লিখুন।']]];
    }

    public function test_a_set_is_written_checked_and_shown(): void
    {
        Http::fake(['https://a.org/*' => Http::response('<p>'.str_replace("\n", '<br>', $this->poem).'</p>'),
            'https://www.b.in/*' => Http::response('<div>এসো হে বৈশাখ এসো এসো। তাপসনিশ্বাসবায়ে মুমূর্ষুরে দাও উড়ায়ে।</div>'), '*' => Http::response('', 404)]);
        $this->drop = [['n' => 3, 'why' => 'বানান ভুল']];
        $now = CarbonImmutable::parse('2026-11-27 12:00', 'Asia/Kolkata');
        GreetingDesk::run($now);
        $s = GreetingSet::where('status', 'drafted')->first();
        $this->assertSame('birthday', $s->slug, 'the any-time occasions are written first');
        $this->assertFalse(GreetingDesk::run($now->addMinutes(10)) && GreetingSet::where('status', 'drafted')->exists(), 'checked on the next run');
        $s->refresh();
        $this->assertSame('live', $s->status);
        $this->assertSame(23, count($s->lines()), '24 written, one dropped by the proofreader; emoji, link and repeat dropped before');
        $this->assertSame(4, count($s->check['removed']));
        $this->assertSame(['https://a.org/p', 'https://www.b.in/p'], $s->body['poet']['urls']);
        $this->assertSame(0, GreetingSet::where('status', 'drafted')->count());
        $this->assertFalse(GreetingDesk::run($now->addMinutes(20)), 'one new set a day');

        $this->get('/janun/greetings/birthday/')->assertOk()->assertSee('জন্মদিনের শুভেচ্ছা বার্তা')->assertSee('এসো হে বৈশাখ')->assertSee('a.org')
            ->assertSee('wa.me/?text=', false)->assertSee('data-card=', false)->assertSee('Birthday Wishes in Bengali', false)->assertDontSee('🎉')->assertDontSee('example.com');
        $this->get('/janun/greetings/')->assertOk()->assertSee('জন্মদিনের শুভেচ্ছা');
        $this->get('/janun/greetings/christmas/')->assertNotFound();
        $this->get('/janun/greetings/nothing/')->assertNotFound();
        $this->get('/sitemap-pages.xml')->assertOk()->assertSee('/janun/greetings/birthday/')->assertSee('/janun/greetings/<', false);
        $this->get('/janun/')->assertOk()->assertSee('শুভেচ্ছা বার্তা, ছবিসহ');
    }

    public function test_a_poet_line_on_one_page_only_is_left_out_and_a_thin_set_is_held(): void
    {
        Http::fake(['https://a.org/*' => Http::response('<p>'.$this->poem.'</p>'), '*' => Http::response('<p>অন্য কবিতা</p>')]);
        $s = GreetingSet::create(['slug' => 'poila-baishakh', 'status' => 'drafted', 'body' => $this->draft()]);
        GreetingDesk::check($s);
        $s->refresh();
        $this->assertSame('live', $s->status);
        $this->assertNull($s->body['poet']);

        $this->drop = collect(range(0, 12))->map(fn ($n) => ['n' => $n, 'why' => 'x'])->all();
        $t = GreetingSet::create(['slug' => 'durga-puja', 'status' => 'drafted', 'body' => $this->draft()]);
        GreetingDesk::check($t);
        $this->assertSame('held', $t->fresh()->status, '11 lines left: held, never shown');
        $this->get('/janun/greetings/durga-puja/')->assertNotFound();
    }

    public function test_a_poet_outside_the_copyright_list_is_never_printed(): void
    {
        Http::fake(['*' => Http::response('<p>'.$this->poem.'</p>')]);
        $d = $this->draft();
        $d['poet']['poet'] = 'কাজী নজরুল ইসলাম';   // died 1976: still in copyright
        $s = GreetingSet::create(['slug' => 'eid-ul-fitr', 'status' => 'drafted', 'body' => $d]);
        GreetingDesk::check($s);
        $this->assertNull($s->fresh()->body['poet']);
    }

    public function test_line_rules(): void
    {
        $this->assertTrue(GreetingDesk::fine('শুভ জন্মদিন, ভালো থেকো!', 'chhoto'));
        $this->assertFalse(GreetingDesk::fine('Happy birthday to you my friend', 'chhoto'));
        $this->assertFalse(GreetingDesk::fine('শুভ', 'chhoto'));
        $this->assertFalse(GreetingDesk::fine(str_repeat('শুভেচ্ছা ', 20), 'chhoto'), 'too long for a picture');
        $this->assertFalse(GreetingDesk::fine('শুভ জন্মদিন #birthday ভালো থেকো', 'bondhu'));
    }

    public function test_nothing_is_written_without_the_ai(): void
    {
        config(['ai.enabled' => false]);
        Setting::put('greeting.day', null);
        JanunDesk::run(CarbonImmutable::parse('2026-11-27 12:00', 'Asia/Kolkata'));
        $this->assertSame(0, GreetingSet::where('status', '!=', 'planned')->count());
    }
}
