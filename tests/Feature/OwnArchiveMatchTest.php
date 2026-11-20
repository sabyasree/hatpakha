<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\EditorTask;
use App\Models\Post;
use App\Models\User;
use App\Support\TextPrints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D248 (owner: "a declined piece must never come out under someone else's name"). */
class OwnArchiveMatchTest extends TestCase
{
    use RefreshDatabase;

    private const POEM = '<p>নদীর ধারে বসে থাকি সারাটা বিকেল একা</p><p>জলের ভেতরে মেঘ নামে, ছায়া পড়ে গাছের</p><p>কেউ ডাকে না, কেউ আসে না এই ঘাটে আর</p><p>শুধু হাওয়া এসে পাতা ওল্টায় আমার খাতার</p><p>সন্ধ্যা নামলে মাঝি ফেরে নৌকা বেয়ে ঘরে</p><p>আমি তবু বসে থাকি, অপেক্ষা করি তোমার</p>';

    public function test_a_piece_declined_earlier_is_found_when_someone_else_sends_it(): void
    {
        $rina = Author::create(['slug' => 'rina', 'display_name' => 'রিনা']);
        $declined = $this->makePost(['slug' => 'nodir-dhare', 'title' => 'নদীর ধারে', 'status' => 'rejected', 'body_html' => self::POEM], $rina);
        $this->assertGreaterThan(10, (int) $declined->fresh()->print_count, 'Fingerprinted when saved');

        $copier = Author::create(['slug' => 'other', 'display_name' => 'অন্য কেউ']);
        $copy = $this->makePost(['slug' => 'amar-kobita', 'title' => 'আমার কবিতা', 'status' => 'pending',
            'body_html' => '<p>শুরুর একটা নতুন লাইন যোগ করলাম আমি এখানে</p>'.str_replace('আর', 'আজ', self::POEM)], $copier);
        $found = TextPrints::check($copy);
        $this->assertCount(1, $found);
        $this->assertSame([$declined->id, 'rejected', 'রিনা'], [$found[0]['post_id'], $found[0]['status'], $found[0]['writer']]);
        $this->assertGreaterThan(60, $found[0]['share']);
        $this->assertStringContainsString('মনোনীত হয়নি', TextPrints::summary($copy->fresh()));
        $this->assertTrue(EditorTask::where('type', 'plagiarism')->where('subject_id', $copy->id)->exists(), 'The editor is told');
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.own_match', 'subject_id' => $copy->id]);
    }

    public function test_the_same_writer_sending_again_and_a_short_common_line_are_not_matches(): void
    {
        $rina = Author::create(['slug' => 'rina', 'display_name' => 'রিনা']);
        $this->makePost(['slug' => 'first', 'status' => 'rejected', 'body_html' => self::POEM], $rina);
        $again = $this->makePost(['slug' => 'again', 'status' => 'pending', 'body_html' => self::POEM], $rina);
        $this->assertSame([], TextPrints::check($again), 'Her own piece, sent again');

        $other = Author::create(['slug' => 'other', 'display_name' => 'অন্য']);
        $quote = $this->makePost(['slug' => 'quote', 'status' => 'pending', 'body_html' => '<p>নদীর ধারে বসে থাকি সারাটা বিকেল একা।</p><p>'.str_repeat('এই কবিতার বাকিটা একেবারে আলাদা আর নিজের লেখা। ', 12).'</p>'], $other);
        $this->assertSame([], TextPrints::check($quote), 'One shared line is not copying');
    }

    public function test_older_pieces_are_fingerprinted_a_batch_at_a_time_and_a_new_submission_is_checked(): void
    {
        $a = Author::create(['slug' => 'a', 'display_name' => 'ক']);
        $old = $this->makePost(['slug' => 'old', 'status' => 'published', 'body_html' => self::POEM], $a);
        DB::table('text_prints')->delete();
        DB::table('posts')->update(['print_count' => null]);
        $this->assertSame(1, TextPrints::backfill());
        $this->assertSame(0, TextPrints::backfill());
        $this->assertGreaterThan(10, DB::table('text_prints')->where('post_id', $old->id)->count());

        $user = User::factory()->create();
        $b = Author::create(['slug' => 'b', 'display_name' => 'খ', 'user_id' => $user->id]);
        $new = $this->makePost(['slug' => 'new', 'status' => 'pending', 'submitted_by' => $user->id, 'body_html' => self::POEM], $b);
        (new \App\Jobs\ReviewSubmission($new->id))->handle(app(\App\Ai\ReviewService::class));
        $this->assertSame($old->id, $new->fresh()->own_match[0]['post_id'] ?? null, 'Checked on submission, also against published pieces');
    }
}
