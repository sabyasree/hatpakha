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

    /** D210 (owner: "what the AI is changing is not in the change history of the piece. Also need a revert option for a specific change"). */
    public function test_every_fix_the_team_puts_in_is_in_the_history_and_one_can_be_taken_back_for_good(): void
    {
        $post = $this->piece(['edits' => [['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling'], ['original' => 'দুপুর জুড়ে', 'replacement' => 'দুপুর জুড়ে।', 'kind' => 'punctuation']]]);
        $this->assertSame(1, Handover::fixes());
        $kinds = fn () => \App\Models\PostVersion::where('post_id', $post->id)->orderBy('id')->pluck('kind')->all();
        $this->assertSame(['before', 'ai_fix'], $kinds(), 'The text from before is kept the first time');
        $v = \App\Models\PostVersion::where('post_id', $post->id)->where('kind', 'ai_fix')->firstOrFail();
        $this->assertNull($v->user_id, 'Nobody clicked: the AI team did it');
        $this->assertSame(['করিনা', 'দুপুর জুড়ে'], array_column($v->changes, 'original'));
        $this->assertStringContainsString('করিনা', \App\Models\PostVersion::where('post_id', $post->id)->where('kind', 'before')->value('body_html'));

        $html = view('filament.post-history', ['post' => $post->fresh(), 'canRevert' => true])->render();
        $this->assertStringContainsString('এআই দল, নিজে থেকে', $html);
        $this->assertStringContainsString('করি না', $html);
        $this->assertStringContainsString('revertFix('.$v->id.', 0)', $html);

        // The owner takes one back from the piece's page.
        $this->actingAs($this->owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->id])->call('revertFix', $v->id, 0);
        $body = $post->fresh()->body_html;
        $this->assertStringContainsString('করিনা', $body);
        $this->assertStringNotContainsString('করি না', $body);
        $this->assertStringContainsString('দুপুর জুড়ে।', $body, 'The other fix stays');
        $this->assertSame(['before', 'ai_fix', 'reverted'], $kinds());
        $this->assertTrue($v->fresh()->changes[0]['reverted']);
        $this->assertSame($this->owner->id, \App\Models\PostVersion::where('post_id', $post->id)->where('kind', 'reverted')->value('user_id'));

        // Never put in again, and never offered again in the dialogs.
        auth()->logout();
        $this->assertSame(0, Handover::fixes());
        $this->assertStringContainsString('করিনা', $post->fresh()->body_html);
        $this->assertSame([], \App\Ai\EditorPackage::openOptions($post->fresh()));
        $this->assertFalse(\App\Ai\EditorPackage::revertFix($post->fresh(), $v->fresh(), 0), 'Once');

        // What else the team did to the piece is listed too.
        $post->forceFill(['proposed_image_path' => 'media/covers/a.webp', 'proposed_image_width' => 1200, 'proposed_image_height' => 800])->saveQuietly();
        Handover::covers();
        $this->assertStringContainsString('এআই-এর আঁকা ছবি প্রচ্ছদে বসানো হয়েছে', view('filament.post-history', ['post' => $post->fresh()])->render());
    }

    /** D214 (owner, offered the two: "do both automatic"): the detective series runs itself once the owner has started a case. */
    public function test_the_detective_series_plans_its_next_case_and_publishes_checked_episodes_by_itself(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        \App\Models\Author::firstOrCreate(['slug' => 'hatpakha-desk'], ['display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        \App\Models\Section::firstOrCreate(['slug' => 'dharabahik'], ['name_bn' => 'ধারাবাহিক', 'format' => 'prose']);
        $problems = [];
        $plan = ['title' => 'হারানো ফর্মা', 'logline' => 'ষোলো পাতা উধাও।', 'setting' => 'কলেজ স্ট্রিট',
            'characters' => [['name' => 'নীলিমা সেন', 'role' => 'লেখিকা'], ['name' => 'অবনী দত্ত', 'role' => 'প্রকাশকের ভাই'], ['name' => 'সুধাময় কর', 'role' => 'বাঁধাইকর']],
            'episodes' => array_map(fn ($i) => ['title' => 'পর্ব-'.$i, 'beat' => 'এই পর্বে একটা নতুন কথা জানা যায়, সন্দেহ সরে, আর শেষে একটা প্রশ্ন থাকে'], range(1, 9)),
            'solution' => ['culprit_name' => 'অবনী দত্ত', 'culprit' => 'ভাই', 'method' => 'রাতে', 'motive' => 'দেনা', 'proof' => 'বানান'],
            'clues' => array_map(fn ($e) => ['clue' => 'সূত্র '.$e, 'episode' => $e, 'written' => $e === 1, 'means' => 'অর্থ'], [1, 2, 4, 6, 8]),
            'characters' => [['name' => 'নীলিমা সেন', 'role' => 'লেখিকা'], ['name' => 'অবনী দত্ত', 'role' => 'প্রকাশকের ভাই'], ['name' => 'সুধাময় কর', 'role' => 'বাঁধাইকর'], ['name' => 'হেমন্ত পাল', 'role' => 'কম্পোজিটর']],
            'thread' => ['episode' => 4, 'step' => 'এক বুড়ো কম্পোজিটর জানায়, সেই রাতে ছাপাখানায় আলো জ্বলছিল।'],
            'cover_prompt' => 'a press'];
        $n = 0;
        $rewrites = [];
        \App\Ai\Claude::$fake = function ($purpose, $user, $schema) use (&$problems, &$plan, &$n, &$rewrites, &$whole) {
            if ($purpose === 'case_check') {
                return ['problems' => $problems];
            }

            if (isset($schema['properties']['edits'])) {   // D229: the smallest cure first
                $rewrites[] = $user;

                return count($rewrites) === 2 ? ['edits' => []]   // cannot be mended: the whole episode is written again
                    : ['edits' => [['paragraph' => 1, 'text' => str_repeat('সারানো হল, বার '.count($rewrites).'। ', 100)], ['paragraph' => 9, 'text' => 'নেই এমন অনুচ্ছেদ']]];
            }
            if (str_contains((string) $user, '<problems_found>')) {
                $whole = ($whole ?? 0) + 1;

                return ['episode' => ['title' => 'উধাও', 'paragraphs' => [str_repeat('আবার লেখা হল, বার '.count($rewrites).'। ', 100)], 'summary' => 'ফর্মা উধাও।', 'image_prompt' => '', 'teaser' => 'একটা বানান মিলছে না।']];
            }

            return in_array('cover_prompt', $schema['required'] ?? [], true) ? ['title' => $plan['title'].(++$n > 1 ? ' '.$n : '')] + $plan
                : ['episode' => ['title' => 'উধাও', 'paragraphs' => [str_repeat('বিকেলে প্রেসে ঢুকেই বুঝলাম কিছু একটা হয়েছে। ', 60)], 'summary' => 'ফর্মা উধাও।', 'image_prompt' => '', 'teaser' => 'একটা বানান মিলছে না।']];
        };
        try {
            // The series begins by itself: with no case at all, the first one is planned.
            $this->assertSame(1, Handover::cases());
            $case = \App\Models\Serial::where('writer', 'serial_detective')->firstOrFail();
            $this->assertSame('proposed', $case->status);
            $this->assertTrue(\App\Models\EditorTask::where('key', 'handover.case_waiting')->where('status', 'open')->exists(), 'The owner is told at once that a case waits to be read');
            $this->assertSame(0, Handover::cases(), 'Not a second one');
            $ep1 = $case->episodes()->first();

            // Not started by the owner: nothing of it goes out, however long it waits.
            Carbon::setTestNow(now()->addDays(3));
            $this->assertSame(0, Handover::cases());
            $this->assertSame('draft', $ep1->fresh()->status);
            $this->assertTrue(\App\Models\EditorTask::where('key', 'handover.case_waiting')->where('status', 'open')->exists(), 'The owner is reminded that a case waits to be read');

            // The owner reads the solution and presses one button: episode one gets its Saturday and the case is running.
            $at = Handover::startCase($case->fresh(), $this->owner);
            $this->assertSame([Carbon::SATURDAY, '09:00'], [$at->copy()->timezone('Asia/Kolkata')->dayOfWeek, $at->copy()->timezone('Asia/Kolkata')->format('H:i')]);
            $this->assertSame(['scheduled', 'active'], [$ep1->fresh()->status, $case->fresh()->status]);
            $this->assertNull(Handover::startCase($case->fresh(), $this->owner), 'Once');
            Handover::cases();
            $this->assertFalse(\App\Models\EditorTask::where('key', 'handover.case_waiting')->where('status', 'open')->exists());
            Carbon::setTestNow($at->copy()->addHour());
            \App\Ai\SerialWriter::keepAhead();
            $ep2 = $case->episodes()->where('episode_no', 2)->firstOrFail();
            $this->assertSame(0, Handover::cases(), 'A day in the desk first');
            $this->assertNotNull($ep2->fresh()->auto_publish_at);

            // The second reader finds a problem: it waits, with the reason in a flag.
            Carbon::setTestNow(now()->addHours(25));
            $problems = ['অপরাধীর নাম এই পর্বেই বলে দেওয়া হয়েছে'];
            // D226 (owner: "it should fix by itself"): the writer writes it again, twice at most, before the owner is asked.
            $first = $ep2->fresh()->body_html;
            $this->assertSame(0, Handover::cases());
            $this->assertCount(1, $rewrites);
            $this->assertStringContainsString('অপরাধীর নাম এই পর্বেই', $rewrites[0]);
            $this->assertStringContainsString('সারানো হল, বার 1', $ep2->fresh()->body_html);
            $this->assertStringNotContainsString('নেই এমন অনুচ্ছেদ', $ep2->fresh()->body_html);
            $this->assertSame($ep2->title, $ep2->fresh()->title, 'Same place, same title');
            $this->assertSame(['before', 'ai_rewrite'], \App\Models\PostVersion::where('post_id', $ep2->id)->orderBy('id')->pluck('kind')->all());
            $this->assertSame($first, \App\Models\PostVersion::where('post_id', $ep2->id)->orderBy('id')->first()->body_html, 'The first text is kept');
            $this->assertFalse(\App\Models\EditorTask::where('key', 'handover.episode.'.$ep2->id)->where('status', 'open')->exists(), 'Nobody is asked while it is being put right');
            $this->assertSame(0, Handover::cases());
            $this->assertSame([2, 1], [count($rewrites), $whole], 'What cannot be mended is written again whole');
            $this->assertSame(0, Handover::cases());
            $this->assertCount(3, $rewrites);
            $this->assertSame(0, Handover::cases());
            $this->assertCount(3, $rewrites, 'Three tries, then a person');
            $flag = \App\Models\EditorTask::where('key', 'handover.episode.'.$ep2->id)->where('status', 'open')->firstOrFail();
            $this->assertSame($problems, $flag->payload['errors']);
            $this->assertSame('draft', $ep2->fresh()->status);

            // The owner fixes the text: it is checked afresh and given its Saturday.
            $problems = [];
            $ep2->forceFill(['body_html' => '<p>'.str_repeat('পাকড়াশীমশাই চশমা কপালে তুললেন। ', 80).'</p>'])->save();
            $this->assertSame(1, Handover::cases());
            $ep2->refresh();
            $this->assertSame('scheduled', $ep2->status);
            $when = $ep2->published_at->copy()->timezone('Asia/Kolkata');
            $this->assertSame([Carbon::SATURDAY, '09:00'], [$when->dayOfWeek, $when->format('H:i')]);
            $this->assertTrue($when->gte($ep1->fresh()->published_at->copy()->addDays(5)), 'Never two episodes in one week');
            $this->assertStringStartsNotWith('desk-', $ep2->slug);
            $this->assertFalse(\App\Models\EditorTask::where('key', 'handover.episode.'.$ep2->id)->where('status', 'open')->exists());

            // The owner can stop one episode.
            Carbon::setTestNow($ep2->published_at->copy()->addHour());
            \App\Ai\SerialWriter::keepAhead();
            $ep3 = $case->episodes()->where('episode_no', 3)->firstOrFail();
            Handover::cases();
            Handover::stop($ep3->fresh());
            Carbon::setTestNow(now()->addDays(2));
            $this->assertSame(0, Handover::cases());
            $this->assertSame('draft', $ep3->fresh()->status);

            // One episode before the end the next case is planned, once; when the last is out the case is finished.
            foreach (range(3, 8) as $no) {
                $case->episodes()->where('episode_no', $no)->first()?->forceFill(['status' => 'published', 'published_at' => now(), 'auto_stopped' => true])->save()
                    ?? tap($this->makePost(['slug' => 'pp-'.$no, 'status' => 'published', 'published_at' => now()]))->forceFill(['serial_id' => $case->id, 'episode_no' => $no])->saveQuietly();
            }
            Handover::cases();
            $next = \App\Models\Serial::where('writer', 'serial_detective')->where('status', 'proposed')->get();
            $this->assertCount(1, $next);
            Handover::cases();
            $this->assertSame(1, \App\Models\Serial::where('status', 'proposed')->count(), 'Not a second plan');
            tap($this->makePost(['slug' => 'pp-9', 'status' => 'published', 'published_at' => now()]))->forceFill(['serial_id' => $case->id, 'episode_no' => 9])->saveQuietly();
            Handover::cases();
            $this->assertSame('finished', $case->fresh()->status);
            $this->assertSame('draft', $next->first()->episodes()->first()->status, 'The new case still waits for the owner');

            Handover::set('cases', false);
            $this->assertSame(0, Handover::run()['cases']);
        } finally {
            \App\Ai\Claude::$fake = null;
        }
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
