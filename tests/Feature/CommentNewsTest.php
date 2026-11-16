<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\TemplateMail;
use App\Support\CommentNews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** D217 (owner: "yes, add daily comment email for writers"). */
class CommentNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_writer_gets_one_email_a_day_listing_new_comments_and_none_when_there_are_none(): void
    {
        Notification::fake();
        config(['mail.off_live' => 'allow', 'mail.default' => 'smtp']);
        $writer = User::factory()->create(['name' => 'রিনা দাস']);
        $author = Author::create(['slug' => 'rina', 'display_name' => 'রিনা দাস', 'user_id' => $writer->id]);
        $post = $this->makePost(['slug' => 'brishti', 'title' => 'বৃষ্টির দিন'], $author);
        $other = $this->makePost(['slug' => 'megh', 'title' => 'মেঘ'], $author);
        $desk = $this->makePost(['slug' => 'desk-1', 'title' => 'ডেস্কের লেখা'], Author::create(['slug' => 'hatpakha-desk', 'display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]));
        $say = fn ($p, string $who, string $body, string $status = 'approved', ?int $by = null) => Comment::create(['post_id' => $p->id, 'author_name' => $who, 'body' => $body, 'status' => $status, 'user_id' => $by]);
        $say($post, 'অনির্বাণ', 'শেষ লাইনটা অনেকক্ষণ মনে থাকবে। {নাম} [বোতাম]');
        $say($other, 'শর্মিষ্ঠা', 'খুব ভালো লাগল।');
        $say($post, 'বট', 'cheap loans', 'pending');                       // not published: not told
        $say($post, 'রিনা দাস', 'ধন্যবাদ সবাইকে', 'approved', $writer->id);    // her own reply: not told
        $say($desk, 'পাঠক', 'ভালো');                                       // the desk's piece: nobody to tell

        $this->assertSame(1, CommentNews::send());
        Notification::assertSentToTimes($writer, TemplateMail::class, 1);
        Notification::assertSentTo($writer, TemplateMail::class, function (TemplateMail $n) use ($writer) {
            $html = (string) $n->toMail($writer)->render();

            return $n->key === 'new_comments' && $n->values['{কতটি}'] === '২' && str_contains($html, 'শেষ লাইনটা অনেকক্ষণ মনে থাকবে।') && str_contains($html, 'শর্মিষ্ঠা')
                && ! str_contains($html, 'cheap loans') && ! str_contains($html, 'ধন্যবাদ সবাইকে') && str_contains($n->url, '/account/writings')
                && ! str_contains($n->values['{মন্তব্য}'], '{নাম}') && ! str_contains($n->values['{মন্তব্য}'], '[বোতাম]');
        });
        $this->assertSame(1, Comment::whereNull('writer_told_at')->count(), 'Only the waiting one is left');

        // Next day, nothing new: no e-mail.
        $this->assertSame(0, CommentNews::send());
        Notification::assertSentToTimes($writer, TemplateMail::class, 1);

        // Letters switched off: the comment is not held over, and no e-mail goes.
        $writer->forceFill(['mail_opt_out' => true])->save();
        $say($post, 'নতুন পাঠক', 'আবার পড়লাম');
        $this->assertSame(0, CommentNews::send());
        $this->assertSame(1, Comment::whereNull('writer_told_at')->count());

        // No real mail (staging, a laptop): nothing is sent and nothing is marked.
        config(['mail.default' => 'log']);
        $say($post, 'আর একজন', 'সুন্দর');
        $this->assertSame(0, CommentNews::send());
        $this->assertSame(2, Comment::whereNull('writer_told_at')->count());
    }
}
