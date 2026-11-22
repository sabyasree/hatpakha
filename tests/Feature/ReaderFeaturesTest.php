<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\User;
use App\Notifications\NewFromWriter;
use App\Support\FollowNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** D64: search, likes, reading list, following writers. */
class ReaderFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_titles_writers_and_text_and_is_noindex(): void
    {
        $writer = Author::create(['slug' => 'rina', 'display_name' => 'Rina Sen', 'display_name_bn' => 'রিনা সেন', 'published_count' => 1]);
        $this->makePost(['status' => 'published', 'slug' => 'barsha', 'title' => 'বর্ষার দিন', 'published_at' => now()], $writer);
        $this->makePost(['status' => 'published', 'slug' => 'other', 'title' => 'অন্য লেখা', 'body_html' => '<p>নদীর ধারে জোনাকি জ্বলে।</p>', 'published_at' => now()]);
        $this->makePost(['status' => 'pending', 'slug' => 'hidden', 'title' => 'বর্ষার গোপন খসড়া']);

        $this->get('/search/?q=বর্ষা')->assertOk()->assertSee('বর্ষার দিন')->assertDontSee('বর্ষার গোপন খসড়া')->assertSee('noindex', false);
        $this->get('/search/?q=রিনা')->assertSee('রিনা সেন')->assertSee('বর্ষার দিন');
        $this->get('/search/?q=জোনাকি')->assertSee('অন্য লেখা');
        $this->get('/search/?q=%25')->assertOk();   // a wildcard is searched as a plain character
    }

    public function test_like_save_follow_need_an_account_and_toggle(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $this->postJson('/like/'.$post->id)->assertUnauthorized();
        $this->get('/p/')->assertSee('পড়ে কেমন লাগল?')->assertSee('ভাবিয়ে তুলল');

        $reader = User::factory()->create();
        $this->actingAs($reader)->postJson('/like/'.$post->id)->assertJson(['on' => true, 'count' => 1]);
        $this->postJson('/like/'.$post->id)->assertJson(['on' => false, 'count' => 0]);
        $this->postJson('/save/'.$post->id)->assertJson(['on' => true]);
        $this->get('/account/reading/')->assertSee('সেভ করা লেখা')->assertSee($post->title);

        $author = $post->authors->first();
        $this->postJson('/follow/'.$author->id)->assertJson(['on' => true, 'count' => 1]);
        $this->get('/account/reading/')->assertSee($author->publicName());
    }

    public function test_followers_hear_once_when_a_new_piece_goes_live(): void
    {
        config(['mail.default' => 'smtp']);
        Notification::fake();
        $post = $this->makePost(['status' => 'published', 'slug' => 'new', 'published_at' => now()->subMinute()]);
        $fan = User::factory()->create();
        \Illuminate\Support\Facades\DB::table('follows')->insert(['user_id' => $fan->id, 'author_id' => $post->authors->first()->id]);
        FollowNotifier::run();
        FollowNotifier::run();
        Notification::assertSentToTimes($fan, NewFromWriter::class, 1);
    }

    public function test_writer_box_shows_under_the_piece(): void
    {
        $writer = Author::create(['slug' => 'mitra', 'display_name' => 'Mitra', 'bio' => 'কবিতা লিখি।', 'published_count' => 3, 'honour_label' => 'মাসের লেখক']);
        $this->makePost(['status' => 'published', 'slug' => 'k', 'published_at' => now()], $writer);
        $this->get('/k/')->assertSee('কবিতা লিখি।')->assertSee('মাসের লেখক')->assertSee('অনুসরণ করুন')->assertSee('-এর সব লেখা', false);
    }

    public function test_a_reader_can_share_a_line_as_a_card_from_any_live_piece(): void
    {
        // D173 (owner): select a line → "এই লাইন শেয়ার করুন" → a Hatpakha card drawn in the browser.
        $post = $this->makePost(['slug' => 'line', 'title' => 'নদীর গান']);
        $this->get($post->url())->assertOk()->assertSee('data-quote-pill', false)->assertSee('data-quote-canvas', false)
            ->assertSee('data-quote-title="নদীর গান"', false)->assertSee('এই লাইন শেয়ার করুন');
        $post->forceFill(['status' => 'draft'])->save();
        $this->actingAs(\App\Models\User::factory()->create(['role' => \App\Enums\Role::Admin]))->get($post->url())->assertDontSee('data-quote-pill', false);
    }

    /** D231 (owner: "we can do something from this idea"; "build it, it should be automatic... no need to come to me"). */
    public function test_a_reader_says_how_a_piece_left_them_and_the_month_gets_a_readers_choice(): void
    {
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-11-20 12:00', 'Asia/Kolkata'));
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'title' => 'নদীর ধারে', 'published_at' => now()->subDays(5)]);
        $other = $this->makePost(['status' => 'published', 'slug' => 'q', 'title' => 'অন্য লেখা', 'published_at' => now()->subDays(5)]);
        $reader = User::factory()->create();

        $this->actingAs($reader)->postJson('/like/'.$post->id, ['feeling' => 'think'])->assertJson(['on' => true, 'feeling' => 'think', 'count' => 1, 'counts' => ['think' => 1, 'touched' => 0]]);
        $this->postJson('/like/'.$post->id, ['feeling' => 'touched'])->assertJson(['feeling' => 'touched', 'count' => 1, 'counts' => ['think' => 0, 'touched' => 1]]);   // one answer a reader: changed, not added
        $this->get('/p/')->assertSee('aria-pressed="true"', false);
        $this->postJson('/like/'.$post->id, ['feeling' => 'touched'])->assertJson(['on' => false, 'count' => 0]);   // the same again takes it back
        $this->postJson('/like/'.$post->id, ['feeling' => 'made-up'])->assertJson(['on' => true, 'feeling' => 'heart', 'count' => 1]);   // an unknown word is a plain heart
        $this->post('/like/'.$post->id, ['feeling' => 'stay'])->assertRedirect();   // works without JavaScript
        $this->assertSame('১ জনের মনে থেকে যাবে', \App\Support\Feelings::summary($post->fresh()));

        // Nine more readers: ten answers in November. One answer on the other piece.
        foreach (User::factory()->count(9)->create() as $i => $u) {
            \App\Support\Feelings::set($u, $post, $i < 6 ? 'touched' : 'think');
        }
        \App\Support\Feelings::set($reader, $other, 'smile');
        $this->assertStringStartsWith('৬ জনের মন ছুঁয়েছে · ৩ জনকে ভাবিয়েছে', \App\Support\Feelings::summary($post->fresh()));
        $this->assertSame(10, $post->fresh()->likes_count);

        // December: the month's choice is made by itself, once; too few answers and nobody is named.
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-12-01 08:00', 'Asia/Kolkata'));
        $this->assertSame($post->id, \App\Support\Feelings::pickMonth()?->id);
        $this->assertSame('2026-11', $post->fresh()->readers_choice);
        $this->assertNull(\App\Support\Feelings::pickMonth(), 'Once a month');
        $this->get('/p/')->assertSee('পাঠকের পছন্দ · নভেম্বর ২০২৬');
        $block = collect(\App\Ai\EditorJobs::comeBack())->firstWhere('kind', 'choice');
        $this->assertSame('পাঠকের পছন্দ: নদীর ধারে', $block['title']);

        // D232 (owner: "build all 4"): the home page, the writer's page, a page by feeling, and true numbers for the AI.
        $this->get('/pathoker-pochhondo/')->assertOk()->assertSee('মাসের পাঠকের পছন্দ')->assertSee('নদীর ধারে')
            ->assertSee('যে লেখা পাঠকের মন ছুঁয়েছে')->assertSee('৬ জনের মন ছুঁয়েছে')->assertSee('যে লেখা পাঠককে হাসিয়েছে')->assertSee('অন্য লেখা')
            ->assertDontSee('যে লেখা পাঠককে চমকে দিয়েছে')->assertSee('<link rel="canonical" href="'.url('/pathoker-pochhondo').'/"', false);
        $this->get('/pathoker-pochhondo')->assertRedirect();   // trailing slash, as every public address
        \Illuminate\Support\Facades\Cache::flush();
        $this->get('/')->assertSee('পাঠকের পছন্দ, নভেম্বর ২০২৬')->assertSee('নদীর ধারে');
        $this->get($post->authors->first()->url())->assertSee('পাঠকের পছন্দ · নভেম্বর ২০২৬');
        $this->assertSame('6 said it moved them, 3 said it made them think, 1 said it will stay with them', \App\Support\Feelings::facts($post->fresh(), 5));
        $this->assertSame('', \App\Support\Feelings::facts($other->fresh(), 5), 'One answer is not a number to print on a card');
        $serial = \App\Models\Serial::create(['title' => 'লাল কাচ', 'slug' => 'lal-kach', 'status' => 'active', 'writer' => 'serial_mystery']);
        $post->forceFill(['serial_id' => $serial->id, 'episode_no' => 1])->saveQuietly();
        $this->assertStringContainsString('readers: 6 said it moved them', \App\Support\SerialSignals::forEpisode($serial));
        $this->assertStringContainsString('most often said "it moved them"', \App\Support\SerialSignals::forEpisode($serial));

        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2027-01-02 08:00', 'Asia/Kolkata'));
        $this->assertNull(\App\Support\Feelings::pickMonth(), 'December had too few answers');
        $this->assertNull($other->fresh()->readers_choice);
        \Illuminate\Support\Carbon::setTestNow();
    }

    /** D236: the owner's WhatsApp Channel is offered under a piece and in the footer. */
    public function test_the_whatsapp_channel_is_offered_to_readers(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()]);
        $this->get('/p/')->assertSee('https://whatsapp.com/channel/0029VbA3b0DICVfp4sWcgb1F', false)->assertSee('হোয়াটসঅ্যাপে খবর নিন');
        config(['services.social.whatsapp' => null]);
        $this->get('/p/')->assertDontSee('whatsapp.com/channel', false);
    }

    /** D241 (owner: "the scroll is too much... he is not able to find it"; two UX reviews; "2nd ad at the end; cards in one line"). */
    public function test_under_a_piece_the_reader_finds_the_response_card_first_and_the_ads_keep_their_distance(): void
    {
        config(['services.adsense.slots' => ['article' => '3', 'article_2' => '4'], 'services.adsense.force' => true]);
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'title' => 'নদীর ধারে', 'published_at' => now()->subDay()]);
        foreach (['ক', 'খ', 'গ'] as $i => $t) {
            $this->makePost(['status' => 'published', 'slug' => 'r'.$i, 'title' => 'পরের লেখা '.$t, 'section_id' => $post->section_id, 'published_at' => now()->subDays(2 + $i)]);
        }
        foreach (['প্রথম মত', 'দ্বিতীয় মত', 'তৃতীয় মত'] as $i => $body) {
            \App\Models\Comment::create(['post_id' => $post->id, 'author_name' => 'পাঠক '.($i + 1), 'author_email' => "r{$i}@example.test", 'status' => 'approved', 'body' => $body, 'created_at' => now()->subHours(9 - $i)]);
        }
        $html = $this->get('/p/')->assertOk()->getContent();
        $at = fn (string $needle) => tap(strpos($html, $needle), fn ($p) => $this->assertNotFalse($p, $needle));
        // the order a reader meets things in
        $order = [$at('পড়ে কেমন লাগল?'), $at('লেখককে দু লাইন লিখুন'), $at('মতামত পাঠান'), $at('সব পড়ুন ↓'), $at('হোয়াটসঅ্যাপে পাঠান'), $at('data-ad-slot="3"'),
            $at('aria-label="লেখক"'), $at('id="next-h"'), $at('id="comments"'), $at('data-ad-slot="4"'), $at('id="news-h"')];
        $this->assertSame($order, collect($order)->sort()->values()->all(), 'Card, sharing, ad, writer, more pieces, every comment, ad, news');
        // the card shows the two newest; the full list has all three
        $card = substr($html, $at('id="reactions"'), $at('ভালো লাগলে বন্ধুকে পাঠান') - $at('id="reactions"'));
        $this->assertStringContainsString('তৃতীয় মত', $card);
        $this->assertStringContainsString('দ্বিতীয় মত', $card);
        $this->assertStringNotContainsString('প্রথম মত', $card);
        $this->assertSame(2, substr_count($html, 'প্রথম মত') + substr_count($card, 'তৃতীয় মত'), 'Oldest once (full list); newest in the card');
        // a guest still writes with a name only, and may keep the comment off the front page
        $this->assertStringContainsString('name="name"', $card);
        $this->assertStringContainsString('name="not_featured"', $card);
        // no button or link between the rule above the first ad and the ad itself
        $gap = substr($html, $at('পাঠকের পছন্দের লেখা দেখুন</a>'), $at('data-ad-slot="3"') - $at('পাঠকের পছন্দের লেখা দেখুন</a>'));
        $this->assertStringNotContainsString('<button', $gap);
        $this->assertStringContainsString('border-t', $gap);
        // more pieces: cards in one line that scrolls sideways
        $this->assertStringContainsString('overflow-x-auto', $html);
        $this->assertStringContainsString('পরের লেখা ক', $html);

        // D252: a guest's tap is recorded at once, without signing in, and counted once a phone
        $this->post('/feel/'.$post->id, ['feeling' => 'think'])->assertRedirect($post->url().'#reactions')->assertCookie('hp_dev');
        $this->assertSame(1, (int) $post->fresh()->likes_count);
    }

    public function test_a_childrens_piece_has_the_card_without_comments_or_the_first_ad(): void
    {
        config(['services.adsense.slots' => ['article' => '3', 'article_2' => '4'], 'services.adsense.force' => true]);
        $kids = \App\Models\Section::firstOrCreate(['slug' => 'chhotoder'], ['name_bn' => 'ছোটদের হাতপাখা', 'name_en' => 'Kids']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'k', 'section_id' => $kids->id, 'published_at' => now()->subDay()]);
        $this->assertTrue($post->fresh()->isKids());
        $this->get('/k/')->assertOk()->assertSee('পড়ে কেমন লাগল?')->assertDontSee('লেখককে দু লাইন লিখুন')->assertDontSee('data-ad-slot="3"', false)->assertDontSee('হোয়াটসঅ্যাপে খবর নিন');
    }
}
