<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Issue;
use App\Models\ReaderEntry;
use App\Models\ReaderPrompt;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Research 2026-11 (owner approved), sections A and C: writers list, search, shelves, empty pages, menus, sign-up. */
class NavigationAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function writer(string $slug, string $name, ?string $bn = null, array $extra = []): Author
    {
        return Author::create(['slug' => $slug, 'display_name' => $name, 'display_name_bn' => $bn] + $extra);
    }

    /** A2: every writer with a published piece, অ to হ, with the letter bar, counts, photos and a search box. */
    public function test_writers_list_is_sorted_by_bengali_name_with_letters_counts_and_search(): void
    {
        $kh = $this->writer('kh', 'Khokon', 'খোকন দাস', ['avatar_path' => '/storage/avatars/kh.jpg']);
        $o = $this->writer('o', 'Ohona', 'অহনা রায়');
        $en = $this->writer('en', 'Zara Ali');
        $none = $this->writer('none', 'Nobody', 'কেউ নন');
        $desk = $this->writer('hatpakha-desk', 'Hatpakha', 'হাতপাখা ডেস্ক', ['is_desk' => true]);
        $this->makePost(['slug' => 'a1', 'published_at' => '2026-11-01 10:00:00'], $kh);
        $this->makePost(['slug' => 'a2', 'published_at' => '2026-11-02 10:00:00'], $kh);
        $this->makePost(['slug' => 'b1', 'published_at' => '2026-11-03 10:00:00'], $o);
        $this->makePost(['slug' => 'c1', 'published_at' => '2026-11-03 10:00:00'], $en);
        $this->makePost(['slug' => 'd1', 'status' => 'pending', 'published_at' => null], $none);
        $this->makePost(['slug' => 'e1', 'published_at' => '2026-11-03 10:00:00'], $desk);

        $html = $this->get('/lekhok/')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/lekhok').'/">', false)
            ->assertSee('index, follow', false)
            ->assertSee('অহনা রায়')->assertSee('খোকন দাস')->assertSee('Zara Ali')
            ->assertDontSee('কেউ নন')->assertDontSee('হাতপাখা ডেস্ক</span>', false)
            ->assertSee('২টি লেখা')->assertSee('/storage/avatars/kh.jpg', false)
            ->assertSee('data-letter-bar', false)->assertSee('#harf-0', false)
            ->getContent();
        $this->assertTrue(strpos($html, 'অহনা রায়') < strpos($html, 'খোকন দাস') && strpos($html, 'খোকন দাস') < strpos($html, 'Zara Ali'), 'অ before খ before English names');

        $this->get('/lekhok/?q=খোকন')->assertOk()->assertSee('খোকন দাস')->assertDontSee('অহনা রায়')->assertSee('noindex, follow', false);
        $this->get('/lekhok/?page=9')->assertNotFound();
        $this->get('/sitemap-sections.xml')->assertSee(url('/lekhok').'/', false);
        $this->get('/archive/')->assertSee(url('/lekhok').'/', false)->assertDontSee('ওপরের খোঁজার ঘরে লেখকের নাম লিখুন');
    }

    public function test_writers_list_pages_after_the_first_are_noindex(): void
    {
        foreach (range(1, 61) as $i) {
            $a = $this->writer('w'.$i, 'Writer '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            $this->makePost(['slug' => 'p'.$i], $a);
        }
        $this->get('/lekhok/?page=2')->assertOk()->assertSee('noindex, follow', false)->assertSee('Writer 61');
        $this->get('/lekhok/')->assertSee('পরের পাতা →')->assertSee(url('/lekhok').'/?page=2', false);
    }

    /** A4: writers are matched in the database (folded spelling), never by loading every writer; C5: filters. */
    public function test_search_matches_writers_in_the_database_and_filters_by_section_length_and_date(): void
    {
        $tagore = $this->writer('tagore', 'Rabindranath Tagore', 'রবীন্দ্রনাথ ঠাকুর', ['published_count' => 2]);
        $story = Section::create(['slug' => 'short-stories', 'name_bn' => 'গল্প', 'format' => 'prose', 'is_active' => true]);
        $this->makePost(['slug' => 'brishti-kobita', 'title' => 'বৃষ্টির কবিতা', 'published_at' => '2026-10-01 10:00:00', 'reading_minutes' => 2], $tagore);
        $this->makePost(['slug' => 'brishti-golpo', 'title' => 'বৃষ্টির গল্প', 'section_id' => $story->id, 'format' => 'prose', 'published_at' => '2026-11-01 10:00:00'], $tagore);
        DB::table('posts')->where('slug', 'brishti-golpo')->update(['reading_minutes' => 20]);   // the model works it out from the text

        DB::enableQueryLog();
        $this->get('/search/?q=রবিন্দ্রনাথ')->assertOk()->assertSee('রবীন্দ্রনাথ ঠাকুর')->assertSee('বৃষ্টির কবিতা');
        $queries = collect(DB::getQueryLog())->pluck('query');
        $this->assertTrue($queries->contains(fn ($q) => str_starts_with($q, 'select') && str_contains($q, 'from "authors" where') && str_contains($q, '"search_name" like') && str_contains($q, 'limit 8')), 'Writers are matched in the database, at most 8');
        DB::disableQueryLog();
        $this->get('/search/?q=Tagore')->assertSee('রবীন্দ্রনাথ ঠাকুর');

        $this->get('/search/?q=বৃষ্টি')->assertSee('data-search-filters', false)->assertSee('৫ মিনিটের মধ্যে')->assertSee('লম্বা লেখা')->assertSee('সবচেয়ে প্রাসঙ্গিক')
            ->assertSee('noindex', false);
        $this->get('/search/?q=বৃষ্টি&section=short-stories')->assertSee('বৃষ্টির গল্প')->assertDontSee('বৃষ্টির কবিতা')->assertSee('noindex', false);
        $this->get('/search/?q=বৃষ্টি&length=short')->assertSee('বৃষ্টির কবিতা')->assertDontSee('বৃষ্টির গল্প');
        $this->get('/search/?q=বৃষ্টি&length=long')->assertSee('বৃষ্টির গল্প')->assertDontSee('বৃষ্টির কবিতা');
        $new = $this->get('/search/?q=বৃষ্টি&sort=new')->getContent();
        $this->assertLessThan(strpos($new, 'বৃষ্টির কবিতা</span>'), strpos($new, 'বৃষ্টির গল্প</span>'), 'Newest first');
        $old = $this->get('/search/?q=বৃষ্টি&sort=old')->getContent();
        $this->assertLessThan(strpos($old, 'বৃষ্টির গল্প</span>'), strpos($old, 'বৃষ্টির কবিতা</span>'), 'Oldest first');
        $this->get('/search/?q=বৃষ্টি&section=nowhere&length=x&sort=y')->assertOk()->assertSee('বৃষ্টির গল্প')->assertSee('বৃষ্টির কবিতা');   // unknown values are ignored
        $this->get('/search/?q=বৃষ্টি&section=short-stories&length=short')->assertSee('বাছাই সরিয়ে সব লেখায় খুঁজুন')->assertSee('data-read-next', false);
    }

    /** C4: shelves by length and by readers' answers (real answers in the last 90 days only). */
    public function test_shelves_by_length_and_by_readers_answers(): void
    {
        $prose = Section::create(['slug' => 'prabandha', 'name_bn' => 'প্রবন্ধ', 'format' => 'prose', 'is_active' => true]);
        $short = $this->makePost(['slug' => 'chhoto', 'title' => 'ছোট গদ্য', 'format' => 'prose', 'section_id' => $prose->id, 'reading_minutes' => 4]);
        $this->makePost(['slug' => 'boro', 'title' => 'বড় গদ্য', 'format' => 'prose', 'section_id' => $prose->id]);
        DB::table('posts')->where('slug', 'boro')->update(['reading_minutes' => 12]);
        $poem = $this->makePost(['slug' => 'kobita-1', 'title' => 'একটি কবিতা', 'reading_minutes' => 1]);

        $this->get('/tak/panch-minute/')->assertOk()->assertSee('৫ মিনিটের লেখা')->assertSee('ছোট গদ্য')->assertDontSee('বড় গদ্য')->assertDontSee('একটি কবিতা')
            ->assertSee('<link rel="canonical" href="'.url('/tak/panch-minute').'/">', false)->assertSee('index, follow', false);

        // Nobody has answered yet: the shelf is empty, out of Google, and leads somewhere.
        $this->get('/tak/mon-chhuyeche/')->assertOk()->assertSee('noindex, follow', false)->assertSee('data-read-next', false);
        $this->assertStringNotContainsString('/tak/mon-chhuyeche/', $this->get('/sitemap-sections.xml')->getContent());
        \Illuminate\Support\Facades\Cache::flush();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        DB::table('post_likes')->insert([
            ['user_id' => $u1->id, 'post_id' => $poem->id, 'feeling' => 'touched', 'created_at' => '2026-11-20 10:00:00'],
            ['user_id' => $u2->id, 'post_id' => $poem->id, 'feeling' => 'think', 'created_at' => '2026-11-21 10:00:00'],
            ['user_id' => $u1->id, 'post_id' => $short->id, 'feeling' => 'touched', 'created_at' => '2026-05-01 10:00:00'],   // older than 90 days
        ]);
        DB::table('guest_feelings')->insert(['post_id' => $short->id, 'device' => str_repeat('a', 64), 'net' => str_repeat('b', 64), 'feeling' => 'smile', 'created_at' => '2026-11-22 10:00:00']);
        $html = $this->get('/tak/mon-chhuyeche/')->assertOk()->assertSee('পাঠকের মন ছুঁয়েছে')->assertSee('একটি কবিতা')->assertSee('ছোট গদ্য')->assertSee('index, follow', false)->getContent();
        $this->assertLessThan(strpos($html, 'ছোট গদ্য</span>'), strpos($html, 'একটি কবিতা</span>'), 'Most answers first (2 against 1 in the last 90 days)');
        $this->get('/sitemap-sections.xml')->assertSee(url('/tak/mon-chhuyeche').'/', false)->assertSee(url('/tak/panch-minute').'/', false);
        $this->get('/archive/')->assertSee(url('/tak/mon-chhuyeche').'/', false);
        $this->get('/tak/onno-kichhu/')->assertNotFound();
    }

    /** A8: empty pages always offer something to read and a way to follow. */
    public function test_empty_pages_lead_somewhere(): void
    {
        $this->makePost(['slug' => 'notun', 'title' => 'নতুন লেখাটি']);
        \App\Models\Setting::put('home.pick', json_encode(['post_id' => $this->makePost(['slug' => 'bachai', 'title' => 'সম্পাদকের পছন্দের লেখা'])->id]));
        foreach (['/sonkha/', '/dharabahik/', '/sonkolon/'] as $page) {
            $this->get($page)->assertOk()->assertSee('data-read-next', false)->assertSee('নতুন লেখাটি')->assertSee('সম্পাদকের পছন্দের লেখা')->assertSee('সম্পাদকের বাছাই')
                ->assertSee('newsletter-card', false)->assertSee(url('/pathoker-pochhondo').'/', false);
        }
        Section::create(['slug' => 'notun-bibhag', 'name_bn' => 'নতুন বিভাগ', 'format' => 'prose', 'is_active' => true]);
        $this->get('/category/notun-bibhag/')->assertOk()->assertSee('data-read-next', false)->assertSee('নতুন লেখাটি');
        $this->get('/search/?q=কিছুইনেইএমন')->assertOk()->assertSee('data-read-next', false);
    }

    /** A6: the week's theme as a button under the title when last week's picks come first; the ten folded into 3 + the rest. */
    public function test_kolom_jumps_to_the_form_and_folds_the_picks(): void
    {
        $last = ReaderPrompt::create(['slug' => 'w1-abcd', 'kind' => 'six', 'title' => 'বৃষ্টি', 'ask' => 'ছয় শব্দে বৃষ্টি', 'status' => 'published',
            'opens_at' => '2026-11-16 03:00:00', 'closes_at' => '2026-11-21 12:30:00', 'published_at' => '2026-11-24 02:00:00']);
        foreach (range(1, 10) as $i) {
            ReaderEntry::create(['reader_prompt_id' => $last->id, 'name' => 'পাঠক '.$i, 'body' => 'বাছাই লেখা নম্বর '.$i, 'sender_hash' => 'h'.$i, 'status' => 'picked', 'ai_score' => 100 - $i]);
        }
        ReaderPrompt::create(['slug' => 'w2-efgh', 'kind' => 'six', 'title' => 'মা', 'ask' => 'ছয় শব্দে মা', 'status' => 'open', 'opens_at' => '2026-11-23 03:00:00', 'closes_at' => '2026-11-28 12:30:00']);

        $html = $this->get('/kolom/')->assertOk()->assertSee('data-kolom-jump', false)->assertSee('এ সপ্তাহের বিষয়: “মা” · লিখুন ↓')
            ->assertSee('আরও ৭টি পড়ুন')->assertSee('বাছাই লেখা নম্বর 10')->getContent();
        $this->assertLessThan(strpos($html, 'data-kolom-rest'), strpos($html, 'বাছাই লেখা নম্বর 3'));
        $this->assertGreaterThan(strpos($html, 'data-kolom-rest'), strpos($html, 'বাছাই লেখা নম্বর 4'));
        $this->assertLessThan(strpos($html, '<section id="picks"'), strpos($html, 'data-kolom-jump'), 'The button is under the title, above the picks');
        $this->get($last->url())->assertOk()->assertDontSee('data-kolom-rest', false)->assertSee('বাছাই লেখা নম্বর 10');   // the week's own page shows all ten
    }

    /** A9, C7, C8: footer columns, the bottom bar's centre, the আরও sheet, the desktop menu. */
    public function test_footer_bottom_bar_sheet_and_top_menu(): void
    {
        $this->makePost(['slug' => 'ek']);
        $html = $this->get('/ek/')->assertOk()->getContent();
        foreach (['পড়ুন', 'লিখুন', 'হাতপাখা'] as $head) {
            $this->assertStringContainsString('font-extrabold text-sky">'.$head.'</h2>', $html);
        }
        foreach (['/lekhok', '/sonkha', '/dharabahik', '/submissions', '/submit', '/kolom', '/khela/aaj', '/about-hatpakha', '/contact-us', '/advertise', '/privacy-policy', '/terms-conditions', '/feed', '/archive'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'/"', $html, $path.' in the menus or footer, with its slash');
        }
        // Bottom bar: the raised centre is সংখ্যা (all issues until one is out); লিখুন is in the sheet.
        $this->assertMatchesRegularExpression('~data-tab-centre[^>]*>.*?সংখ্যা~s', $html);
        $this->assertStringContainsString('href="'.url('/sonkha').'/" data-tab-centre', $html);
        $this->assertStringContainsString('data-more-write', $html);
        $this->assertLessThan(strpos($html, 'নমস্কার') ?: strpos($html, 'অ্যাকাউন্ট থাকলে'), strpos($html, 'data-more-write'), 'লিখুন near the top of the sheet');
        // Desktop: সংখ্যা and ধারাবাহিক come before খেলা.
        $nav = substr($html, strpos($html, 'aria-label="প্রধান বিভাগ"'), 6000);
        $this->assertLessThan(strpos($nav, '>খেলা<'), strpos($nav, '>সংখ্যা<'));
        $this->assertLessThan(strpos($nav, '>খেলা<'), strpos($nav, '>ধারাবাহিক<'));

        $issue = Issue::create(['slug' => '2026-11', 'title' => 'নভেম্বর সংখ্যা', 'status' => 'published', 'published_at' => '2026-11-01 00:00:00']);
        $p = $this->makePost(['slug' => 'issue-piece']);
        $p->forceFill(['issue_id' => $issue->id])->saveQuietly();
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertStringContainsString('href="'.$issue->url().'" data-tab-centre', $this->get('/ek/')->getContent());
    }

    /** C9: one password box, Google first when ready, the owner's 8-character rule explained; no phone autofocus on sign-in. A10: tap targets. */
    public function test_sign_up_has_one_password_box_and_the_eight_character_rule(): void
    {
        $this->get('/register/')->assertOk()->assertDontSee('password_confirmation', false)->assertSee('কমপক্ষে ৮ অক্ষর')->assertDontSee('Google দিয়ে অ্যাকাউন্ট খুলুন')
            ->assertSee('class="inline-flex min-h-11 items-center underline" target="_blank">শর্তাবলি', false);
        config(['services.google.client_id' => 'x.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
        $html = $this->get('/register/')->assertSee('Google দিয়ে অ্যাকাউন্ট খুলুন')->getContent();
        $this->assertLessThan(strpos($html, 'name="email"'), strpos($html, 'Google দিয়ে অ্যাকাউন্ট খুলুন'), 'Google first');
        $this->get('/login/')->assertOk()->assertDontSee(' autofocus', false)->assertSee('data-focus-wide', false);

        $started = fn () => preg_match('/name="started" value="([^"]+)"/', $this->get('/register/')->getContent(), $m) ? html_entity_decode($m[1]) : '';
        $base = ['name' => 'রিনা দাস', 'terms' => '1'];
        $this->travel(10)->seconds();
        $s = $started();
        $this->travel(10)->seconds();
        $this->post('/register/', $base + ['email' => 'short@example.com', 'password' => 'abc1234', 'started' => $s])->assertSessionHasErrors('password');
        $this->assertSame(0, User::where('email', 'short@example.com')->count());
        $s = $started();
        $this->travel(10)->seconds();
        $this->post('/register/', $base + ['email' => 'rina@example.com', 'password' => 'brishtipore', 'started' => $s])->assertSessionHasNoErrors();   // 8+ letters, no number, no second box
        $this->assertSame(1, User::where('email', 'rina@example.com')->count());
    }

    public function test_password_rule_is_eight_characters_and_still_refuses_breached_ones(): void
    {
        $rule = \App\Support\PasswordPolicy::rule();
        $this->assertFalse(validator(['p' => 'abcdefg'], ['p' => [$rule]])->passes());
        $this->assertTrue(validator(['p' => 'abcdefgh'], ['p' => [$rule]])->passes());
        $this->assertTrue((fn () => $this->uncompromised)->call($rule), 'A leaked password is still refused (breach check kept)');
        $this->assertFalse((fn () => $this->letters || $this->numbers)->call($rule), 'No letters-and-numbers rule (owner, 2026-11-24)');
    }

    /** A10: the small links on a piece are real tap targets, the meta text 16px. */
    public function test_piece_page_tap_targets(): void
    {
        $this->makePost(['slug' => 'tap']);
        $html = $this->get('/tap/')->assertOk()->getContent();
        $this->assertStringContainsString('<nav aria-label="অবস্থান" class="flex flex-wrap items-center text-base text-ink2">', $html);
        $this->assertMatchesRegularExpression('~class="inline-flex min-h-11 items-center rounded-full bg-red px-4 font-bold text-white">কবিতা</a>~u', $html);
        $this->assertMatchesRegularExpression('~<a href="[^"]+/author/[^"]+/" class="inline-flex min-h-11 items-center font-semibold hover:text-red">~', $html);
    }
}
