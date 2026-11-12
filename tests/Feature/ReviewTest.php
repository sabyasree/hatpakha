<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use App\Notifications\SubmissionDecision;
use App\Support\Review;
use App\Support\Transliterate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Phase 3c review loop: a person decides; the writer is told; URLs follow D14. */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function submission(string $title = 'বৃষ্টির দিন'): array
    {
        $writer = User::factory()->create(['role' => Role::Writer]);
        $post = $this->makePost(['slug' => 'draft-abc'.mt_rand(), 'title' => $title, 'status' => 'pending', 'published_at' => null, 'submitted_at' => now()]);
        $post->forceFill(['submitted_by' => $writer->id])->save();
        $post->authors()->sync([Author::create(['slug' => 'w'.$writer->id, 'display_name' => 'W', 'user_id' => $writer->id])->id]);

        return [$post, $writer, User::factory()->create(['role' => Role::Editor])];
    }

    /** D161: every letter to a writer is kept exactly as sent, and the desk can read it. */
    public function test_letters_to_writers_are_kept_and_readable_in_the_desk(): void
    {
        config(['app.url' => 'https://hatpakha.com', 'mail.default' => 'array']);
        [$post, $writer, $editor] = $this->submission('তিন দেশের গল্প');
        $writer->forceFill(['email' => 'lekhok@example.test'])->save();
        Review::requestChanges($post, $editor, 'শেষ লাইনে আর একটু থামা যায়?');

        $letter = \App\Models\SentLetter::sole();
        $this->assertSame('decision_changes', $letter->kind);
        $this->assertSame($post->id, $letter->post_id);
        $this->assertSame($writer->id, $letter->user_id);
        $this->assertSame('lekhok@example.test', $letter->to_email);
        $this->assertStringContainsString('শেষ লাইনে আর একটু থামা যায়?', $letter->html);

        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Resources\SentLetters\Pages\ListSentLetters::class)->assertOk()
            ->assertCanSeeTableRecords([$letter])->assertSee('lekhok@example.test');
        Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $post->id])->assertSee('চিঠিগুলো পড়ুন');

        // A reader's or a staff password mail is not a letter to a writer.
        \Illuminate\Support\Facades\Mail::raw('hello', fn ($m) => $m->to('lekhok@example.test')->subject('hi'));
        $this->assertSame(1, \App\Models\SentLetter::count());
    }

    public function test_bengali_titles_get_readable_latin_urls(): void
    {
        $this->assertSame('brishtir-din', Transliterate::slug('বৃষ্টির দিন'));
        $this->assertSame('dokkhiner-chhora', Transliterate::slug('দক্ষিণের ছড়া'));
        $this->assertSame('akash-bhora-swapno', Transliterate::slug('আকাশ ভরা স্বপ্ন'));
    }

    public function test_approve_publishes_at_a_readable_url_and_tells_the_writer(): void
    {
        Notification::fake();
        [$post, $writer, $editor] = $this->submission();
        $this->makePost(['slug' => 'brishtir-din', 'title' => 'অন্য']); // slug already taken

        Review::approve($post, $editor);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertSame('brishtir-din-2', $post->slug);
        $this->get('/brishtir-din-2/')->assertOk()->assertSee('বৃষ্টির দিন');
        Notification::assertSentTo($writer, SubmissionDecision::class, fn ($n) => $n->decision === 'approved');
        $this->assertDatabaseHas('audit_logs', ['action' => 'submission.approved']);
    }

    public function test_scheduled_posts_go_live_on_their_date_without_a_cron(): void
    {
        Notification::fake();
        [$post, , $editor] = $this->submission('শরতের মেঘ');
        Review::approve($post, $editor, now()->addDay());

        $this->assertSame('scheduled', $post->fresh()->status);
        $this->get('/'.$post->fresh()->slug.'/')->assertNotFound();
        $this->travel(2)->days();
        $this->get('/'.$post->fresh()->slug.'/')->assertOk();
    }

    public function test_changes_and_rejection_send_the_editors_words(): void
    {
        Notification::fake();
        [$a, $writerA, $editor] = $this->submission('এক');
        [$b, $writerB] = $this->submission('দুই');

        Review::requestChanges($a, $editor, 'শেষ স্তবকটা আর একটু ভাবুন।');
        Review::reject($b, $editor, Review::REJECT_TEMPLATES['এবার নয়']);

        $this->assertSame('changes', $a->fresh()->status);
        $this->assertSame('rejected', $b->fresh()->status);
        Notification::assertSentTo($writerA, SubmissionDecision::class, fn ($n) => str_contains($n->toMail($writerA)->render(), 'শেষ স্তবকটা'));
        Notification::assertSentTo($writerB, SubmissionDecision::class, fn ($n) => $n->decision === 'rejected');
        $this->actingAs($writerA)->get('/account/')->assertSee('শেষ স্তবকটা আর একটু ভাবুন।');
    }

    public function test_review_queue_tab_and_approve_button_work_in_desk(): void
    {
        Notification::fake();
        [$post, , $editor] = $this->submission('কোনজন তুমি');
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        Livewire::test(ListPosts::class)->assertOk()->assertSee('কোনজন তুমি')
            ->callTableAction('approve', $post, data: ['at' => now()->toDateTimeString(), 'slug' => 'konojon-tumi', 'note' => null])
            ->assertHasNoTableActionErrors();
        $this->assertSame('konojon-tumi', $post->fresh()->slug);
        $this->assertSame('published', $post->fresh()->status);
    }

    public function test_old_wordpress_backlog_does_not_bury_new_submissions(): void
    {
        [$new, , $editor] = $this->submission('নতুন জমা');
        $old = $this->makePost(['slug' => 'old-pending', 'title' => 'পুরনো জমা লেখা', 'status' => 'pending', 'published_at' => null]);
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        Livewire::test(ListPosts::class)->assertSee('নতুন জমা')->assertDontSee('পুরনো জমা লেখা');
        Livewire::test(ListPosts::class)->set('activeTab', 'backlog')->assertSee('পুরনো জমা লেখা')->assertDontSee('নতুন জমা');
    }
}
