<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\User;
use App\Support\ReadingHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D166: the account redesign the owner approved (mockups tested with older users): "আমার", "আরও", আমার পড়া/লেখা. */
class AccountRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_says_amar_or_dhukun_and_the_more_sheet_holds_everything(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('>সাইন ইন</a>', false)                 // header, before signing in
            ->assertSee('data-more-open', false)->assertSee('মেনু')->assertSee('সাইন আপ')
            ->assertSee('পুরনো লেখা')->assertSee('সাহায্য ও যোগাযোগ')
            ->assertDontSee('>আর্কাইভ</span>', false);         // the archive moved from the bar into the sheet

        $reader = User::factory()->create(['role' => Role::Reader, 'name' => 'মিতা সেন']);
        $this->actingAs($reader)->get('/')->assertOk()
            ->assertSee('aria-label="আমার অ্যাকাউন্ট"', false)->assertSee('নমস্কার, মিতা')
            ->assertSee('ই-মেল, পাসওয়ার্ড, সাপ্তাহিক ই-মেল, ফোনে নোটিফিকেশন')   // each account row says what is inside
            ->assertSee('লগ আউট')->assertDontSee('নতুন লেখা পাঠান</span>', false);
    }

    public function test_a_reader_lands_on_amar_pora_and_never_sees_writing_pages(): void
    {
        $reader = User::factory()->create(['role' => Role::Reader]);
        $this->actingAs($reader)->get('/account/')->assertRedirect(url('/account/reading').'/');
        $page = $this->get('/account/reading/')->assertOk()
            ->assertSee('লাইব্রেরি')->assertSee('প্রোফাইল')->assertSee('সেটিংস')
            ->assertSee('কীভাবে লেখা পাঠাবেন')->assertDontSee('+ নতুন লেখা পাঠান')->assertDontSee('অ্যাকাউন্টটি আরও নিরাপদ করুন')
            ->getContent();
        $this->assertStringContainsString('grid-cols-3', $page, 'A reader has three tabs');
        $this->assertStringNotContainsString('আবার আসবেন', $page, 'Account pages end with one quiet line, not the big footer');
    }

    public function test_reading_history_remembers_how_far_and_can_be_switched_off_and_cleared(): void
    {
        $reader = User::factory()->create(['role' => Role::Reader]);
        $post = $this->makePost(['slug' => 'golpo', 'title' => 'প্রথম মন খারাপের সকাল']);
        $this->actingAs($reader);

        $this->get($post->url())->assertSee('data-read-track=', false);
        $this->post('/reading/'.$post->id, ['p' => 40])->assertNoContent();
        $this->post('/reading/'.$post->id, ['p' => 20])->assertNoContent();   // the furthest point counts
        $this->assertSame(40, (int) DB::table('reading_history')->where('user_id', $reader->id)->value('progress'));
        $this->get('/account/reading/')->assertSee('যেখানে থেমেছিলেন')->assertSee('#resume-40', false)->assertSee('৪০% পড়েছেন');

        $this->post('/account/reading-history/', ['on' => 0])->assertRedirect();
        $this->assertFalse($reader->fresh()->reading_history);
        $this->get($post->url())->assertDontSee('data-read-track=', false);
        $this->post('/reading/'.$post->id, ['p' => 90]);
        $this->assertSame(40, (int) DB::table('reading_history')->where('user_id', $reader->id)->value('progress'), 'Off: nothing more is recorded');

        DB::table('saved_posts')->insert(['user_id' => $reader->id, 'post_id' => $post->id, 'created_at' => now()]);
        $this->post('/account/reading-history/clear/')->assertRedirect();
        $this->assertSame(0, DB::table('reading_history')->where('user_id', $reader->id)->count());
        $this->assertSame(1, DB::table('saved_posts')->where('user_id', $reader->id)->count(), 'Saved pieces stay');

        DB::table('reading_history')->insert(['user_id' => $reader->id, 'post_id' => $post->id, 'progress' => 10, 'read_at' => now()->subYears(2)]);
        $this->assertSame(1, ReadingHistory::prune());
    }

    public function test_each_route_has_its_own_rate_limit_counter(): void
    {
        // Found in the browser walk-through: reading pings and a few saves used up the 5 a minute for clearing.
        $reader = User::factory()->create(['role' => Role::Reader]);
        $post = $this->makePost(['slug' => 'golpo', 'title' => 'গল্প']);
        $this->actingAs($reader);
        for ($i = 1; $i <= 12; $i++) {
            $this->post('/reading/'.$post->id, ['p' => $i * 5])->assertNoContent();
        }
        $this->post('/account/reading-history/clear/')->assertRedirect();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/account/reading-history/clear/');
        }
        $this->post('/account/reading-history/clear/')->assertStatus(429);   // its own limit still holds
        $this->post('/account/profile/', ['name' => 'মিতা'])->assertRedirect();   // and doesn't block other forms
    }

    public function test_new_from_followed_writers_shows_until_read(): void
    {
        $reader = User::factory()->create(['role' => Role::Reader]);
        $author = Author::create(['slug' => 'kobi', 'display_name' => 'Saptarshi Khanra']);
        $post = $this->makePost(['slug' => 'futpath', 'title' => 'ফুটপাত', 'published_at' => now()->subDays(2)], $author);
        DB::table('follows')->insert(['user_id' => $reader->id, 'author_id' => $author->id, 'created_at' => now()]);
        $this->actingAs($reader)->get('/account/reading/')->assertSee('আপনার জন্য নতুন')->assertSee('ফুটপাত')->assertSee('Saptarshi Khanra');
        ReadingHistory::record($reader, $post, 100);
        $this->get('/account/reading/')->assertDontSee('আপনার জন্য নতুন');
    }

    public function test_a_writer_lands_on_amar_lekha_with_plain_actions(): void
    {
        $user = User::factory()->create(['role' => Role::Writer, 'name' => 'Rina Basu']);
        $author = Author::create(['slug' => 'rina-basu', 'display_name' => 'Rina Basu', 'display_name_bn' => 'রিনা বসু', 'user_id' => $user->id, 'published_count' => 1]);
        $live = $this->makePost(['slug' => 'shiter-sokal', 'title' => 'শীতের সকাল'], $author);
        $this->makePost(['slug' => 'mayer-chithi', 'title' => 'মায়ের চিঠি', 'status' => 'changes', 'review_note' => 'শেষ স্তবকটা আর একবার পড়ে দেখবেন?', 'published_at' => null], $author);
        DB::table('post_views')->insert(['post_id' => $live->id, 'day' => now()->toDateString(), 'views' => 18]);

        $this->actingAs($user)->get('/account/')->assertOk()
            ->assertSee('নমস্কার, রিনা')->assertSee('আপনাকে দেখতে হবে')->assertSee('শেষ স্তবকটা আর একবার পড়ে দেখবেন?')
            ->assertSee('লেখাটি খুলে ঠিক করুন')->assertSee('১৮ বার খোলা হয়েছে')
            ->assertSee('https://wa.me/?text=', false)->assertSee('সার্টিফিকেট')->assertSee('লিংক কপি')
            ->assertSee('ছবি ও নাম বদলান')->assertSee('grid-cols-4', false);
        $this->get('/')->assertSee('১টি কাজ আছে');   // the "আরও" sheet tells the writer
    }

    public function test_writer_can_save_a_bengali_name_alone_and_is_told_it_is_kept(): void
    {
        $user = User::factory()->create(['role' => Role::Writer, 'name' => 'rina']);
        $author = Author::create(['slug' => 'rina', 'display_name' => 'rina', 'user_id' => $user->id, 'published_count' => 1]);
        $this->actingAs($user)->post('/account/profile/', ['name_bn' => 'রিনা বসু', 'bio' => 'হাওড়ায় থাকি।'])
            ->assertRedirect(url('/account/profile').'/')->assertSessionHas('status', 'সেভ হয়েছে। পাঠকেরা এখন এভাবেই আপনাকে দেখবেন।');
        $this->assertSame('রিনা বসু', $author->fresh()->display_name_bn);
        $this->assertSame('রিনা বসু', $user->fresh()->name);
    }

    public function test_settings_switches_say_what_they_do_now(): void
    {
        $reader = User::factory()->create(['role' => Role::Reader]);
        $this->actingAs($reader)->get('/account/settings/')->assertOk()
            ->assertSee('সাপ্তাহিক ই-মেল')->assertSee('>চালু<', false)->assertSee('role="switch"', false)
            ->assertSee('পড়ার হিস্টরি রাখুন')->assertSee('সেভ করা লেখা, লাইক করা লেখা আর আপনার নিজের লেখা যেমন আছে থাকবে')
            ->assertSee('হ্যাঁ, মুছে দিন')->assertSee('পাসওয়ার্ড বদলান')->assertSee('টু-স্টেপ ভেরিফিকেশন')->assertSee('লগ আউট');
        $this->post('/account/newsletter/', ['newsletter' => 0]);
        $this->get('/account/settings/')->assertSee('>বন্ধ<', false);
    }

    /** Owner, 2026-11-14: "পাঠকের কলম and games are not present in the computer screen menu, and also the আরও mobile menu". */
    public function test_games_and_readers_corner_are_in_every_menu_for_guests_and_members(): void
    {
        foreach ([null, User::factory()->create(['role' => Role::Reader])] as $who) {
            $who ? $this->actingAs($who) : null;
            $html = $this->get('/')->assertOk()->getContent();
            $sheet = substr($html, strpos($html, 'id="more-sheet"'));
            $top = substr($sheet, 0, strpos($sheet, 'সার্চ'));
            $this->assertStringContainsString('/khela/', $top, 'Games sit at the top of the phone menu');
            $this->assertStringContainsString('/kolom/', $top, 'পাঠকের কলম sits at the top of the phone menu');
            $bar = substr($html, strpos($html, 'aria-label="প্রধান বিভাগ"'), 9000);
            $this->assertStringContainsString('/khela/', $bar, 'Games are in the computer menu');
            $this->assertStringContainsString('/kolom/', $bar, 'পাঠকের কলম is in the computer menu');
        }
    }
}
