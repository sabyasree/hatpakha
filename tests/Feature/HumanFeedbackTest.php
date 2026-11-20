<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\DeskWriter;
use App\Ai\FeedbackLessons;
use App\Ai\Rewriter;
use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\WritingRules\Pages\CreateWritingRule;
use App\Filament\Resources\WritingRules\WritingRuleResource;
use App\Models\Author;
use App\Models\Post;
use App\Models\Section;
use App\Models\User;
use App\Models\WritingFeedback;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D113 (owner): real readers' feedback on AI writing: rewrite this piece (owner accepts) and/or a standing rule for the next ones. */
class HumanFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Author $desk;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $this->desk = Author::firstOrCreate(['slug' => 'hatpakha-desk'], ['display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function aiDraft(string $section = 'jonopod'): Post
    {
        $sec = Section::where('slug', $section)->firstOrFail();

        return $this->makePost(['status' => 'draft', 'published_at' => null, 'section_id' => $sec->id, 'title' => 'চন্দননগরের সন্ধ্যা', 'slug' => 'desk-x-'.uniqid(),
            'body_html' => '<p>গঙ্গার ধারে আলো জ্বলে।</p><p>স্ট্র্যান্ডে হাওয়া।</p>', 'ai_drafted_at' => now()], $this->desk);
    }

    public function test_phase_one_sections_exist_with_their_desk_jobs(): void
    {
        foreach (['phire-pora' => 'literature', 'jonopod' => 'life', 'rohosyomoy' => 'literature'] as $slug => $group) {
            $s = Section::where('slug', $slug)->first();
            $this->assertNotNull($s, $slug);
            $this->assertSame($group, $s->nav_group);
            $this->assertContains($slug, DeskWriter::JOBS);
            $this->assertArrayHasKey($slug, DeskWriter::SECTIONS);
        }
        Claude::$fake = fn () => ['plan' => ['moment' => 'সন্ধ্যা', 'feeling' => 'মায়া', 'details' => ['আলো'], 'closing_image' => 'জল'],
            'title' => 'আলেয়া', 'excerpt' => 'জলার আলো।', 'paragraphs' => ['লোকে বলে…'], 'facts_to_check' => ['কোন জেলায় বলা হয়'], 'photo_ideas' => []];
        [$p] = DeskWriter::draft('rohosyomoy', 'আলেয়া');
        $this->assertSame('draft', $p->status);
        $this->assertSame('rohosyomoy', $p->section->slug);
    }

    public function test_feedback_rewrites_this_piece_only_when_the_owner_accepts_and_can_go_back(): void
    {
        $post = $this->aiDraft();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('humanFeedback', ['giver' => 'স্কুলশিক্ষিকা', 'body' => 'বড্ড তাড়াহুড়ো। নদীর শব্দ আরও থাকুক।', 'apply_now' => true, 'apply_future' => false])
            ->assertHasNoActionErrors();
        $f = WritingFeedback::firstOrFail();
        $this->assertSame('queued', $f->rewrite_status);

        Claude::$fake = fn ($purpose) => ['title' => 'গঙ্গার ধারে চন্দননগর', 'paragraphs' => ['ঢেউয়ের শব্দে সন্ধ্যা নামে।', 'স্ট্র্যান্ডে হাওয়া।'], 'what_changed' => 'নদীর শব্দ বাড়ানো হয়েছে।'];
        $this->assertSame(1, Rewriter::runQueued());
        $post->refresh();
        $this->assertStringContainsString('গঙ্গার ধারে আলো জ্বলে', $post->body_html, 'Nothing changes until the owner accepts');
        $this->assertSame('proposed', $post->rewrite_kind);
        $this->assertSame('done', $f->fresh()->rewrite_status);

        Rewriter::swap($post);
        $post->refresh();
        $this->assertStringContainsString('ঢেউয়ের শব্দে', $post->body_html);
        $this->assertSame('গঙ্গার ধারে চন্দননগর', $post->title);
        $this->assertSame('previous', $post->rewrite_kind, 'The old version is kept');
        Rewriter::swap($post);
        $this->assertStringContainsString('গঙ্গার ধারে আলো জ্বলে', $post->fresh()->body_html, 'And the owner can go back');
    }

    public function test_feedback_becomes_a_scoped_rule_the_owner_can_edit_or_switch_off(): void
    {
        $post = $this->aiDraft();
        $f = WritingFeedback::create(['post_id' => $post->id, 'body' => 'এত বিশেষণ নয়।', 'apply_future' => true, 'scope' => 'section', 'section_id' => $post->section_id]);
        Claude::$fake = fn () => ['rules' => ['বিশেষণ কম, দৃশ্য বেশি।']];
        Rewriter::runQueued();
        $this->assertSame('• বিশেষণ কম, দৃশ্য বেশি।', $f->fresh()->lesson);

        $this->assertStringContainsString('বিশেষণ কম', FeedbackLessons::forPrompt('jonopod'));
        $this->assertSame('', FeedbackLessons::forPrompt('kitchen'), 'A section rule stays in its section');
        $f->update(['active' => false]);
        $this->assertSame('', FeedbackLessons::forPrompt('jonopod'), 'Switched off');

        WritingFeedback::create(['body' => 'x', 'lesson' => 'সংলাপ চলিত বাংলায়।', 'apply_future' => true, 'scope' => 'all']);
        $this->assertStringContainsString('সংলাপ চলিত', FeedbackLessons::forPrompt('kitchen'), 'An "all" rule reaches every writer');
        foreach (['DeskWriter', 'SerialWriter', 'EditorialWriter', 'Rewriter'] as $w) {
            $this->assertStringContainsString('FeedbackLessons::forPrompt', file_get_contents(app_path("Ai/{$w}.php")), $w.' reads the rules');
        }
    }

    public function test_a_human_writers_piece_is_never_rewritten(): void
    {
        $writer = Author::create(['slug' => 'real-writer', 'display_name' => 'আসল লেখক']);
        $post = $this->makePost(['ai_drafted_at' => now(), 'slug' => 'human'], $writer);
        $this->assertFalse(Rewriter::canRewrite($post));
        $f = WritingFeedback::create(['post_id' => $post->id, 'body' => 'বদলাও', 'apply_now' => true, 'rewrite_status' => 'queued']);
        Rewriter::runQueued();
        $this->assertSame('failed', $f->fresh()->rewrite_status);
        $this->assertNull($post->fresh()->rewrite_html);
    }

    /** D251 (owner: "there is no way to see how it looks before publish from desk"). */
    public function test_the_desk_can_see_a_piece_as_readers_will_before_it_is_published(): void
    {
        $post = $this->aiDraft();
        $post->forceFill(['status' => 'pending', 'slug' => 'not-yet'])->save();
        $this->get('/not-yet/')->assertNotFound();
        $this->get('/post-preview/'.$post->id.'/')->assertRedirect();   // a guest is sent to sign in
        $this->actingAs(User::factory()->create())->get('/post-preview/'.$post->id.'/')->assertForbidden();

        $editor = User::factory()->create(['role' => Role::Editor]);
        $html = $this->actingAs($editor)->get('/post-preview/'.$post->id.'/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('প্রিভিউ: পাঠকেরা ঠিক এভাবেই দেখবেন')->assertSee('এখনও প্রকাশিত হয়নি')->assertSee($post->title)->getContent();
        $this->assertStringNotContainsString('data-view=', $html, 'A preview is never counted as a read');
        $this->assertStringContainsString('noindex', $html);

        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionVisible('preview')
            ->assertActionHasUrl('preview', \App\Support\Url::to('/post-preview/'.$post->id));
    }

    /** D250 (owner: "in desk the header is not sticky; difficult for a long writing"). */
    public function test_the_desk_editor_keeps_its_toolbar_in_view(): void
    {
        $post = $this->aiDraft();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertSee('fi-fo-rich-editor-sticky-toolbar', false);
        $this->assertStringContainsString('ed-toolbar sticky top-0', file_get_contents(resource_path('views/submit.blade.php')), 'The writers\' form toolbar stays too');
    }

    /** D247 (owner: "how to rewrite the editorial? there is no option to ask to rewrite"). */
    public function test_the_ais_editorial_draft_can_be_sent_back_with_notes(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin, 'name' => 'Owner']);
        $mine = Author::create(['user_id' => $owner->id, 'slug' => 'editor-'.$owner->id, 'display_name' => 'Owner']);
        $section = Section::firstOrCreate(['slug' => 'editorial'], ['name' => 'Editorial', 'name_bn' => 'সম্পাদকীয়']);
        $issue = \App\Models\Issue::create(['slug' => '2026-12', 'title' => 'পৌষ সংখ্যা', 'status' => 'draft']);
        $ed = $this->makePost(['slug' => 'sompadokiyo-2026-12', 'title' => 'শীতের প্রথম আলো', 'status' => 'draft', 'section_id' => $section->id, 'issue_id' => $issue->id,
            'ai_drafted_at' => now(), 'body_html' => '<p>কুয়াশা নামে গলিতে।</p>'], $mine);
        $issue->forceFill(['editorial_post_id' => $ed->id])->save();
        $this->assertTrue(Rewriter::canRewrite($ed), 'Signed with the owner\'s name, but the AI wrote it');

        $handWritten = $this->makePost(['slug' => 'sompadokiyo-old', 'status' => 'draft', 'section_id' => $section->id], $mine);
        $this->assertFalse(Rewriter::canRewrite($handWritten), 'An editorial the owner wrote is never rewritten');

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\Editorials::class)->assertSee('আবার লেখান')->assertSee('action=humanFeedback', false);
        Livewire::test(EditPost::class, ['record' => $ed->getRouteKey()])->assertActionVisible('humanFeedback')->assertActionHasLabel('humanFeedback', 'আবার লেখান')
            ->callAction('humanFeedback', ['body' => 'আরও ছোট করুন, শুরুটা শীতের সকাল দিয়ে।', 'apply_now' => true, 'apply_future' => false])->assertHasNoActionErrors();

        $prompt = '';
        Claude::$fake = function ($purpose, $system = '') use (&$prompt) { $prompt = (string) $system; return ['title' => 'শীতের সকাল', 'paragraphs' => ['ভোরে কুয়াশা।'], 'what_changed' => 'ছোট করা হয়েছে।']; };
        $this->assertSame(1, Rewriter::runQueued());
        $ed->refresh();
        $this->assertStringContainsString('কুয়াশা নামে গলিতে', $ed->body_html, 'Nothing changes until the owner chooses the new version');
        $this->assertSame('proposed', $ed->rewrite_kind);
        $this->assertStringContainsString('ভোরে কুয়াশা', $ed->rewrite_html);
        $this->assertSame('draft', $ed->status, 'Never published by this');
    }

    public function test_owner_adds_own_rule_and_editors_cannot_open_the_rules(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(WritingRuleResource::canAccess());
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(CreateWritingRule::class)->fillForm(['lesson' => 'প্রতিটি পর্ব একটি দৃশ্য দিয়ে শুরু।', 'scope' => 'all', 'active' => true])->call('create')->assertHasNoFormErrors();
        $this->assertStringContainsString('একটি দৃশ্য দিয়ে শুরু', FeedbackLessons::forPrompt());
    }
}
