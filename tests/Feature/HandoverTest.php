<?php

namespace Tests\Feature;

use App\Ai\ReviewSchema;
use App\Enums\Role;
use App\Models\AiReview;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Newsletter;
use App\Models\Post;
use App\Models\User;
use App\Support\Handover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * D208 (owner, shown the list of an admin's jobs: "Do all these… do all this safe handover change"): six jobs are done by
 * the AI team, each behind a switch. A piece goes by itself only when nothing about it is in doubt.
 */
class HandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00', 'Asia/Kolkata'));
        $this->owner = User::factory()->create(['role' => Role::Admin]);
    }

    private function piece(array $report = [], array $attrs = []): Post
    {
        $writer = User::factory()->create(['role' => Role::Writer]);
        $post = $this->makePost(array_merge(['slug' => 'p'.uniqid(), 'title' => 'বৃষ্টির দিন', 'status' => 'pending', 'published_at' => null,
            'body_html' => '<p>আমি ভয় করিনা<br>মেঘের ডাকে</p><p>তোমার কথা মনে পড়ে<br>সারা দুপুর জুড়ে</p>'], $attrs));
        $post->forceFill(['submitted_by' => $writer->id, 'submitted_at' => now(), 'plagiarism' => ['verdict' => 'clear', 'matches' => [], 'note' => '']])->saveQuietly();
        AiReview::create(['post_id' => $post->id, 'provider' => 'anthropic', 'model' => 'm', 'status' => 'done', 'score' => 9,
            'report' => ReviewSchema::normalise(array_merge(['quality_score' => 9, 'summary' => 'ভালো', 'genre' => 'কবিতা', 'strengths' => [], 'suggestions' => [],
                'edits' => [['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling']], 'decision' => 'publish', 'note_to_writer' => 'অভিনন্দন! লেখাটি ভালো লেগেছে।',
                'excerpt' => 'বৃষ্টির দিনের কথা', 'suggested_section' => 'poetry', 'tags' => ['বৃষ্টি'], 'seo_title' => 'বৃষ্টির দিন', 'seo_description' => 'বর্ষার কবিতা',
                'policy_flags' => [], 'fits_magazine' => true, 'fit_reason' => 'ভালো', 'stray_text' => [], 'doubts' => []], $report))]);

        return $post->fresh();
    }

    public function test_small_fixes_go_in_by_themselves_but_never_a_change_of_words(): void
    {
        $post = $this->piece(['edits' => [['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling'], ['original' => 'মেঘের ডাকে', 'replacement' => 'ঝড়ের ডাকে', 'kind' => 'word']]]);
        $this->assertSame(1, Handover::fixes());
        $this->assertStringContainsString('করি না', $post->fresh()->body_html);
        $this->assertStringContainsString('মেঘের ডাকে', $post->fresh()->body_html, 'The writer\'s word stays');
        $this->assertSame(0, Handover::fixes(), 'Once');
        $this->assertNotNull(Handover::hold($post->fresh()), 'A word change waiting: not by itself');

        Handover::set('fixes', false);
        $other = $this->piece();
        $this->assertSame(0, Handover::run()['fixes']);
        $this->assertStringContainsString('করিনা', $other->fresh()->body_html);
    }

    public function test_a_drawn_cover_goes_on_only_when_the_writer_sent_none(): void
    {
        $bare = $this->piece();
        $bare->forceFill(['proposed_image_path' => 'media/covers/a.webp', 'proposed_image_width' => 1200, 'proposed_image_height' => 800])->saveQuietly();
        $own = $this->piece();
        $own->forceFill(['featured_image_path' => 'media/covers/own.webp', 'featured_image_width' => 1200, 'featured_image_height' => 800,
            'proposed_image_path' => 'media/covers/b.webp', 'proposed_image_width' => 1200, 'proposed_image_height' => 800])->saveQuietly();
        $this->assertSame(1, Handover::covers());
        $this->assertSame(['media/covers/a.webp', true], [$bare->fresh()->featured_image_path, (bool) $bare->fresh()->image_is_ai]);
        $this->assertSame('media/covers/own.webp', $own->fresh()->featured_image_path, 'The writer\'s own picture is never replaced');
    }

    public function test_a_clearly_fine_comment_appears_and_a_doubtful_one_waits(): void
    {
        $post = $this->makePost(['slug' => 'c1']);
        $fine = Comment::create(['post_id' => $post->id, 'author_name' => 'ক', 'body' => 'খুব ভালো লাগল', 'status' => 'pending']);
        $fine->forceFill(['ai_verdict' => 'ok'])->saveQuietly();
        $doubt = Comment::create(['post_id' => $post->id, 'author_name' => 'খ', 'body' => '…', 'status' => 'pending']);
        $doubt->forceFill(['ai_verdict' => 'check'])->saveQuietly();
        $unread = Comment::create(['post_id' => $post->id, 'author_name' => 'গ', 'body' => 'বাহ', 'status' => 'pending']);
        $this->assertTrue(Handover::comment($fine->fresh()));
        $this->assertSame(0, Handover::comments());
        $this->assertSame(['approved', 'pending', 'pending'], [$fine->fresh()->status, $doubt->fresh()->status, $unread->fresh()->status]);
    }

    public function test_a_strong_clean_piece_waits_a_day_then_is_selected_and_the_owner_can_stop_it(): void
    {
        $post = $this->piece();
        $this->assertNull(Handover::hold($post));
        $this->assertSame(0, Handover::publish(), 'First the clock is set');
        $this->assertSame(now()->addHours(24)->toDateTimeString(), $post->fresh()->auto_publish_at->toDateTimeString());
        Carbon::setTestNow(now()->addHours(23));
        $this->assertSame(0, Handover::publish());
        $this->assertSame('pending', $post->fresh()->status);

        $stopped = $this->piece();
        Handover::publish();
        Handover::stop($stopped->fresh());

        Carbon::setTestNow(now()->addHours(26));
        $this->assertSame(1, Handover::publish());
        $post->refresh();
        $this->assertSame('scheduled', $post->status);
        $this->assertTrue($post->published_at->isFuture(), 'The next free date, not at once');
        $this->assertStringContainsString('করি না', $post->body_html, 'Small fixes go in with it');
        $this->assertSame($this->owner->id, $post->reviewed_by);
        Notification::assertSentTo($post->submittedBy, \App\Notifications\SubmissionDecision::class);
        $this->assertSame(['pending', null, true], [$stopped->fresh()->status, $stopped->fresh()->auto_publish_at, $stopped->fresh()->auto_stopped]);
        $this->assertTrue(AuditLog::where('action', 'handover.published')->exists());
    }

    public function test_anything_in_doubt_is_held_for_the_owner_with_the_reason(): void
    {
        // Owner: "one user writes author details below the poem, that is not correct. Need to flag."
        $bio = $this->piece(['stray_text' => [['text' => 'কবি পরিচিতি: রিনা দাস, কল্যাণী। ফোন ৯৮৩০০০০০০০', 'why' => 'লেখকের পরিচয় ও ফোন নম্বর']]]);
        $this->assertStringContainsString('লেখার অংশ নয়', (string) Handover::hold($bio));
        $this->assertStringContainsString('কবি পরিচিতি', (string) Handover::hold($bio));
        $html = view('filament.ai-report', ['review' => $bio->latestAiReview])->render();
        $this->assertStringContainsString('প্রকাশের আগে আপনি দেখে নিন', $html);
        $this->assertStringContainsString('লেখকের পরিচয় ও ফোন নম্বর', $html);
        $this->assertStringContainsString('ফোন ৯৮৩০০০০০০০', $bio->fresh()->body_html.' কবি পরিচিতি: রিনা দাস, কল্যাণী। ফোন ৯৮৩০০০০০০০', 'Nothing is removed by the AI');

        foreach ([
            ['doubts' => ['লেখাটি মাঝপথে শেষ হয়ে গেছে বলে মনে হচ্ছে']],
            ['decision' => 'changes'],
            ['quality_score' => 7],
            ['policy_flags' => ['defamation']],
            ['fits_magazine' => false],
        ] as $report) {
            $this->assertNotNull(Handover::hold($this->piece($report)), json_encode($report));
        }
        $old = $this->piece();
        $old->latestAiReview->forceFill(['report' => array_diff_key((array) $old->latestAiReview->report, ['stray_text' => 1, 'doubts' => 1])])->save();
        $this->assertStringContainsString('পুরনো', (string) Handover::hold($old->fresh()), 'A report from before this check is never trusted as clean');
        $copied = $this->piece();
        $copied->forceFill(['plagiarism' => ['verdict' => 'possible', 'matches' => [], 'note' => '']])->saveQuietly();
        $this->assertNotNull(Handover::hold($copied->fresh()));
        $unchecked = $this->piece();
        $unchecked->forceFill(['plagiarism' => null])->saveQuietly();
        $this->assertNotNull(Handover::hold($unchecked->fresh()), 'Not checked is not clean');

        Carbon::setTestNow(now()->addDays(3));
        $this->assertSame(0, Handover::publish());
        $this->assertSame(0, Post::where('status', '!=', 'pending')->where('submitted_at', '!=', null)->count());

        // A piece that was going, then changes: the clock is taken away.
        $going = $this->piece();
        Handover::publish();
        $this->assertNotNull($going->fresh()->auto_publish_at);
        $going->forceFill(['plagiarism' => ['verdict' => 'found', 'matches' => [['url' => 'https://x.example/a']], 'note' => '']])->saveQuietly();
        Carbon::setTestNow(now()->addDays(2));
        $this->assertSame(0, Handover::publish());
        $this->assertSame(['pending', null], [$going->fresh()->status, $going->fresh()->auto_publish_at]);
    }

    public function test_the_readers_letter_sends_itself_after_twelve_hours_unless_stopped_and_only_with_real_mail(): void
    {
        $make = fn () => tap(Newsletter::create(['subject' => 'চিঠি', 'intro' => 'ক', 'closing' => 'খ', 'audience' => 'readers', 'source' => 'ai_weekly', 'status' => 'draft', 'items' => []]),
            fn ($n) => Handover::letterDrafted($n));
        $a = $make();
        $this->assertSame(now()->addHours(12)->toDateTimeString(), $a->fresh()->auto_send_at->toDateTimeString());
        Carbon::setTestNow(now()->addHours(13));
        $this->assertSame(0, Handover::letter(), 'No real mail here: nothing is sent');
        $this->assertSame('draft', $a->fresh()->status);

        // The owner changes a word: the twelve hours start again.
        $this->actingAs($this->owner);
        $a->fresh()->update(['subject' => 'নতুন শিরোনাম']);
        $this->assertTrue($a->fresh()->auto_send_at->isFuture());

        // Stopped from the desk page.
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\HandoverPage::class)->assertSee('নিজে থেকে চলা কাজ')->assertSee('চিঠি নিজে থেকে যাবে')->call('stopLetter', $a->id);
        $this->assertNull($a->fresh()->auto_send_at);

        Handover::set('letter', false);
        $this->assertNull($make()->fresh()->auto_send_at, 'Switched off: a new draft just waits');
    }

    public function test_the_desk_page_shows_what_is_going_what_is_held_and_has_a_switch_for_each_job(): void
    {
        $going = $this->piece([], ['title' => 'যাবে']);
        $held = $this->piece(['doubts' => ['শেষ স্তবকটি দু-বার আছে']], ['title' => 'থাকবে']);
        Handover::publish();
        $this->actingAs($this->owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $page = \Livewire\Livewire::test(\App\Filament\Pages\HandoverPage::class)->assertSee('যাবে')->assertSee('থাকবে')->assertSee('শেষ স্তবকটি দু-বার আছে')->assertSee('ভালো লেখা প্রকাশ');
        $page->call('stopPiece', $going->id);
        $this->assertTrue($going->fresh()->auto_stopped);
        $page->call('toggle', 'publish');
        $this->assertFalse(Handover::on('publish'));
        $this->assertSame(0, Handover::run()['publish']);

        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Pages\HandoverPage::canAccess(), 'Only the owner');
    }

    public function test_small_monthly_jobs_go_ahead_but_a_writers_own_recording_waits(): void
    {
        $machine = $this->piece();
        $machine->forceFill(['audio_path' => '/media/audio/a.mp3', 'audio_status' => 'pending', 'audio_ai' => true])->saveQuietly();
        $own = $this->piece();
        $own->forceFill(['audio_path' => '/media/audio/b.mp3', 'audio_status' => 'pending', 'audio_ai' => false])->saveQuietly();
        $c = \App\Models\Collection::create(['slug' => 'borsha', 'title' => 'বর্ষা', 'kind' => 'theme', 'status' => 'approved', 'post_ids' => [1]]);
        $fresh = \App\Models\EditorTask::propose('collection_add', 'যোগ', ['add' => [2, 3]], $c);
        $stale = \App\Models\EditorTask::propose('collection', 'পুরনো প্রস্তাব', [], \App\Models\Collection::create(['slug' => 'sheet', 'title' => 'শীত', 'kind' => 'theme', 'status' => 'proposed', 'post_ids' => [1]]));
        $stale->forceFill(['created_at' => now()->subDays(90)])->saveQuietly();

        $this->assertSame(2, Handover::monthly());
        $this->assertSame(['approved', 'pending'], [$machine->fresh()->audio_status, $own->fresh()->audio_status]);
        $this->assertSame([1, 2, 3], $c->fresh()->post_ids);
        $this->assertSame(['done', 'open'], [$fresh->fresh()->status, $stale->fresh()->status], 'An old forgotten proposal is not acted on');
    }

    /** Owner: "Make sure the team will do a proper job always. Any confusion, raise a flag." */
    public function test_trouble_is_flagged_for_the_owner_and_the_flag_goes_when_the_trouble_does(): void
    {
        $alerts = fn () => \App\Models\EditorTask::where('type', 'alert')->where('status', 'open')->pluck('title', 'key')->all();
        // A piece no AI has read after six hours.
        $unread = $this->piece([], ['title' => 'কেউ পড়েনি']);
        $unread->latestAiReview->delete();
        // A piece kept for the owner that has waited three days.
        $kept = $this->piece(['doubts' => ['শেষটা কাটা']], ['title' => 'অপেক্ষায়']);
        Handover::run();
        $this->assertSame([], $alerts(), 'Too early to worry');

        Carbon::setTestNow(now()->addDays(4));
        Handover::run();
        $a = $alerts();
        $this->assertArrayHasKey('handover.unread', $a);
        $this->assertArrayHasKey('handover.waiting', $a);
        $waiting = \App\Models\EditorTask::where('key', 'handover.waiting')->first();
        $this->assertStringContainsString('অপেক্ষায়: ', $waiting->payload['errors'][0]);
        $this->assertStringContainsString('শেষটা কাটা', $waiting->payload['errors'][0]);
        $this->assertNotNull(\App\Models\Setting::get('handover.ran_at'));

        // The owner deals with both: the flags go by themselves.
        \App\Support\Review::reject($kept->fresh(), $this->owner, 'না');
        \App\Support\Review::reject($unread->fresh(), $this->owner, 'না');
        Handover::run();
        $this->assertSame([], $alerts());

        // A job that breaks says so, and the other jobs still run.
        $c = Comment::create(['post_id' => $this->makePost(['slug' => 'c9'])->id, 'author_name' => 'ক', 'body' => 'ভালো', 'status' => 'pending']);
        $c->forceFill(['ai_verdict' => 'ok'])->saveQuietly();
        Comment::saving(fn () => throw new \RuntimeException('disk full'));
        $done = Handover::run();
        $this->assertArrayHasKey('handover.job.comments', $alerts());
        $this->assertSame(['disk full'], \App\Models\EditorTask::where('key', 'handover.job.comments')->first()->payload['errors']);
        $this->assertArrayHasKey('publish', $done);
        Comment::flushEventListeners();
        Handover::run();
        $this->assertSame([], $alerts());
    }

    public function test_never_more_than_three_pieces_are_selected_by_themselves_in_a_day(): void
    {
        foreach (range(1, 5) as $i) {
            $this->piece([], ['title' => 'লেখা '.$i]);
        }
        Handover::publish();
        Carbon::setTestNow(now()->addHours(25));
        $this->assertSame(3, Handover::publish());
        $this->assertSame(0, Handover::publish(), 'The rest wait for tomorrow');
        Carbon::setTestNow(now()->addDay());
        $this->assertSame(2, Handover::publish());
    }

    public function test_a_piece_sent_again_starts_from_nothing(): void
    {
        $post = $this->piece();
        Handover::publish();
        Handover::stop($post->fresh());
        Handover::reset($post->fresh());
        $this->assertSame([null, false], [$post->fresh()->auto_publish_at, $post->fresh()->auto_stopped]);
    }
}
