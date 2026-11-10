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
        $this->get('/p/')->assertSee('ভালো লেগেছে');

        $reader = User::factory()->create();
        $this->actingAs($reader)->postJson('/like/'.$post->id)->assertJson(['on' => true, 'count' => 1]);
        $this->postJson('/like/'.$post->id)->assertJson(['on' => false, 'count' => 0]);
        $this->postJson('/save/'.$post->id)->assertJson(['on' => true]);
        $this->get('/account/reading/')->assertSee('পরে পড়ব')->assertSee($post->title);

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
}
