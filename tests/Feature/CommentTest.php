<?php

namespace Tests\Feature;

use App\Http\Controllers\CommentController;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/** D39: guests and signed-in readers comment; guests always checked; accounts trusted after 2 approved. */
class CommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\RateLimiter::clear('comment');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);   // limits tested separately
    }

    private function form(array $extra = []): array
    {
        return $extra + ['body' => 'খুব ভালো লাগল।', 'started' => Crypt::encryptString((string) now()->subSeconds(10)->timestamp)];
    }

    public function test_post_page_shows_the_form_and_only_approved_comments(): void
    {
        $post = $this->makePost(['slug' => 'nodi']);
        Comment::create(['post_id' => $post->id, 'author_name' => 'ক', 'body' => 'দেখা যাবে', 'status' => 'approved']);
        Comment::create(['post_id' => $post->id, 'author_name' => 'খ', 'body' => 'লুকানো থাকবে', 'status' => 'pending']);
        $this->get('/nodi/')->assertOk()->assertSee('লেখাটি পড়ে কেমন লাগল')->assertSee('দেখা যাবে')->assertDontSee('লুকানো থাকবে')
            ->assertSee('আপনার নাম')->assertSee('/login/?next=', false);
    }

    public function test_guest_comment_waits_for_an_editor_and_is_plain_text(): void
    {
        $post = $this->makePost(['slug' => 'nodi']);
        $this->post("/comment/{$post->id}", $this->form(['name' => 'অতিথি পাঠক', 'body' => '<script>alert(1)</script> ভালো']))
            ->assertRedirect($post->url().'#comments')->assertSessionHas('comment_status');
        $c = Comment::sole();
        $this->assertSame('pending', $c->status);
        $this->assertNull($c->user_id);
        Comment::query()->update(['status' => 'approved']);
        $this->get('/nodi/')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_guest_needs_a_name_and_bots_are_refused(): void
    {
        $post = $this->makePost(['slug' => 'nodi']);
        $this->post("/comment/{$post->id}", $this->form())->assertSessionHasErrors('name');
        $this->post("/comment/{$post->id}", $this->form(['name' => 'বট', 'website' => 'http://spam']))->assertSessionHasErrors('body');
        $this->post("/comment/{$post->id}", ['name' => 'বট', 'body' => 'x y', 'started' => Crypt::encryptString((string) now()->timestamp)])->assertSessionHasErrors('body');
        $this->assertSame(0, Comment::count());
    }

    public function test_signed_in_reader_is_trusted_after_two_approved_comments(): void
    {
        $post = $this->makePost(['slug' => 'nodi']);
        $reader = User::factory()->create(['name' => 'নিয়মিত পাঠক']);
        $this->actingAs($reader)->post("/comment/{$post->id}", $this->form());
        $this->assertSame('pending', Comment::latest('id')->first()->status);
        $this->assertSame('নিয়মিত পাঠক', Comment::latest('id')->first()->author_name);

        Comment::query()->update(['status' => 'approved']);
        Comment::create(['post_id' => $post->id, 'user_id' => $reader->id, 'author_name' => 'নিয়মিত পাঠক', 'body' => 'আগের', 'status' => 'approved']);
        $this->actingAs($reader)->post("/comment/{$post->id}", $this->form(['body' => 'এবার সরাসরি']));
        $this->assertSame('approved', Comment::latest('id')->first()->status);

        $reader->forceFill(['spam_reason' => 'test'])->save();
        $this->actingAs($reader)->post("/comment/{$post->id}", $this->form(['body' => 'স্প্যাম চিহ্নিত']));
        $this->assertSame('pending', Comment::latest('id')->first()->status, 'Flagged accounts are always checked');
    }

    public function test_no_comments_on_unpublished_posts_and_opt_out_is_kept(): void
    {
        $draft = $this->makePost(['slug' => 'khosra', 'status' => 'pending', 'published_at' => null]);
        $this->post("/comment/{$draft->id}", $this->form(['name' => 'ক']))->assertNotFound();

        $post = $this->makePost(['slug' => 'nodi']);
        $this->post("/comment/{$post->id}", $this->form(['name' => 'কমল', 'not_featured' => '1']));
        $this->assertTrue(Comment::sole()->feature_opt_out);
    }

    public function test_log_in_to_comment_comes_back_to_the_post_and_refuses_other_sites(): void
    {
        $this->get('/login/?next='.rawurlencode('/nodi/#comments'))->assertOk();
        $this->assertStringEndsWith('/nodi/#comments', session('url.intended'));
        session()->forget('url.intended');
        $this->get('/login/?next='.rawurlencode('//evil.example/'))->assertOk();
        $this->assertNull(session('url.intended'));
    }

    public function test_comments_are_rate_limited(): void
    {
        $this->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $post = $this->makePost(['slug' => 'nodi']);
        $codes = collect(range(1, 4))->map(fn ($i) => $this->post("/comment/{$post->id}", $this->form(['name' => 'কমল', 'body' => "মন্তব্য {$i}"]))->status());
        $this->assertSame(429, $codes->last(), 'Guests: a few a minute at most');
    }

    /** D74: signed-in readers see their letter status, not a sign-up form; the writer's comments are marked. */
    public function test_signed_in_reader_sees_letter_status_and_writer_badge(): void
    {
        $writer = \App\Models\User::factory()->create(['email_verified_at' => now()]);
        $author = \App\Models\Author::create(['user_id' => $writer->id, 'display_name' => 'লেখিকা', 'slug' => 'lekhika']);
        $post = $this->makePost(['slug' => 'nodi', 'status' => 'published', 'published_at' => now()->subDay()], $author);
        Comment::create(['post_id' => $post->id, 'user_id' => $writer->id, 'author_name' => 'লেখিকা', 'body' => 'ধন্যবাদ সবাইকে', 'status' => 'approved']);
        $this->get('/nodi/')->assertSee('চিঠি চাই')->assertSee('লেখক</span>', false);
        $this->actingAs(\App\Models\User::factory()->create(['email_verified_at' => now()]))->get('/nodi/')
            ->assertSee('হাতপাখার চিঠি আপনি পাচ্ছেন')->assertDontSee('চিঠি চাই');
    }

    /** D75: no links in comments, however they are written; ordinary text still goes through. */
    public function test_comments_cannot_carry_links(): void
    {
        foreach (['https://example.in/x', 'www.example', 'northbengaltourism dot com', 'example[.]com', 'example . com', 'ডট কম', 'লিখুন a@b.com-এ', 'sheroshayri.in'] as $spam) {
            $this->assertTrue(Comment::hasLink($spam), $spam);
        }
        foreach (['খুব ভালো লাগল। ৩.৫ বছর আগে পড়েছিলাম', 'e.g. Mr.Ghosh বলেছিলেন', 'in the end. Com on!', 'U.S.A তে থাকি'] as $fine) {
            $this->assertFalse(Comment::hasLink($fine), $fine);
        }
        $post = $this->makePost(['slug' => 'nodi', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->actingAs(\App\Models\User::factory()->create(['email_verified_at' => now()]))
            ->post('/comment/'.$post->id.'/', $this->form(['body' => 'দারুণ! আমার সাইট দেখুন northbengaltourism dot com']))
            ->assertSessionHasErrors('body');
        $this->assertSame(0, Comment::count());
    }
}
