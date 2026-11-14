<?php

namespace Tests\Feature;

use App\Ai\CopyEdit;
use App\Ai\EditorPackage;
use App\Ai\ReviewSchema;
use App\Enums\Role;
use App\Models\AiReview;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D42: the AI editor prepares, the owner approves; copy-edits can never rewrite a piece. */
class AiEditorTest extends TestCase
{
    use RefreshDatabase;

    private function packageFor(Post $post, array $edits, string $decision = 'publish'): void
    {
        AiReview::create(['post_id' => $post->id, 'provider' => 'anthropic', 'model' => 'm', 'status' => 'done', 'score' => 8,
            'report' => ReviewSchema::normalise(['quality_score' => 8, 'summary' => 'ভালো', 'genre' => 'কবিতা', 'strengths' => [], 'suggestions' => [],
                'edits' => $edits, 'decision' => $decision, 'note_to_writer' => 'অভিনন্দন! লেখাটি ভালো লেগেছে।', 'excerpt' => 'বৃষ্টির দিনের কথা',
                'suggested_section' => 'poetry', 'tags' => ['বৃষ্টি', 'বর্ষা'], 'seo_title' => 'বৃষ্টির দিন', 'seo_description' => 'বর্ষার কবিতা',
                'policy_flags' => [], 'fits_magazine' => true, 'fit_reason' => 'ভালো'])]);
    }

    public function test_server_relabels_big_changes_as_word_changes(): void
    {
        $this->assertSame('spelling', CopyEdit::kindOf(['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling']));
        $this->assertSame('word', CopyEdit::kindOf(['original' => 'মেঘলা আকাশ', 'replacement' => 'কালো আকাশ', 'kind' => 'spelling']), 'A "spelling" fix that changes a word is a word change');
        $r = ReviewSchema::normalise(['edits' => [
            ['original' => '<b>x</b>', 'replacement' => 'y', 'kind' => 'typo'],          // markup: refused
            ['original' => str_repeat('ক', 90), 'replacement' => 'খ', 'kind' => 'typo'], // too long: refused
            ['original' => 'উত্স', 'replacement' => 'উৎস', 'kind' => 'spelling'],
        ]]);
        $this->assertCount(1, $r['edits']);
    }

    public function test_edits_touch_only_text_and_keep_markup(): void
    {
        $out = CopyEdit::apply('<p>আমি <strong>করিনা</strong> ভয়<br>উত্স খুঁজি</p>', [
            ['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling'],
            ['original' => 'উত্স', 'replacement' => 'উৎস', 'kind' => 'spelling'],
            ['original' => 'নেই এমন', 'replacement' => 'x', 'kind' => 'typo'],
        ]);
        $this->assertSame('<p>আমি <strong>করি না</strong> ভয়<br>উৎস খুঁজি</p>', $out['html']);
        $this->assertSame([2], $out['missing']);
    }

