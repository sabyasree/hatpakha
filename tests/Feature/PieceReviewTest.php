<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\PieceReviews\Pages\ListPieceReviews;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\AiReview;
use App\Models\Author;
use App\Models\PieceReview;
use App\Models\User;
use App\Notifications\TemplateMail;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** D168: human reviewers (যাচাইকারী). Owner: assign a piece, they read it and give feedback, then see the AI's; the desk sees all. */
class PieceReviewTest extends TestCase
{
    use RefreshDatabase;

    private function submission(): \App\Models\Post
    {
        $author = Author::create(['slug' => 'secret-writer', 'display_name' => 'Gopon Lekhok']);
        $post = $this->makePost(['slug' => 'pending-one', 'title' => 'নদীর গল্প', 'status' => 'pending', 'published_at' => null,
            'body_html' => '<p>নদীর ধারে একটা গ্রাম।</p>'], $author);
        AiReview::create(['post_id' => $post->id, 'provider' => 'anthropic', 'model' => 'm', 'status' => 'done', 'score' => 7,
            'report' => ['quality_score' => 7, 'decision' => 'changes', 'summary' => 'এআই-এর সারাংশ', 'strengths' => ['ভালো ছবি'],
                'suggestions' => ['শেষটা ছোট করুন'], 'note_to_writer' => 'লেখকের জন্য গোপন চিঠি']]);

        return $post;
    }

    public function test_admin_marks_a_reviewer_and_assigns_a_piece_by_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $reviewer = User::factory()->create(['role' => Role::Reader, 'name' => 'Mita', 'is_reviewer' => true]);
        $notReviewer = User::factory()->create(['role' => Role::Reader]);
        $post = $this->submission();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('sendForReview', ['reviewers' => [$notReviewer->id], 'note' => 'x'])
            ->assertHasActionErrors(['reviewers.0']);   // only people marked as reviewers can be picked
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('sendForReview', ['reviewers' => [$reviewer->id], 'note' => 'শেষটা দেখবেন', 'due' => now()->addWeek()->toDateString()])
            ->assertHasNoActionErrors();
        $this->assertSame(0, \App\Support\PieceReviews::assign($post, [$notReviewer->id], null, null, $admin), 'Never someone who is not a reviewer');

