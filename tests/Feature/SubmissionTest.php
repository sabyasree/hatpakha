<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Post;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 3b: the website form is the only way to submit (D29). */
class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $writer;

    private Section $poetry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = User::factory()->create(['name' => 'রিনা দাস', 'role' => Role::Reader]);
        $this->poetry = Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'name_en' => 'Poetry', 'format' => 'poem', 'sort' => 1]);
    }

    private function poem(array $extra = []): array
    {
        return array_merge(['title' => 'বৃষ্টির দিন', 'section_id' => $this->poetry->id,
            'body' => "আকাশ ভরা মেঘ\nভিজে গেছে মাঠ\n\nতোমার কথা মনে পড়ে\nসারা দুপুর", 'original' => '1', 'action' => 'submit'], $extra);
    }

    public function test_guests_are_sent_to_login_first(): void
    {
        $this->get('/submit/')->assertRedirect(url('/login'));
    }

    public function test_writer_submits_a_poem_and_sees_it_pending(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAs($this->writer)->get('/submit/')->assertOk()->assertSee('জমা দিন');
        $this->post('/submit/', $this->poem())->assertRedirect(url('/account').'/');
        \Illuminate\Support\Facades\Notification::assertSentTo($this->writer, \App\Notifications\FormReceived::class, fn ($n) => $n->title === 'বৃষ্টির দিন');   // D122

        $post = Post::where('title', 'বৃষ্টির দিন')->firstOrFail();
        $this->assertSame('pending', $post->status);
        $this->assertSame('poem', $post->format);
        $this->assertNotNull($post->originality_confirmed_at);
        $this->assertMatchesRegularExpression('/আকাশ ভরা মেঘ<br>\s*ভিজে গেছে মাঠ/u', $post->body_html);
        $this->assertSame(2, substr_count($post->body_html, '<p>'), 'Blank line = new stanza');
        $this->assertSame(Role::Writer, $this->writer->fresh()->role);
        $this->assertSame($this->writer->id, $post->authors->first()->user_id);
        $this->get('/account/')->assertSee('বৃষ্টির দিন')->assertSee('পাঠানো হয়েছে');

        // Not public, and no longer editable.
        $this->get('/'.$post->slug.'/')->assertNotFound();
        $this->post('/submit/'.$post->id.'/', $this->poem(['title' => 'বদল']))->assertForbidden();
    }

    public function test_originality_declaration_is_required_to_submit_but_not_to_save_a_draft(): void
    {
        $this->actingAs($this->writer)->post('/submit/', $this->poem(['original' => null]))->assertSessionHasErrors('original');
        $this->post('/submit/', $this->poem(['original' => null, 'action' => 'draft']))->assertRedirect();
        $this->assertSame('draft', Post::where('title', 'বৃষ্টির দিন')->value('status'));
    }

    public function test_html_and_scripts_typed_into_the_form_are_never_rendered(): void
    {
        $this->actingAs($this->writer)->post('/submit/', $this->poem(['body' => "<script>alert(1)</script>\n<b onmouseover=x()>লেখা</b> আরও কিছু লাইন লেখা হল এখানে"]));
        $html = Post::where('title', 'বৃষ্টির দিন')->value('body_html');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<b ', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html, 'Typed markup is shown as text, not executed');
    }

    public function test_three_submissions_per_day(): void
    {
        $this->actingAs($this->writer);
        foreach (['এক', 'দুই', 'তিন'] as $t) {
            $this->post('/submit/', $this->poem(['title' => $t]))->assertRedirect(url('/account').'/');
        }
        $this->post('/submit/', $this->poem(['title' => 'চার']))->assertSessionHasErrors('title');
        $this->assertSame(3, Post::where('status', 'pending')->count());
    }

    public function test_writers_cannot_open_someone_elses_piece(): void
    {
        $this->actingAs($this->writer)->post('/submit/', $this->poem(['action' => 'draft']));
        $id = Post::where('title', 'বৃষ্টির দিন')->value('id');
        $other = User::factory()->create();
        $this->actingAs($other)->get('/submit/'.$id.'/')->assertNotFound();
        $this->actingAs($other)->postJson('/submit/'.$id.'/autosave/', ['title' => 'x', 'body' => 'y'])->assertNotFound();
    }

    public function test_autosave_creates_a_draft_and_preview_matches_the_site(): void
    {
        $this->actingAs($this->writer);
        // Owner (2026-11-12): a title alone makes no draft.
        $this->postJson('/submit/autosave/', ['title' => 'Swami Vivekananda', 'body' => ''])->assertOk()->assertJson(['saved' => false]);
        $this->postJson('/submit/autosave/', ['title' => 'Swami Vivekananda', 'body' => 'এক'])->assertOk()->assertJson(['saved' => false]);
        $this->assertSame(0, Post::where('submitted_by', $this->writer->id)->count());
        $res = $this->postJson('/submit/autosave/', ['title' => 'অর্ধেক লেখা', 'body' => "প্রথম লাইন\nদ্বিতীয় লাইন", 'section_id' => $this->poetry->id])->assertOk()->json();
        $this->assertTrue($res['saved']);
        $this->assertSame('draft', Post::find($res['id'])->status);

        $this->postJson('/submit/'.$res['id'].'/autosave/', ['title' => 'পুরো লেখা', 'body' => 'আরও লেখা'])->assertOk();
        // A writer's unsent draft is theirs: not among the desk's drafts, but in its own tab.
        $this->actingAs(User::factory()->create(['role' => \App\Enums\Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $draft = Post::find($res['id']);
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\ListPosts::class, ['activeTab' => 'drafts'])->assertCanNotSeeTableRecords([$draft]);
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\ListPosts::class, ['activeTab' => 'writer_drafts'])->assertCanSeeTableRecords([$draft]);
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $draft->getRouteKey()])->assertSee('খসড়া, এখনও জমা দেননি');
        $this->actingAs($this->writer);
        $this->assertSame('পুরো লেখা', Post::find($res['id'])->title);

        $preview = $this->postJson('/submit/preview/', ['title' => 't', 'body' => "এক\nদুই", 'section_id' => $this->poetry->id])->json();
        $this->assertTrue($preview['poem']);
        $this->assertMatchesRegularExpression('/এক<br>\s*দুই/u', $preview['html']);
    }

    public function test_changes_requested_can_be_edited_and_resubmitted(): void
    {
        $this->actingAs($this->writer)->post('/submit/', $this->poem());
        $post = Post::where('title', 'বৃষ্টির দিন')->first();
        $post->forceFill(['status' => 'changes', 'review_note' => 'শেষ স্তবকটা আর একটু ভাবুন।'])->save();

        $this->get('/submit/'.$post->id.'/')->assertOk()->assertSee('শেষ স্তবকটা আর একটু ভাবুন।');
        $this->post('/submit/'.$post->id.'/', $this->poem(['body' => "নতুন করে লেখা কবিতা\nআরও ভালো হল\n\nশেষ স্তবক বদলেছি\nএবার পড়ুন"]))->assertRedirect(url('/account').'/');
        $this->assertSame('pending', $post->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'submission.resubmitted']);
    }

    public function test_editor_formatting_is_kept_but_only_the_safe_set(): void
    {
        $html = '<h2 style="color:red">উপশিরোনাম</h2><p>আকাশ <strong>ভরা</strong> <em>মেঘ</em> <u>দাগ</u><br>ভিজে গেছে <a href="https://spam.example">মাঠ</a></p>'
            .'<blockquote><p>উদ্ধৃতি</p></blockquote><ul><li>এক</li></ul><hr><p onclick="x()">তোমার কথা</p><img src="x" onerror="alert(1)"><script>alert(1)</script><p></p>';
        $this->actingAs($this->writer)->post('/submit/', $this->poem(['body' => $html, 'body_format' => 'html', 'section_id' => $this->poetry->id]))
            ->assertSessionHasNoErrors();
        $body = Post::where('title', 'বৃষ্টির দিন')->firstOrFail()->body_html;
        foreach (['<strong>ভরা</strong>', '<em>মেঘ</em>', '<u>দাগ</u>', 'উপশিরোনাম', '<blockquote>', '<li>এক</li>', 'মাঠ', 'তোমার কথা'] as $kept) {
            $this->assertStringContainsString($kept, $body);
        }
        foreach (['<a ', 'spam.example', 'onclick', 'onerror', '<img', '<script', 'style='] as $gone) {
            $this->assertStringNotContainsString($gone, $body);
        }
        // The editor reopens with the formatting (JSON island, never executed).
        $post = Post::where('title', 'বৃষ্টির দিন')->firstOrFail();
        $post->forceFill(['status' => 'draft'])->save();
        $this->get('/submit/'.$post->id.'/')->assertOk()->assertSee('id="body-initial"', false)->assertSee('\u003Cstrong\u003E', false);
    }

    public function test_empty_editor_is_too_short_even_with_markup(): void
    {
        $this->actingAs($this->writer)->post('/submit/', $this->poem(['body' => str_repeat('<p><strong></strong></p>', 20), 'body_format' => 'html']))
            ->assertSessionHasErrors('body');
    }

    public function test_alignment_strike_and_super_subscript_are_kept_safely(): void
    {
        $html = '<p style="text-align: center; color: red">মাঝখানে <s>কাটা</s> ১<sup>ম</sup> H<sub>2</sub>O</p>'
            .'<h3 style="text-align: right">ডানে</h3><p style="text-align: left">বাঁয়ে আর আরও কিছু লেখা যাতে দৈর্ঘ্য পূর্ণ হয়</p><p class="hidden fixed inset-0">লুকোনো নয়</p>';
        $this->actingAs($this->writer)->post('/submit/', $this->poem(['body' => $html, 'body_format' => 'html']))->assertSessionHasNoErrors();
        $post = Post::where('title', 'বৃষ্টির দিন')->firstOrFail();
        $body = $post->body_html;
        foreach (['class="ta-center"', 'class="ta-right"', 'class="ta-left"', '<s>কাটা</s>', '<sup>ম</sup>', '<sub>2</sub>', 'লুকোনো নয়'] as $kept) {
            $this->assertStringContainsString($kept, $body);
        }
        foreach (['style=', 'color', 'hidden', 'fixed'] as $gone) {
            $this->assertStringNotContainsString($gone, $body);
        }
        // Published page shows the alignment.
        $post->forceFill(['status' => 'published', 'published_at' => now()->subMinute(), 'slug' => 'brishtir-din'])->save();
        $this->get('/brishtir-din/')->assertSee('class="ta-center"', false);
    }

    public function test_alignment_from_the_desk_editor_survives_a_save(): void
    {
        $post = $this->makePost(['slug' => 'desk-align']);
        $post->update(['body_html' => '<p style="text-align: center">মাঝখানে</p><p style="text-align: end">শেষে</p>']);
        $this->assertStringContainsString('<p class="ta-center">মাঝখানে</p>', $post->fresh()->body_html);
        $this->assertStringContainsString('<p class="ta-right">শেষে</p>', $post->fresh()->body_html);
        $this->assertSame('<p style="text-align: center">x</p>', \App\Support\HtmlSanitizer::alignmentForEditor('<p class="ta-center">x</p>'));
    }

    public function test_writer_sends_it_back_with_a_note_and_the_desk_sees_exactly_what_changed(): void
    {
        // Owner (D171): "no option for the writer to write a comment when sending back; no change history".
        $this->actingAs($this->writer)->post('/submit/', $this->poem())->assertRedirect();
        $post = \App\Models\Post::latest('id')->first();
        $editor = User::factory()->create(['role' => Role::Admin]);
        \App\Support\Review::requestChanges($post->fresh(), $editor, 'শেষ লাইনটা আর একবার ভাবুন।');

        $this->actingAs($this->writer)->get('/submit/'.$post->id.'/')->assertOk()
            ->assertSee('সম্পাদককে কিছু বলবেন?')->assertSee('name="writer_note"', false)->assertSee('আবার পাঠান');
        $this->post('/submit/'.$post->id.'/', $this->poem(['body' => "আকাশ ভরা মেঘ\nভিজে গেছে মাঠ\n\nতোমার কথা মনে পড়ে\nসারা বিকেল একা", 'writer_note' => 'শেষ লাইনটা বদলেছি।']))->assertRedirect();

        $kinds = $post->versions()->pluck('kind')->all();
        $this->assertSame(['submitted', 'sent_back', 'resubmitted'], $kinds);
        $last = $post->versions()->reorder('id', 'desc')->first();
        $this->assertSame('শেষ লাইনটা বদলেছি।', $last->note);
        $this->assertSame('শেষ লাইনটা আর একবার ভাবুন।', $last->previous()->note);

        $d = \App\Support\TextDiff::compare($last->previous()->body_html, $last->body_html);
        $this->assertStringContainsString('<del style="background:#FBD9DD;text-decoration:line-through">দুপুর</del>', $d['html']);
        $this->assertStringContainsString('<ins style="background:#D7F0DC;text-decoration:none">বিকেল</ins>', $d['html']);
        $this->assertSame([2, 1], [$d['added'], $d['removed']], '+বিকেল একা, −দুপুর');

        // The desk: the writer's note and the changes at the top of the waiting piece, and the full history.
        $this->actingAs($editor);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $guide = view('filament.review-guide', ['getRecord' => fn () => $post->fresh()])->render();
        $this->assertStringContainsString('লেখক বদল করে আবার পাঠিয়েছেন', $guide);
        $this->assertStringContainsString('শেষ লাইনটা বদলেছি।', $guide);
        $this->assertStringContainsString('বিকেল', $guide);
        $history = view('filament.post-history', ['post' => $post->fresh()])->render();
        $this->assertStringContainsString('সম্পাদক বদল চাইলেন', $history);
        $this->assertStringContainsString('লেখক আবার পাঠালেন', $history);
        \Livewire\Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->getRouteKey()])->assertActionVisible('history');

        $this->assertStringNotContainsString('<script', \App\Support\TextDiff::compare('<p>ক</p>', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>')['html'], 'Escaped');
    }

    public function test_an_editors_own_changes_in_the_desk_are_kept_with_the_text_before(): void
    {
        // Owner (D171): "I made some changes and saved; it is not showing any change history."
        $this->actingAs($this->writer)->post('/submit/', $this->poem())->assertRedirect();
        $post = \App\Models\Post::latest('id')->first();
        \App\Models\PostVersion::query()->delete();   // like a piece sent in before versions were kept
        $editor = User::factory()->create(['role' => Role::Editor, 'name' => 'Sompadok']);
        $this->actingAs($editor);

        $post->fresh()->forceFill(['body_html' => $post->body_html])->save();   // saved without changing a word
        $this->assertSame(0, $post->versions()->count());

        $post->fresh()->forceFill(['body_html' => str_replace('দুপুর', 'সন্ধ্যা', $post->body_html)])->save();
        $this->assertSame(['before', 'desk_edit'], $post->versions()->pluck('kind')->all());
        $this->assertSame($editor->id, $post->versions()->reorder('id', 'desc')->first()->user_id);
        $history = view('filament.post-history', ['post' => $post->fresh()])->render();
        $this->assertStringContainsString('ডেস্কে বদল করা হল', $history);
        $this->assertStringContainsString('Sompadok', $history);
        $this->assertStringContainsString('<ins style="background:#D7F0DC;text-decoration:none">সন্ধ্যা</ins>', $history);

        auth()->logout();   // imports and scheduled jobs (no person) never add versions
        $post->fresh()->forceFill(['title' => 'অন্য নাম'])->save();
        $this->assertSame(2, $post->versions()->count());
    }
}