    public function test_owner_approves_package_safe_edits_and_metadata_go_live(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin]);
        $writer = User::factory()->create();
        $post = $this->makePost(['slug' => 'draft-x', 'title' => 'বৃষ্টির দিন', 'status' => 'pending', 'published_at' => null, 'body_html' => '<p>আমি করিনা ভয়</p>']);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        $this->packageFor($post, [['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling']]);

        $this->assertSame([0], EditorPackage::safeIndexes($post->fresh()));
        $this->actingAs($owner);
        $this->assertSame('published', EditorPackage::approve($post->fresh(), $owner, now(), 'brishtir-din', 'অভিনন্দন!', [0], true));
        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertStringContainsString('করি না', $post->body_html);
        $this->assertSame('বৃষ্টির দিন', $post->seo_title);
        $this->assertSame('বৃষ্টির দিনের কথা', $post->excerpt);
        $this->assertEqualsCanonicalizing(['বৃষ্টি', 'বর্ষা'], $post->tags->pluck('name')->all());
    }

    /** Owner, 2026-11-12: "whats next?" The piece's page says what to do; "বদল চাই" carries the AI's whole advice and fixes. */
    public function test_review_guide_and_request_changes_carry_the_whole_advice(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $owner = User::factory()->create(['role' => Role::Admin]);
        $writer = User::factory()->create(['email' => 'lekhok@example.com']);
        $post = $this->makePost(['slug' => 'draft-z', 'title' => 'তিন দেশের গল্প', 'status' => 'pending', 'published_at' => null, 'body_html' => '<p>আমি করিনা ভয়, মেঘলা আকাশ</p>']);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        $this->packageFor($post, [['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling'], ['original' => 'মেঘলা আকাশ', 'replacement' => 'কালো আকাশ', 'kind' => 'word']], 'changes');
        $review = $post->latestAiReview()->first();
        $review->forceFill(['report' => ['suggestions' => ['শেষটা আগে থেকে আভাসে রাখুন', 'তথ্যভারী অংশ ছোট করুন']] + $review->report])->save();

        // A newer report's letter already carries the advice; an older report's short note gets its points added.
        $this->assertSame('অভিনন্দন! লেখাটি ভালো লেগেছে।', \App\Support\Review::changesMessage($post->fresh()));
        $old = $review->report;
        unset($old['letter']);
        $review->forceFill(['report' => $old])->save();
        $message = \App\Support\Review::changesMessage($post->fresh());
        $this->assertStringContainsString("আর একবার ফিরে দেখলে ভালো হয়:\n— শেষটা আগে থেকে আভাসে রাখুন\n— তথ্যভারী অংশ ছোট করুন", $message);
        $this->assertStringContainsString('personal letter', \App\Ai\Prompt::system());
        $this->assertSame(['requestChanges', 'approve', 'reject'], \App\Filament\Resources\Posts\Tables\PostsTable::decisionOrder($post->fresh()));

        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $page = \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->id])
            ->assertSee('এই লেখা নিয়ে কী করবেন')->assertSee('এআই-এর প্রস্তাব')->assertSee('lekhok@example.com');

        // From the AI report straight to the decision it recommends.
        $page->mountAction('aiReport')->callMountedAction(['next' => 'requestChanges'])->assertActionMounted('requestChanges')
            ->assertSchemaStateSet(['note' => $message, 'edits' => [0]], 'mountedActionSchema0')
            ->assertSchemaComponentExists('email_preview', 'mountedActionSchema0')   // the e-mail, before sending
            ->callMountedAction()->assertHasNoFormErrors();

        $post->refresh();
        $this->assertSame('changes', $post->status);
        $this->assertSame($message, $post->review_note);
        $this->assertStringContainsString('করি না', $post->body_html, 'The small fix goes in before the piece goes back');
        $this->assertStringContainsString('মেঘলা আকাশ', $post->body_html, 'Never a word change without the writer');
        \Illuminate\Support\Facades\Notification::assertSentTo($writer, \App\Notifications\SubmissionDecision::class, function ($n) use ($writer) {
            $mail = implode(' ', array_map('strval', $n->toMail($writer)->introLines));

            return str_contains($mail, 'কিছু না বদলে আবার জমা দিন') && str_contains($mail, 'করিনা → করি না') && ! str_contains($mail, 'কালো আকাশ');
        });
        $this->actingAs($writer)->get('/submit/'.$post->id.'/')->assertSee('কিছু না বদলেই আবার জমা দিতে পারেন')->assertSee('করিনা → করি না');
        $this->assertSame([['original' => 'করিনা', 'replacement' => 'করি না']], $post->review_fixes);
    }

    public function test_word_changes_wait_for_the_writer(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin]);
        $writer = User::factory()->create();
        $post = $this->makePost(['slug' => 'draft-y', 'title' => 'নদী', 'status' => 'pending', 'published_at' => null, 'body_html' => '<p>মেঘলা আকাশ আর নদী</p>']);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        $this->packageFor($post, [['original' => 'মেঘলা আকাশ', 'replacement' => 'কালো আকাশ', 'kind' => 'word']]);

        $this->assertSame([], EditorPackage::safeIndexes($post->fresh()), 'Word changes are never ticked by default');
        $this->actingAs($owner);
        $this->assertSame('confirm', EditorPackage::approve($post->fresh(), $owner, now()->addDay(), 'nodi', null, [0], false));
        $this->assertStringContainsString('মেঘলা আকাশ', $post->fresh()->body_html, 'Nothing changes before the writer agrees');

        $this->actingAs($writer)->get('/submit/'.$post->id.'/')->assertOk()->assertSee('সম্পাদকের প্রস্তাবিত বদল')->assertSee('কালো আকাশ');
        $this->post('/submit/'.$post->id.'/confirm/', ['answer' => 'accept'])->assertRedirect();
        $post->refresh();
        $this->assertSame('scheduled', $post->status);
        $this->assertStringContainsString('কালো আকাশ', $post->body_html);
    }

    public function test_writer_can_object_and_nothing_changes(): void
    {
        $writer = User::factory()->create();
        $post = $this->makePost(['slug' => 'draft-z', 'status' => 'confirm', 'published_at' => null, 'body_html' => '<p>মেঘলা আকাশ</p>']);
        $post->forceFill(['submitted_by' => $writer->id, 'pending_edits' => ['edits' => [['original' => 'মেঘলা', 'replacement' => 'কালো', 'kind' => 'word']], 'at' => now()->toIso8601String()]])->save();
        $this->actingAs($writer)->post('/submit/'.$post->id.'/confirm/', ['answer' => 'object', 'reason' => 'মেঘলা শব্দটাই আমি চাই'])->assertRedirect();
        $post->refresh();
        $this->assertSame('pending', $post->status);
        $this->assertStringContainsString('মেঘলা আকাশ', $post->body_html);
        $this->assertStringContainsString('মেঘলা শব্দটাই আমি চাই', $post->review_note);
    }

    public function test_next_publishing_slot_is_a_free_mon_wed_fri_morning(): void
    {
        $slot = \App\Support\Schedule::nextSlot()->setTimezone('Asia/Kolkata');
        $this->assertContains($slot->dayOfWeek, \App\Support\Schedule::DAYS);
        $this->assertSame('09:00', $slot->format('H:i'));
        $this->makePost(['slug' => 'taken', 'status' => 'scheduled', 'published_at' => $slot->copy()->utc()]);
        $this->assertNotSame($slot->toDateString(), \App\Support\Schedule::nextSlot()->setTimezone('Asia/Kolkata')->toDateString());
    }

    public function test_illustrator_proposes_and_owner_approval_puts_it_on_the_page(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => 'k', 'ai.openai.image_model' => 'img-model', 'ai.openai.image_price_usd' => 0.04]);
        \App\Ai\Claude::$fake = fn ($purpose, $user, $schema) => ['scene' => 'A lone boat on a misty river at dawn'];
        $img = imagecreatetruecolor(1536, 1024);
        imagefill($img, 0, 0, imagecolorallocate($img, 45, 170, 226));
        ob_start(); imagepng($img); $png = ob_get_clean();
        \Illuminate\Support\Facades\Http::fake(['*/images/generations' => \Illuminate\Support\Facades\Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);

        $post = $this->makePost(['slug' => 'nodi-bhor', 'title' => 'নদীর ভোর']);
        $this->assertNull(\App\Ai\Illustrator::make($post));
        $post->refresh();
        $this->assertNotNull($post->proposed_image_path);
        $this->assertNull($post->featured_image_path, 'Nothing on the site before approval');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r['prompt'], 'no text') || str_contains($r['prompt'], 'no text, letters'));
        $this->assertDatabaseHas('ai_usages', ['purpose' => 'image']);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\AiImages::class)->assertSee('নদীর ভোর')
            ->set('picked.'.$post->id, true)->call('approvePicked');
        $post->refresh();
        $this->assertSame(1536, $post->featured_image_width);
        $this->assertTrue($post->image_is_ai);
        $this->get('/nodi-bhor/')->assertSee('ছবি: এআই-এর সাহায্যে আঁকা');
        \App\Ai\Claude::$fake = null;
        foreach (glob(public_path('media/covers/*/*/*')) ?: [] as $f) { @unlink($f); }
    }

    public function test_designer_paints_a_portrait_issue_cover_with_room_for_masthead_and_title(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => 'k', 'ai.openai.image_model' => 'img-model']);
        $briefs = [];
        \App\Ai\Claude::$fake = function ($purpose, $user, $schema) use (&$briefs) {
            $briefs[] = $user;

            return ['scene' => 'Paddy fields under the first winter mist'];
        };
        $img = imagecreatetruecolor(1024, 1536);
        ob_start(); imagepng($img); $png = ob_get_clean();
        \Illuminate\Support\Facades\Http::fake(['*/images/generations' => \Illuminate\Support\Facades\Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);
        \App\Models\Issue::create(['slug' => '2026-10', 'title' => 'আগের', 'status' => 'published', 'ai_plan' => ['cover_scene' => 'Lamps on a river ghat']]);
        $issue = \App\Models\Issue::create(['slug' => '2026-11', 'title' => 'অগ্রহায়ণ সংখ্যা', 'status' => 'proposed', 'theme_key' => 'hemonto', 'ai_plan' => []]);

        $this->assertNull(\App\Ai\Illustrator::forIssue($issue));
        $this->assertNotNull($issue->fresh()->proposed_cover_path);
        $this->assertNull($issue->fresh()->cover_path, 'Only a proposal until the owner picks it');
        $this->assertSame('Paddy fields under the first winter mist', $issue->fresh()->ai_plan['cover_scene'], 'Remembered so next month differs');
        $this->assertStringContainsString('Lamps on a river ghat', $briefs[0], 'Recent covers are not repeated');
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r['size'] === '1024x1536' && str_contains($r['prompt'], 'portrait composition'));
        \App\Ai\Claude::$fake = null;
        foreach (glob(public_path('media/covers/*/*/*')) ?: [] as $f) { @unlink($f); }
    }

    public function test_illustrator_does_nothing_when_paused_or_unconfigured(): void
    {
        $post = $this->makePost(['slug' => 'x1']);
        $this->assertNotNull(\App\Ai\Illustrator::make($post), 'AI off by default');
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => 'k']);
        $this->assertStringContainsString('OPENAI_IMAGE_MODEL', \App\Ai\Illustrator::make($post));
    }

    public function test_issue_proposal_theme_lineup_and_editorial_draft_then_owner_approval(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $owner = User::factory()->create(['role' => Role::Admin, 'name' => 'সম্পাদক নাম']);
        \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $posts = collect(range(1, 5))->map(fn ($i) => $this->makePost(['slug' => "lekha-{$i}", 'title' => "লেখা {$i}", 'published_at' => now()->subDays($i)]));
        $month = now('Asia/Kolkata')->startOfMonth()->addMonth();
        \App\Ai\Claude::$fake = fn ($purpose, $user, $schema) => $purpose === 'issue_plan'
            ? ['theme_key' => \App\Ai\IssuePlanner::CALENDAR[$month->month][0], 'title' => 'পরীক্ষা সংখ্যা', 'post_ids' => [$posts[2]->id, $posts[0]->id, $posts[1]->id, 999999],
               'reason' => 'ভারসাম্য', 'cover_lines' => ['এক', 'দুই'], 'editorial_brief' => 'ঋতু']
            : ['title' => 'সম্পাদকীয়', 'paragraphs' => ['প্রিয় পাঠক,', 'এই সংখ্যায় <b>নতুন</b> লেখা।']];

        [$issue, $err] = \App\Ai\IssuePlanner::propose($owner, $month);
        $this->assertNull($err);
        $this->assertSame('proposed', $issue->status);
        $this->assertSame([$posts[2]->id, $posts[0]->id, $posts[1]->id], $issue->ai_plan['post_ids'], 'Unknown ids are dropped, order kept');
        $this->get('/sonkha/'.$issue->slug.'/')->assertNotFound();

        $editorial = $issue->fresh()->editorial;
        $this->assertSame('draft', $editorial->status);
        $this->assertNotNull($editorial->ai_drafted_at);
        $this->assertSame($owner->id, $editorial->authors->first()->user_id, "Published under the owner's name");
        $this->assertStringContainsString('&lt;b&gt;', $editorial->body_html, 'Model text is escaped, never markup');

        \App\Ai\IssuePlanner::approve($issue->fresh(), $owner, now()->subMinute(), [$posts[2]->id, $posts[0]->id]);
        $this->assertSame('published', $issue->fresh()->status);
        $this->assertSame($issue->id, $posts[2]->fresh()->issue_id);
        $this->assertNull($posts[1]->fresh()->issue_id, 'Owner untick = left out');
        $this->get('/sonkha/'.$issue->slug.'/')->assertOk();
        \App\Ai\Claude::$fake = null;
    }

    public function test_comment_pre_screen_is_advice_only(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        \App\Ai\Claude::$fake = fn () => ['verdict' => 'spam', 'reason' => 'বিজ্ঞাপন'];
        $c = \App\Models\Comment::create(['post_id' => $this->makePost(['slug' => 'c1'])->id, 'author_name' => 'বট', 'body' => 'cheap loans', 'status' => 'pending']);
        \App\Ai\CommentScreen::run($c);
        $c->refresh();
        $this->assertSame('spam', $c->ai_verdict);
        $this->assertSame('pending', $c->status, 'The editor still decides');
        \App\Ai\Claude::$fake = null;
    }

    /** Owner, 2026-11-14: "when clicking request changes getting error". A fix whose words hold a line break (a poem) broke the e-mail preview. */
    public function test_request_changes_opens_when_a_small_fix_spans_two_lines(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin]);
        $writer = User::factory()->create();
        $post = $this->makePost(['slug' => 'draft-poem', 'title' => 'বৃষ্টি', 'status' => 'pending', 'published_at' => null, 'body_html' => "<p>আমি করিনা<br>ভয় ,মেঘলা আকাশ।</p>"]);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        $this->packageFor($post, [['original' => "করিনা\nভয় ,", 'replacement' => "করি না\nভয়,", 'kind' => 'spacing']], 'changes');
        $safe = \App\Ai\EditorPackage::safeIndexes($post->fresh());
        $fixes = \App\Ai\EditorPackage::fixesPreview($post->fresh(), $safe);
        $this->assertNotSame([], $safe, 'The two-line fix is one of the small fixes');
        $this->assertSame(count($safe), count($fixes), 'One pair per ticked fix, whatever is inside the words');

        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->id])
            ->mountAction('requestChanges')->assertActionMounted('requestChanges')
            ->assertSchemaComponentExists('email_preview', 'mountedActionSchema0');
    }

    /** D192 (owner: "how to apply the corrections as an admin, without sending back to the writer?"). */
    public function test_admin_applies_small_fixes_at_once_without_sending_the_piece_back(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $owner = User::factory()->create(['role' => Role::Admin]);
        $writer = User::factory()->create();
        $post = $this->makePost(['slug' => 'draft-fix', 'title' => 'বৃষ্টি', 'status' => 'pending', 'published_at' => null, 'body_html' => '<p>আমি করিনা ভয়, মেঘলা আকাশ ।</p>']);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        $this->packageFor($post, [['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling'], ['original' => 'আকাশ ।', 'replacement' => 'আকাশ।', 'kind' => 'spacing'],
            ['original' => 'মেঘলা আকাশ', 'replacement' => 'কালো আকাশ', 'kind' => 'spelling']]);   // the last one changes a word: never from here
        $this->assertCount(2, \App\Ai\EditorPackage::waitingFixes($post->fresh()));
        $this->assertCount(1, \App\Ai\EditorPackage::wordChanges($post->fresh()));

        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->id])
            ->mountAction('applyFixes')->assertSchemaStateSet(['edits' => [0, 1]], 'mountedActionSchema0')
            ->setActionData(['edits' => [0]])->callMountedAction()->assertHasNoFormErrors()
            ->call('save')->assertHasNoFormErrors();   // owner: "I applied, then saved": the save must keep the correction
        $post->refresh();
        $this->assertStringContainsString('করি না', $post->body_html);
        $this->assertStringNotContainsString('করিনা', $post->body_html, 'Saving the open form does not bring the old text back');
        $this->assertStringContainsString('আকাশ ।', $post->body_html, 'The unticked fix stays out');
        $this->assertStringContainsString('মেঘলা আকাশ', $post->body_html, 'Never a word change');
        $this->assertSame('pending', $post->status, 'The piece stays where it is');
        \Illuminate\Support\Facades\Notification::assertNothingSent();
        $this->assertSame([1], array_keys(\App\Ai\EditorPackage::waitingFixes($post)), 'What is already in is no longer offered');
        $this->assertSame(0, \App\Ai\EditorPackage::applyNow($post, [2]), 'A word change cannot be forced through');

        // The writer is told of both rounds of fixes in the next letter, each once.
        \App\Ai\EditorPackage::applySafeEdits($post->fresh(), [1]);
        $this->assertSame([['original' => 'করিনা', 'replacement' => 'করি না'], ['original' => 'আকাশ ।', 'replacement' => 'আকাশ।']], $post->fresh()->review_fixes);
    }

    /** Owner, 2026-11-14: "I already added the corrections; on approve the same corrections show again". */
    public function test_corrections_already_in_the_piece_are_not_offered_again_anywhere(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin]);
        $writer = User::factory()->create();
        $post = $this->makePost(['slug' => 'draft-sync', 'title' => 'বৃষ্টি', 'status' => 'pending', 'published_at' => null, 'body_html' => '<p>আমি করিনা ভয়, মেঘলা আকাশ ।</p>']);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        $this->packageFor($post, [['original' => 'করিনা', 'replacement' => 'করি না', 'kind' => 'spelling'], ['original' => 'আকাশ ।', 'replacement' => 'আকাশ।', 'kind' => 'spacing'],
            ['original' => 'মেঘলা আকাশ', 'replacement' => 'কালো আকাশ', 'kind' => 'spelling']]);
        $this->assertSame([0, 1, 2], \App\Ai\EditorPackage::openIndexes($post->fresh()));
        \App\Ai\EditorPackage::applyNow($post->fresh(), [0]);
        $post->refresh();
        $this->assertSame([1, 2], \App\Ai\EditorPackage::openIndexes($post), 'The one already in is gone from the list');
        $this->assertSame([1], \App\Ai\EditorPackage::openSafe($post));

        $this->actingAs($owner);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $page = \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->id]);
        $page->mountAction('approve')->assertSchemaStateSet(['edits' => [1]], 'mountedActionSchema0');
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->id])
            ->mountAction('requestChanges')->assertSchemaStateSet(['edits' => [1]], 'mountedActionSchema0');

        // A correction typed in by hand counts as done too.
        $post->forceFill(['body_html' => '<p>আমি করি না ভয়, মেঘলা আকাশ।</p>'])->save();
        $this->assertSame([2], \App\Ai\EditorPackage::openIndexes($post->fresh()));
    }

    /** Owner, 2026-11-14: a check whose web searches did not work said "possible match". It is "not checked yet". */
    public function test_a_plagiarism_check_that_could_not_search_is_not_a_verdict(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $post = $this->makePost(['slug' => 'draft-pl', 'title' => 'বৃষ্টি', 'status' => 'pending', 'published_at' => null,
            'body_html' => '<p>আকাশ ভরা মেঘ আর ভিজে যাওয়া মাঠের কথা আজ খুব মনে পড়ে।</p><p>তোমার কথা মনে পড়ে সারা দুপুর, জানালার ধারে বসে থাকি।</p>']);
        \App\Ai\Claude::$fake = fn () => null;   // the search gave nothing back
        foreach ([1, 2] as $try) {
            $this->assertNotNull(\App\Ai\PlagiarismCheck::run($post->fresh()));
            $this->assertSame($try, $post->fresh()->plagiarism['tries']);
            $this->assertNull($post->fresh()->plagiarism_at, 'Tried again later by itself');
            $this->assertArrayNotHasKey('verdict', $post->fresh()->plagiarism);
        }
        $this->assertStringContainsString('এখনও যাচাই হয়নি', \App\Ai\PlagiarismCheck::summary($post->fresh()));
        \App\Ai\PlagiarismCheck::run($post->fresh());
        $this->assertNotNull($post->fresh()->plagiarism_at, 'After three tries it stops trying by itself');

        \App\Ai\Claude::$fake = fn () => ['verdict' => 'clear', 'matches' => [], 'note' => 'কোথাও পাওয়া যায়নি'];
        $this->assertNull(\App\Ai\PlagiarismCheck::run($post->fresh()));
        $this->assertSame('clear', $post->fresh()->plagiarism['verdict']);
        \App\Ai\Claude::$fake = null;
    }
}
