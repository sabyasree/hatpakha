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
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2027-01-02 08:00', 'Asia/Kolkata'));
        $this->assertNull(\App\Support\Feelings::pickMonth(), 'December had too few answers');
        $this->assertNull($other->fresh()->readers_choice);
        \Illuminate\Support\Carbon::setTestNow();
    }
}
