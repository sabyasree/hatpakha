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

    /** Owner (2026-11-14): "many people started submitting. How will you decide which is for the সংখ্যা and which are normal?" */
    public function test_the_writer_says_on_the_form_whether_a_piece_is_for_the_return_issue(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-11-20 10:00', 'Asia/Kolkata'));
        $this->actingAs($this->writer)->get('/submit/')->assertOk()->assertSee('এই লেখাটি “ফিরে আসা সংখ্যা”-র জন্য');
        $this->post('/submit/', $this->poem(['for_issue' => '1', 'action' => 'draft']));
        $post = Post::where('submitted_by', $this->writer->id)->latest('id')->firstOrFail();
        $this->assertSame('phire-asha', \App\Models\Issue::findOrFail($post->issue_id)->slug);
        $this->get('/submit/'.$post->id.'/')->assertOk()->assertSee('name="for_issue" value="1" checked', false);
        $this->post('/submit/'.$post->id.'/', $this->poem(['for_issue' => '0']));
        $this->assertNull($post->fresh()->issue_id, 'Unticked: an ordinary piece');
        $this->assertSame('pending', $post->fresh()->status);

        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-12-08 10:00', 'Asia/Kolkata'));
        $this->get('/submit/')->assertOk()->assertDontSee('ফিরে আসা সংখ্যা');
    }

    /** D205 (owner): change, add or withdraw until published; a writer e-mailed "sent half by mistake, sending the whole again". */
    public function test_a_sent_piece_can_be_taken_back_changed_and_sent_again_until_it_is_published(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAs($this->writer)->post('/submit/', $this->poem())->assertSessionHas('status', fn ($m) => str_contains($m, 'নতুন করে পাঠাবেন না'));
        $post = Post::where('submitted_by', $this->writer->id)->firstOrFail();
        $task = \App\Models\EditorTask::propose('rewrite', 'কাজ', [], $post);

        // Everywhere the writer looks, the way to change it is in sight.
        $this->get('/account/')->assertOk()->assertSee('বদলান বা তুলে নিন');
        $this->get('/account/writings/')->assertOk()->assertSee('বদলান');
        $this->get('/submit/')->assertOk()->assertSee('আগে পাঠানো লেখাটিই বদলাতে')->assertSee('/submit/'.$post->id.'/', false);
        $this->get('/submit/'.$post->id.'/')->assertOk()->assertSee('ফিরিয়ে এনে বদলান')->assertDontSee('এখন আর বদলানো যায় না');
        $this->post('/submit/'.$post->id.'/', $this->poem(['title' => 'না']))->assertForbidden();

        $this->post('/submit/'.$post->id.'/withdraw/')->assertRedirect(url('/submit/'.$post->id).'/');
        $this->assertSame('draft', $post->fresh()->status);
        $this->assertSame('dismissed', $task->fresh()->status);
        $this->assertSame('withdrawn', \App\Models\PostVersion::where('post_id', $post->id)->latest('id')->value('kind'));
        $this->get('/submit/'.$post->id.'/')->assertOk()->assertSee('জমা দিন')->assertSee('এই খসড়াটি আর চাই না');
        $this->post('/submit/'.$post->id.'/', $this->poem(['body' => "আকাশ ভরা মেঘ\nভিজে গেছে মাঠ\n\nতোমার কথা মনে পড়ে\nসারা দুপুর\n\nবাকি অর্ধেকটা এই যে"]));
        $this->assertSame('pending', $post->fresh()->status);
        $this->assertStringContainsString('বাকি অর্ধেকটা', $post->fresh()->body_html);
        $this->assertSame(1, Post::where('submitted_by', $this->writer->id)->count(), 'Still one piece, not two');

        // Selected with a date: can still be taken back; the date goes.
        $post->forceFill(['status' => 'scheduled', 'published_at' => now()->addDays(3)])->saveQuietly();
        $this->get('/submit/'.$post->id.'/')->assertSee('প্রকাশের তারিখ বাতিল হবে');
        $this->post('/submit/'.$post->id.'/withdraw/');
        $this->assertSame(['draft', null], [$post->fresh()->status, $post->fresh()->published_at]);

        // Published: not from here; ask the editor.
        $post->forceFill(['status' => 'published', 'published_at' => now()->subHour()])->saveQuietly();
        $this->post('/submit/'.$post->id.'/withdraw/')->assertForbidden();
        $this->post('/submit/'.$post->id.'/delete/')->assertForbidden();
        $this->get('/submit/'.$post->id.'/')->assertOk()->assertSee('প্রকাশিত লেখা এখান থেকে বদলানো বা তুলে নেওয়া যায় না')->assertSee('editor@hatpakha.com')->assertDontSee('ফিরিয়ে এনে বদলান');

        // A draft the writer no longer wants; and nobody else's piece.
        $draft = Post::find($this->actingAs($this->writer)->post('/submit/', $this->poem(['action' => 'draft', 'title' => 'খসড়া']))->getSession()->all() ? Post::where('title', 'খসড়া')->value('id') : 0);
        $other = User::factory()->create();
        $this->actingAs($other)->post('/submit/'.$draft->id.'/delete/')->assertNotFound();
        $this->actingAs($other)->post('/submit/'.$draft->id.'/withdraw/')->assertNotFound();
        $this->actingAs($this->writer)->post('/submit/'.$draft->id.'/delete/')->assertRedirect(url('/account').'/');
        $this->assertSoftDeleted('posts', ['id' => $draft->id]);
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

    /** D193 (owner: "if anyone wants to write a serial, how will they start?"). */
    public function test_a_writer_proposes_a_serial_and_later_sends_its_next_episode(): void
    {
        \Illuminate\Support\Facades\RateLimiter::clear('submit:'.$this->writer->id);
        $this->actingAs($this->writer)->get('/submit/')->assertOk()->assertSee('এটি কি ধারাবাহিকের পর্ব?')->assertSee('না, এটি একটাই লেখা')->assertSee('হ্যাঁ, নতুন ধারাবাহিক শুরু করছি')->assertSee('এরপর কী হবে');
        // D206: the ধারাবাহিক section sent as a single piece is asked about, and no half-made draft is left behind.
        $serialSection = Section::firstOrCreate(['slug' => 'dharabahik'], ['name_bn' => 'ধারাবাহিক', 'name_en' => 'Serials', 'format' => 'prose', 'sort' => 25]);
        $this->post('/submit/', $this->poem(['section_id' => $serialSection->id]))->assertSessionHasErrors(['serial' => 'আপনি “ধারাবাহিক” বিভাগ বেছেছেন। “এটি কি ধারাবাহিকের পর্ব?” প্রশ্নে “হ্যাঁ, নতুন ধারাবাহিক শুরু করছি” বেছে তিনটি কথা জানান। একটাই লেখা হলে বিভাগ বদলে গল্প, কবিতা বা অন্য কিছু বেছে নিন।']);
        $this->assertSame(0, Post::where('submitted_by', $this->writer->id)->count());
        $this->post('/submit/', $this->poem(['section_id' => $serialSection->id, 'action' => 'draft']))->assertSessionHasNoErrors();
        Post::where('submitted_by', $this->writer->id)->forceDelete();
        // The plan is needed with a new serial.
        $this->post('/submit/', $this->poem(['serial' => 'new', 'serial_title' => 'নদীর ওপারে']))->assertSessionHasErrors(['serial_plan', 'serial_count']);

        $plan = 'এক স্কুলমাস্টার তিরিশ বছর পরে নিজের গ্রামে ফেরেন। পুরনো বন্ধু, পুরনো ঝগড়া, আর একটা না-বলা কথা। শেষে তিনি থেকে যাবেন কি না, সেটাই গল্প।';
        $this->post('/submit/', $this->poem(['title' => 'প্রথম পর্ব: ফেরা', 'serial' => 'new', 'serial_title' => 'নদীর ওপারে', 'serial_plan' => $plan, 'serial_count' => 8]))->assertRedirect()
            ->assertSessionHas('status', fn ($m) => str_starts_with($m, 'নতুন ধারাবাহিক “নদীর ওপারে”-এর প্রথম পর্ব'));
        $this->get('/submit/')->assertSee('প্রথম পর্ব সম্পাদকের কাছে আছে')->assertDontSee('হ্যাঁ, নতুন ধারাবাহিক শুরু করছি');
        $this->post('/submit/', $this->poem(['section_id' => $serialSection->id, 'title' => 'দ্বিতীয়']))->assertSessionHasErrors('serial');
        $serial = \App\Models\Serial::where('user_id', $this->writer->id)->firstOrFail();
        $first = Post::where('serial_id', $serial->id)->firstOrFail();
        $this->assertSame(['proposed', 'নদীর ওপারে', 8, 1, 'pending'], [$serial->status, $serial->title, $serial->planned_count, $first->episode_no, $first->status]);
        $this->assertFalse($serial->isPublic(), 'Nothing is public until the editor takes the first episode');

        // One proposal at a time.
        $this->post('/submit/', $this->poem(['title' => 'আর একটা', 'serial' => 'new', 'serial_title' => 'অন্য গল্প', 'serial_plan' => $plan, 'serial_count' => 5]))->assertSessionHasErrors('serial');

        // The editor approves the first episode: the serial starts.
        \App\Support\Review::approve($first->fresh(), User::factory()->create(['role' => Role::Admin]));
        $serial->refresh();
        $this->assertSame('active', $serial->status);
        $this->assertTrue($serial->isPublic());
        $this->get($serial->url())->assertOk()->assertSee('নদীর ওপারে');

        // The next piece is picked as the serial's next episode.
        $this->get('/submit/')->assertSee('হ্যাঁ, আমার ধারাবাহিক “নদীর ওপারে”-এর পরের পর্ব (পর্ব ২)')->assertSee('এই পর্বের নাম');
        $this->post('/submit/', $this->poem(['title' => 'দ্বিতীয় পর্ব', 'serial' => (string) $serial->id]))->assertRedirect();
        $second = Post::where('serial_id', $serial->id)->where('episode_no', 2)->firstOrFail();
        $this->assertSame('pending', $second->status);

        // Never someone else's serial, never the desk's; and the AI never writes a writer's serial.
        $other = User::factory()->create(['role' => Role::Writer, 'email_verified_at' => now()]);
        $this->actingAs($other)->post('/submit/', $this->poem(['title' => 'ঢুকে পড়া', 'serial' => (string) $serial->id]));
        $this->assertNull(Post::where('title', 'ঢুকে পড়া')->firstOrFail()->serial_id);
        $second->forceFill(['status' => 'published', 'published_at' => now()])->saveQuietly();
        $this->assertSame(0, \App\Ai\SerialWriter::keepAhead(), 'The writer writes it, not the AI');
    }
}
