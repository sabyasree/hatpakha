<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\TemplateMail;
use App\Support\CommentNews;
use App\Support\Feelings;
use App\Support\PieceLive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * D252 (owner: "build A, B; the writer gets to know of a comment by e-mail; a reply option in comments; the commenter
 * gets to know of a reply; the e-mail link brings the user to that comment directly").
 */
class CommentsAndSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $writer;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-22 10:00', 'Asia/Kolkata'));
        config(['mail.off_live' => 'allow', 'mail.default' => 'smtp']);
        $this->writer = User::factory()->create(['name' => 'Megha', 'email' => 'megha@example.com', 'email_verified_at' => now()]);
        $a = Author::create(['slug' => 'megha', 'display_name' => 'মেঘলা', 'user_id' => $this->writer->id]);
        $this->post = $this->makePost(['slug' => 'brishti', 'title' => 'বৃষ্টির দিন', 'status' => 'published', 'published_at' => '2026-11-21 09:00:00', 'writer_live_told_at' => now()], $a);
        Notification::fake();
    }


    public function test_a_reply_joins_the_thread_and_the_reader_lands_on_it(): void
    {
        $reader = User::factory()->create(['name' => 'Anirban', 'email_verified_at' => now()]);
        $c = Comment::create(['post_id' => $this->post->id, 'user_id' => $reader->id, 'author_name' => 'Anirban', 'body' => 'শেষ লাইনটা মনে থাকবে।', 'status' => 'approved']);
        $started = \Illuminate\Support\Facades\Crypt::encryptString((string) now()->subMinute()->timestamp);

        $this->actingAs($this->writer)->post('/comment/'.$this->post->id, ['body' => 'ধন্যবাদ!', 'parent_id' => $c->id, 'started' => $started])
            ->assertRedirect($this->post->url().'#comment-'.$c->id);
        $reply = Comment::where('parent_id', $c->id)->firstOrFail();
        $reply->forceFill(['status' => 'approved'])->save();
        $second = Comment::create(['post_id' => $this->post->id, 'author_name' => 'X', 'body' => 'আমিও', 'status' => 'approved', 'parent_id' => $c->id]);
        $this->post('/comment/'.$this->post->id, ['body' => 'উত্তরের উত্তর', 'parent_id' => $second->id, 'started' => $started]);
        $this->assertSame($c->id, Comment::where('body', 'উত্তরের উত্তর')->value('parent_id'), 'One level: a reply to a reply joins the thread');

        auth()->logout();
        $html = $this->get('/brishti/')->assertOk()->getContent();
        $this->assertStringContainsString('id="comment-'.$c->id.'"', $html);
        $this->assertStringContainsString('id="comment-'.$reply->id.'"', $html);
        $this->assertStringContainsString('name="parent_id" value="'.$c->id.'"', $html);
        $this->assertStringContainsString('উত্তর দিন', $html);
        $this->assertStringContainsString('লেখক হিসেবে উত্তর দিতে আগে', $html, 'A writer who came from the e-mail signed out is told to sign in first');
        $this->assertLessThan(strpos($html, 'id="comment-'.$reply->id.'"'), strpos($html, 'id="comment-'.$c->id.'"'));
    }

    public function test_the_writer_is_told_within_minutes_straight_to_the_comment_but_not_more_than_hourly(): void
    {
        $this->assertTrue(\App\Support\BulkMail::real());
        $c = Comment::create(['post_id' => $this->post->id, 'author_name' => 'Rina', 'body' => 'খুব ভালো।', 'status' => 'approved']);
        $sent = CommentNews::send();
        $this->assertSame(1, $sent);
        Notification::assertSentTo($this->writer, TemplateMail::class, fn ($n) => $n->key === 'new_comments' && $n->url === $this->post->url().'#comment-'.$c->id);
        Comment::create(['post_id' => $this->post->id, 'author_name' => 'Tapas', 'body' => 'আবার পড়লাম।', 'status' => 'approved']);
        $this->assertSame(0, CommentNews::send(), 'Within the hour: it waits');
        Cache::flush();
        $this->assertSame(1, CommentNews::send(), 'An hour later it goes');
    }

    public function test_the_commenter_is_told_of_a_reply_once_and_never_of_their_own(): void
    {
        $reader = User::factory()->create(['name' => 'Anirban', 'email_verified_at' => now()]);
        $c = Comment::create(['post_id' => $this->post->id, 'user_id' => $reader->id, 'author_name' => 'Anirban', 'body' => 'শেষ লাইনটা।', 'status' => 'approved']);
        $mine = Comment::create(['post_id' => $this->post->id, 'parent_id' => $c->id, 'user_id' => $reader->id, 'author_name' => 'Anirban', 'body' => 'আর একটা কথা।', 'status' => 'approved']);
        $r = Comment::create(['post_id' => $this->post->id, 'parent_id' => $c->id, 'user_id' => $this->writer->id, 'author_name' => 'Megha', 'body' => 'ধন্যবাদ!', 'status' => 'approved']);
        $pending = Comment::create(['post_id' => $this->post->id, 'parent_id' => $c->id, 'author_name' => 'Guest', 'body' => 'হুম', 'status' => 'pending']);
        $this->assertSame(1, CommentNews::replies());
        Notification::assertSentToTimes($reader, TemplateMail::class, 1);
        Notification::assertSentTo($reader, TemplateMail::class, fn ($n) => $n->key === 'comment_reply' && $n->url === $this->post->url().'#comment-'.$r->id
            && str_contains($n->values['{উত্তরদাতা}'], '(লেখক)'));
        $this->assertSame(0, CommentNews::replies(), 'Once');
        $this->assertNull($pending->fresh()->reply_told_at, 'An unpublished reply waits');
        $this->assertNotNull($mine->fresh()->reply_told_at);
    }

    /** D253: the account shows readers' words and replies, and a reply sent from there comes back there. */
    public function test_the_account_shows_comments_and_replies_and_takes_a_reply(): void
    {
        $reader = User::factory()->create(['name' => 'Anirban', 'email_verified_at' => now()]);
        $c = Comment::create(['post_id' => $this->post->id, 'user_id' => $reader->id, 'author_name' => 'Anirban', 'body' => 'শেষ লাইনটা মনে থাকবে।', 'status' => 'approved']);
        $this->actingAs($this->writer)->get('/account/')->assertDontSee('আপনি এখন হাতপাখার সদস্য', false);   // a writer with pieces (from the old site) is not welcomed as new
        $this->actingAs($this->writer)->get('/account/reading/')->assertSee('নতুন মতামত ও উত্তর');
        $this->get('/account/comments/')->assertOk()->assertSee('আপনার লেখায় পাঠকের মতামত')->assertSee('শেষ লাইনটা মনে থাকবে।')->assertSee('নতুন')
            ->assertSee('name="from" value="account"', false);
        $this->get('/account/reading/')->assertDontSee('নতুন মতামত ও উত্তর')->assertSee('মতামত ও উত্তর দেখুন');   // seen now
        $started = \Illuminate\Support\Facades\Crypt::encryptString((string) now()->subMinute()->timestamp);
        $this->post('/comment/'.$this->post->id, ['body' => 'ধন্যবাদ!', 'parent_id' => $c->id, 'from' => 'account', 'started' => $started])
            ->assertRedirect(\App\Support\Url::to('/account/comments').'#comment-'.$c->id);
        // a reply that fails stays in its own box on the account page, never in the piece's comment box (UX review)
        $this->from(\App\Support\Url::to('/account/comments'))->post('/comment/'.$this->post->id, ['body' => 'x', 'parent_id' => $c->id, 'from' => 'account', 'started' => $started])
            ->assertRedirect(\App\Support\Url::to('/account/comments').'#comment-'.$c->id);
        $this->get('/account/comments/')->assertSee('এখনও উত্তর দেননি', false);
        $this->post('/comment/'.$this->post->id, ['body' => 'দ্রুত উত্তর', 'parent_id' => $c->id, 'from' => 'account', 'started' => \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp)])
            ->assertRedirect(\App\Support\Url::to('/account/comments').'#comment-'.$c->id);
        $this->assertTrue(Comment::where('body', 'দ্রুত উত্তর')->exists(), 'A signed-in reader is not timed: a quick reply is not a robot');
        Comment::where('parent_id', $c->id)->update(['status' => 'approved']);
        $this->actingAs($reader)->get('/account/reading/')->assertSee('নতুন মতামত ও উত্তর');
        $this->get('/account/comments/')->assertSee('আপনার মতামতের উত্তর')->assertSee('ধন্যবাদ!');
    }

    /** D253: on release, the last week's published comments are told to their writers once more. */
    public function test_the_last_weeks_comments_are_queued_again_once(): void
    {
        $old = Comment::create(['post_id' => $this->post->id, 'author_name' => 'A', 'body' => 'আগের', 'status' => 'approved', 'writer_told_at' => now(), 'created_at' => now()->subDays(10)]);
        $week = Comment::create(['post_id' => $this->post->id, 'author_name' => 'B', 'body' => 'এ সপ্তাহের', 'status' => 'approved', 'writer_told_at' => now(), 'created_at' => now()->subDays(3)]);
        $migration = require database_path('migrations/2026_11_22_000200_comments_page_and_catch_up.php');
        \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('comments_seen_at'));
        $migration->up();
        $this->assertNull($week->fresh()->writer_told_at);
        $this->assertNotNull($old->fresh()->writer_told_at);
        $this->assertSame(1, CommentNews::send());
        Notification::assertSentTo($this->writer, TemplateMail::class, fn ($n) => $n->key === 'new_comments' && str_contains($n->values['{মন্তব্য}'], 'এ সপ্তাহের'));
    }

    public function test_guests_react_without_an_account_once_a_phone(): void
    {
        $this->withCredentials()->withCookie('hp_dev', str_repeat('a', 32))->postJson('/feel/'.$this->post->id, ['feeling' => 'touched'])->assertOk()->assertJson(['feeling' => 'touched', 'counts' => ['touched' => 1]]);
        $this->withCredentials()->withCookie('hp_dev', str_repeat('a', 32))->postJson('/feel/'.$this->post->id, ['feeling' => 'think'])->assertJson(['feeling' => 'think', 'counts' => ['touched' => 0, 'think' => 1]]);
        $this->withCredentials()->withCookie('hp_dev', str_repeat('a', 32))->postJson('/feel/'.$this->post->id, ['feeling' => 'think'])->assertJson(['feeling' => null]);
        $this->withCredentials()->withCookie('hp_dev', str_repeat('b', 32))->postJson('/feel/'.$this->post->id, ['feeling' => 'smile'])->assertJson(['counts' => ['smile' => 1]]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        Feelings::set($member, $this->post, 'smile');
        $this->assertSame(2, Feelings::counts($this->post->fresh())['smile'], 'Guests and members added together');
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('post_likes')->where('post_id', $this->post->id)->count(), 'Members kept apart (the monthly choice counts these)');
        for ($i = 0; $i < Feelings::GUEST_PER_NET; $i++) {
            \Illuminate\Support\Facades\DB::table('guest_feelings')->insert(['post_id' => $this->post->id, 'device' => hash('sha256', 'x'.$i), 'net' => hash('sha256', 'hp-net|127.0.0.1|2026-11-22'), 'feeling' => 'stay']);
        }
        $this->withCredentials()->withCookie('hp_dev', str_repeat('c', 32))->postJson('/feel/'.$this->post->id, ['feeling' => 'stay'])->assertStatus(429);
        $this->get('/brishti/')->assertSee('data-guest="'.$this->post->id.'"', false)->assertDontSee('বোতাম টিপতে একবার লগ ইন লাগে');
    }

    public function test_a_piece_going_live_tells_its_writer_once_with_whatsapp_and_the_picture(): void
    {
        $a = Author::where('slug', 'megha')->first();
        $new = $this->makePost(['slug' => 'notun', 'title' => 'নতুন কবিতা', 'status' => 'published', 'published_at' => now()->subMinutes(5)], $a);
        $later = $this->makePost(['slug' => 'pore', 'title' => 'পরে', 'status' => 'scheduled', 'published_at' => now()->addDay()], $a);
        $this->assertSame(1, PieceLive::send());
        Notification::assertSentTo($this->writer, TemplateMail::class, fn ($n) => $n->key === 'piece_live' && $n->url === $new->url()
            && str_contains($n->values['{হোয়াটসঅ্যাপ}'], 'wa.me') && str_ends_with($n->values['{ছবি}'], '/chhobi/'.$new->id.'/'));
        $this->assertSame(0, PieceLive::send(), 'Once');
        $this->assertNull($later->fresh()->writer_live_told_at, 'Not before it is out');
        $this->get('/chhobi/'.$new->id.'/')->assertOk()->assertSee('data-share-card', false)->assertSee('নতুন কবিতা')->assertSee('noindex', false);
        $this->get('/chhobi/'.$later->id.'/')->assertNotFound();
    }
}