        $r = PieceReview::sole();
        $this->assertSame([$reviewer->id, 'assigned', 'শেষটা দেখবেন'], [$r->reviewer_id, $r->status, $r->request_note], 'Only people marked as reviewers');
        Notification::assertSentTo($reviewer, TemplateMail::class, fn ($n) => $n->key === 'review_assigned' && str_ends_with((string) $n->url, '/account/review/'.$r->id.'/'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.assigned', 'subject_id' => $post->id]);

        \App\Support\PieceReviews::assign($post, [$reviewer->id], null, null, $admin);
        $this->assertSame(1, PieceReview::count(), 'Assigning twice does not ask twice');
    }

    public function test_reviewer_reads_blind_gives_feedback_first_then_sees_the_ai(): void
    {
        $reviewer = User::factory()->create(['role' => Role::Reader, 'is_reviewer' => true, 'name' => 'মিতা সেন']);
        $post = $this->submission();
        $r = PieceReview::create(['post_id' => $post->id, 'reviewer_id' => $reviewer->id, 'request_note' => 'শেষটা দেখবেন']);
        $this->actingAs($reviewer);

        $this->get('/account/reading/')->assertSee('যাচাইয়ের জন্য ১টি লেখা আপনার অপেক্ষায়');
        $this->get('/')->assertSee('যাচাইয়ের লেখা')->assertSee('১টি লেখা পড়া বাকি');   // the "আরও" sheet
        $this->get('/account/review/')->assertOk()->assertSee('নদীর গল্প')->assertSee('শেষটা দেখবেন');

        $page = $this->get('/account/review/'.$r->id.'/')->assertOk()
            ->assertSee('নদীর ধারে একটা গ্রাম।')->assertSee('মত পাঠান')
            ->assertSee('লেখকের নাম দেখানো হচ্ছে না')->assertDontSee('Gopon Lekhok')     // blind
            ->assertDontSee('এআই-এর সারাংশ');                                         // the AI only after their own view
        $this->assertNotNull($r->fresh()->opened_at);

        $this->post('/account/review/'.$r->id.'/', ['verdict' => 'changes', 'liked' => 'ছোট'])->assertSessionHasErrors('liked');
        $this->post('/account/review/'.$r->id.'/', ['verdict' => 'changes', 'rating' => 4, 'liked' => 'গ্রামের ছবিটা খুব ভালো লাগল।', 'improve' => 'শেষটা তাড়াহুড়ো।'])
            ->assertRedirect(url('/account/review/'.$r->id).'/#ai');
        $r->refresh();
        $this->assertSame(['done', 'changes', 4], [$r->status, $r->verdict, $r->rating]);

        $this->get('/account/review/'.$r->id.'/')->assertSee('এআই-এর সারাংশ')->assertSee('শেষটা ছোট করুন')->assertSee('দশে ৭')
            ->assertDontSee('লেখকের জন্য গোপন চিঠি')->assertDontSee('মত পাঠান');
        $this->post('/account/review/'.$r->id.'/after-ai/', ['after_ai' => 'এআই-এর সঙ্গে একমত।'])->assertRedirect();
        $this->assertSame('এআই-এর সঙ্গে একমত।', $r->fresh()->after_ai);
        $this->post('/account/review/'.$r->id.'/', ['verdict' => 'good', 'liked' => 'আবার বদলাতে চাই কিন্তু পারব না'])->assertRedirect();
        $this->assertSame('changes', $r->fresh()->verdict, 'Once sent, the main view stays as it was');
    }

    public function test_only_the_assigned_reviewer_sees_it_and_never_after_it_is_withdrawn(): void
    {
        $reviewer = User::factory()->create(['is_reviewer' => true]);
        $other = User::factory()->create(['is_reviewer' => true]);
        $r = PieceReview::create(['post_id' => $this->submission()->id, 'reviewer_id' => $reviewer->id]);

        $this->actingAs($other)->get('/account/review/'.$r->id.'/')->assertNotFound();
        $this->actingAs($other)->post('/account/review/'.$r->id.'/', ['verdict' => 'good', 'liked' => 'অন্যের লেখায় মত দিচ্ছি'])->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/account/review/')->assertNotFound();   // not a reviewer
        auth()->logout();
        $this->get('/account/review/'.$r->id.'/')->assertRedirect();                             // signed out: to the login page

        $r->update(['status' => 'withdrawn']);
        $this->actingAs($reviewer)->get('/account/review/'.$r->id.'/')->assertNotFound();
    }

    public function test_desk_lists_new_feedback_beside_the_ai_report(): void
    {
        $editor = User::factory()->create(['role' => Role::Editor]);
        $reviewer = User::factory()->create(['is_reviewer' => true, 'name' => 'Mita Sen']);
        $post = $this->submission();
        $r = PieceReview::create(['post_id' => $post->id, 'reviewer_id' => $reviewer->id, 'status' => 'done', 'verdict' => 'good',
            'rating' => 5, 'liked' => 'গ্রামের ছবিটা অসাধারণ।', 'submitted_at' => now()]);
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        $this->assertSame('১', \App\Filament\Resources\PieceReviews\PieceReviewResource::getNavigationBadge(), 'New feedback is counted in the menu');
        Livewire::test(ListPieceReviews::class)->assertCanSeeTableRecords([$r])->assertSee('নদীর গল্প')->assertSee('Mita Sen')
            ->mountTableAction('read', $r);
        $modal = view('filament.piece-review', ['review' => $r, 'ai' => $post->latestAiReview])->render();
        $this->assertStringContainsString('গ্রামের ছবিটা অসাধারণ।', $modal, 'The reviewer\'s words');
        $this->assertStringContainsString('এআই-এর সারাংশ', $modal, 'Beside the AI report');
        $this->assertNotNull($r->fresh()->desk_read_at, 'Opening it marks it read');
        $this->assertNull(\App\Filament\Resources\PieceReviews\PieceReviewResource::getNavigationBadge());

        $this->actingAs(User::factory()->create(['role' => Role::Writer]))->get('/desk/piece-reviews')->assertForbidden();
    }

    public function test_admin_turns_reviewer_on_in_users_and_it_is_logged(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $user = User::factory()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['is_reviewer' => true])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($user->fresh()->is_reviewer);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.reviewer_on', 'subject_id' => $user->id]);
    }
}
