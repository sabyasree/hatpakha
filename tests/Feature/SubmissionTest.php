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
        $this->actingAs($this->writer)->get('/submit/')->assertOk()->assertSee('জমা দিন');
        $this->post('/submit/', $this->poem())->assertRedirect(url('/account').'/');

        $post = Post::where('title', 'বৃষ্টির দিন')->firstOrFail();
        $this->assertSame('pending', $post->status);
        $this->assertSame('poem', $post->format);
        $this->assertNotNull($post->originality_confirmed_at);
        $this->assertMatchesRegularExpression('/আকাশ ভরা মেঘ<br>\s*ভিজে গেছে মাঠ/u', $post->body_html);
        $this->assertSame(2, substr_count($post->body_html, '<p>'), 'Blank line = new stanza');
        $this->assertSame(Role::Writer, $this->writer->fresh()->role);
        $this->assertSame($this->writer->id, $post->authors->first()->user_id);
        $this->get('/account/')->assertSee('বৃষ্টির দিন')->assertSee('জমা পড়েছে');

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
        $res = $this->postJson('/submit/autosave/', ['title' => 'অর্ধেক লেখা', 'body' => "প্রথম লাইন\nদ্বিতীয় লাইন", 'section_id' => $this->poetry->id])->assertOk()->json();
        $this->assertTrue($res['saved']);
        $this->assertSame('draft', Post::find($res['id'])->status);

        $this->postJson('/submit/'.$res['id'].'/autosave/', ['title' => 'পুরো লেখা', 'body' => 'আরও লেখা'])->assertOk();
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
}
