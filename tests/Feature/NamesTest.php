<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\JanunDesk;
use App\Ai\NameDesk;
use App\Models\NamePage;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D316: baby names: printed only when two dictionaries give the meaning; three letter pages a week. */
class NamesTest extends TestCase
{
    use RefreshDatabase;

    /** 26 real words that are names: [name, roman, Bengali meaning, English meaning] */
    private array $words = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 06:00:00');   // Friday, 11:30 in India
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        JanunDesk::$resolve = fn ($h) => ['93.184.216.34'];
        $syll = ['কাশ', 'লোক', 'দিত্য', 'নন্দ', 'মোদ', 'বীর', 'রব', 'লয়', 'শিস', 'হ্লাদ', 'ভা', 'মল', 'তুল', 'দর্শ', 'নীল', 'রুণ', 'মিত', 'ভাস', 'রত্ন', 'গম', 'জয়', 'তীশ', 'বেশ', 'রূপ', 'লাপ', 'ধার'];
        foreach (array_unique($syll) as $i => $sy) {
            $this->words[] = ['আ'.$sy, 'A'.$i, 'আলো ও আনন্দ', 'light and joy'];
        }
        $this->words[] = ['আকাশকুসুম', 'Akashkusum', 'অসম্ভব কল্পনা', 'impossible fancy'];   // in no dictionary: left out
        Claude::$fake = fn ($purpose, $user) => str_contains($user, '<page') ? ['intro' => 'আ দিয়ে ছেলেদের নাম।', 'names' => array_merge(
            array_map(fn ($w) => ['bn' => $w[0], 'roman' => $w[1], 'meaning_bn' => $w[2], 'meaning_en' => $w[3], 'origin' => 'সংস্কৃত'], $this->words),
            [['bn' => 'রাজ', 'roman' => 'Raj', 'meaning_bn' => 'রাজা', 'meaning_en' => 'king', 'origin' => 'সংস্কৃত'], ['bn' => 'Arun', 'roman' => 'Arun', 'meaning_bn' => 'সূর্য', 'meaning_en' => 'sun', 'origin' => 'সংস্কৃত']])] : null;
        Http::fake(function ($req) {
            $url = urldecode((string) $req->url());
            $word = preg_replace('/^.*[=\/]/u', '', $url);
            if ($word === 'আকাশকুসুম') {
                return Http::response('', 404);
            }
            $word = preg_replace('/\?action=raw$/', '', $word);
            if (str_contains($url, 'accessibledictionary')) {   // the real markup: headword and label in neighbouring spans
                return Http::response('<p>Bengali Word </span><span class="separator-2">'.$word.'</span><span class="sr-only">English definition</span><em>(noun)</em> (1) light; (2) joy, delight.</p>'
                    .'<p>Bengali Word </span><span class="separator">অন্য</span><span class="sr-only">English definition</span> dark</p>');
            }
            if (str_contains($url, 'en.wiktionary')) {   // wikitext: the entry exists, but its meaning shares no word with ours
                return Http::response("==Bengali==\n\n===Noun===\n{{bn-noun}}\n\n# [[shine]]\n#: {{ux|bn|light and joy}}\n\n==Assamese==\n# [[light]]\n");
            }

            return Http::response("=={{ভাষা|bn}}==\n\n===বিশেষ্য===\n# [[আলো]], দীপ্তি\n");
        });
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        JanunDesk::$resolve = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_letter_page_is_written_checked_given_a_day_and_shown(): void
    {
        $now = CarbonImmutable::parse('2026-11-27 11:30', 'Asia/Kolkata');
        NameDesk::run($now);
        $p = NamePage::first();
        $this->assertSame(['boy', 'a', 'checking'], [$p->gender, $p->letter, $p->status]);
        $this->assertCount(27, $p->names, 'a name in Roman letters and one with another first letter are dropped');
        for ($i = 0; $i < 6; $i++) {   // five names a run
            NameDesk::run($now->addMinutes(10 * ($i + 1)));
        }
        $p->refresh();
        $this->assertSame('scheduled', $p->status);
        $this->assertCount(26, $p->checked());
        $this->assertSame(['accessibledictionary.gov.bd', 'bn.wiktionary.org'], $p->checked()[0]['found']);
        $this->assertSame('no', collect($p->names)->firstWhere('bn', 'আকাশকুসুম')['status']);
        $this->assertSame('2026-11-28 10:00', $p->publish_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'), 'Saturday, the next name day');
        $this->get('/janun/names/boy-names-a/')->assertNotFound();
        $this->assertFalse(NameDesk::run($now->addHour()), 'one new page a day');

        Carbon::setTestNow('2026-11-28 04:31:00');
        config(['ai.enabled' => false]);
        JanunDesk::run(CarbonImmutable::now('Asia/Kolkata'));
        $this->assertSame('live', $p->fresh()->status, 'goes live on its day, even with the AI off');
        $this->get('/janun/names/boy-names-a/')->assertOk()->assertSee('আ দিয়ে ছেলেদের নাম, অর্থসহ')->assertSee('আকাশ')->assertDontSee('আকাশকুসুম')
            ->assertSee('Bengali Boy Names with A (আ)', false)->assertSee('"DefinedTermSet"', false)->assertSee('২৬টি সংস্কৃত থেকে', false)->assertSee('data-name-q', false);
        $this->get('/janun/names/')->assertOk()->assertSee('/janun/names/boy-names-a/', false);
        $this->get('/janun/names/girl-names-a/')->assertNotFound();
        $this->get('/janun/names/boy-names-zz/')->assertNotFound();
        $this->assertStringContainsString('/janun/names/boy-names-a/', $this->get('/sitemap-pages.xml')->getContent());
        $this->get('/janun/')->assertSee('নাম ও অর্থ');
    }

    public function test_a_page_with_too_few_checked_names_is_held(): void
    {
        $this->words = array_slice($this->words, 0, 10);
        NameDesk::run(CarbonImmutable::parse('2026-11-27 11:30', 'Asia/Kolkata'));
        $p = NamePage::first();
        NameDesk::run(CarbonImmutable::parse('2026-11-27 11:40', 'Asia/Kolkata'));
        NameDesk::run(CarbonImmutable::parse('2026-11-27 11:50', 'Asia/Kolkata'));
        $this->assertSame('held', $p->fresh()->status);
    }

    public function test_the_dictionary_entry_must_be_the_name_itself(): void
    {
        $n = ['bn' => 'অরুণ', 'meaning_bn' => 'সূর্য', 'meaning_en' => 'the sun'];
        $acc = "Bengali Word অরুণEnglish definition(noun) (1) the sun. (2) the newly-risen sun.\nBengali Word অরুচিEnglish definition apathy";
        $this->assertTrue(NameDesk::gives('accessibledictionary.gov.bd', $acc, $n));
        $this->assertFalse(NameDesk::gives('accessibledictionary.gov.bd', $acc, ['bn' => 'অরুচি', 'meaning_bn' => '', 'meaning_en' => 'sun']), 'the meaning of the next word does not count');
        $this->assertFalse(NameDesk::gives('accessibledictionary.gov.bd', 'Bengali Word অরুণিমাEnglish definition sun', $n));
        $this->assertTrue(NameDesk::gives('accessibledictionary.gov.bd', 'Bengali Word জ্যোতি, জ্যোতিঃEnglish definition light', ['bn' => 'জ্যোতি', 'meaning_bn' => 'আলো', 'meaning_en' => 'light']), 'a headword with variants');
        $wiki = "==Bengali==\n===Noun===\n# the [[sun]]\n==Sanskrit==\n# [[beginning]]\n";
        $this->assertTrue(NameDesk::gives('en.wiktionary.org', $wiki, $n));
        $this->assertFalse(NameDesk::gives('en.wiktionary.org', $wiki, ['bn' => 'অরুণ', 'meaning_bn' => 'নতুন', 'meaning_en' => 'beginning']), 'only the Bengali section counts');
        $this->assertFalse(NameDesk::gives('en.wiktionary.org', "==Bengali==\n# [[sunday]]\n", $n), 'whole words only');
        $this->assertFalse(NameDesk::gives('bn.wiktionary.org', "==বাংলা==\n# আলোচনা\n", ['bn' => 'অরুণ', 'meaning_bn' => 'আলো', 'meaning_en' => 'x']), 'আলো is not আলোচনা');
        $this->assertTrue(NameDesk::gives('bn.wiktionary.org', "=={{ভাষা|bn}}==\n# [[আলো]]\n", ['bn' => 'অরুণ', 'meaning_bn' => 'আলো', 'meaning_en' => 'x']));
    }

    public function test_arabic_and_persian_names_through_behind_the_name_and_the_source_word(): void
    {
        $n = ['bn' => 'ফারহান', 'roman' => 'Farhan', 'meaning_bn' => 'আনন্দিত', 'meaning_en' => 'happy, joyful', 'variants' => []];
        $btn = "Farhan\nGender Masculine\nMeaning & History\nMeans \"happy, joyful\" in Arabic.\nRelated Names\nhappy families";
        $this->assertTrue(NameDesk::gives('behindthename.com', $btn, $n));
        $this->assertFalse(NameDesk::gives('behindthename.com', "Meaning & History\nMeans \"sad\".\nRelated Names\nhappy joyful", $n), 'only the meaning part counts');
        $this->assertTrue(NameDesk::gives('behindthename.com', 'Meaning & History Means "abstaining, weaning". Related Names', ['bn' => 'x', 'meaning_bn' => '', 'meaning_en' => 'one who abstains']), 'abstains finds abstaining');
        Http::swap(new \Illuminate\Http\Client\Factory);   // instead of the dictionaries faked for the other tests
        Http::fake([
            'https://en.wiktionary.org/wiki/'.rawurlencode('ফারহান').'*' => Http::response("==Bengali==\n===Etymology===\n{{bor+|bn|ar|فَرْحَان}}\n===Proper noun===\n# {{given name|bn|male}}\n"),
            'https://en.wiktionary.org/wiki/'.rawurlencode('فرحان').'*' => Http::response("==Arabic==\n===Adjective===\n# [[happy]], [[glad]]\n==Persian==\n# x\n"),
            '*' => Http::response('', 404),
        ]);
        $this->assertTrue(NameDesk::lookup('en.wiktionary.org', $n), 'the Arabic word it comes from gives the meaning');
    }

    public function test_slots_are_tuesday_thursday_saturday_and_three_wait_at_most(): void
    {
        foreach (['a', 'o', 's'] as $i => $l) {
            NamePage::create(['gender' => 'boy', 'letter' => $l, 'status' => 'scheduled', 'publish_at' => CarbonImmutable::parse('2026-12-0'.(1 + 2 * $i).' 04:30')]);
        }
        $this->assertSame('2026-12-08 10:00', NameDesk::nextSlot(CarbonImmutable::parse('2026-11-27 11:30', 'Asia/Kolkata'))->timezone('Asia/Kolkata')->format('Y-m-d H:i'));
        Setting::put('names.day', null);
        $this->assertFalse(NameDesk::run(CarbonImmutable::parse('2026-11-27 11:30', 'Asia/Kolkata')));
    }
}
