<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\AiTeam;
use App\Filament\Pages\Marketing;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Newsletters\Pages\ListNewsletters;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Author;
use App\Models\Newsletter;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SocialPost;
use App\Models\TeamRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Owner, 2026-11-09: delete and bulk delete everywhere in the desk, admins only, never breaking an old URL. */
class DeleteActionsTest extends TestCase
{
    use RefreshDatabase;

    private function as(Role $role): User
    {
        $u = User::factory()->create(['role' => $role]);
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        return $u;
    }

    public function test_a_deleted_published_piece_answers_410_and_comes_back_when_restored(): void
    {
        $this->as(Role::Admin);
        $post = $this->makePost(['status' => 'published', 'slug' => 'megh', 'published_at' => now()->subDay()]);
        $this->get('/megh/')->assertOk();

        Livewire::test(ListPosts::class)->set('activeTab', 'all')->callTableAction('delete', $post)->assertHasNoTableActionErrors();
        $this->assertSoftDeleted($post);
        $this->get('/megh/')->assertStatus(410);

        Livewire::test(ListPosts::class)->set('activeTab', 'all')->callTableAction('restoreTrashed', Post::withTrashed()->find($post->id));
        $this->assertNotSoftDeleted($post);
        $this->assertSame(0, Redirect::where('from_path', '/megh/')->count());
        $this->get('/megh/')->assertOk();
    }

    public function test_a_merge_redirect_is_never_replaced_by_the_trash(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'old']);
        Redirect::create(['from_path' => '/old/', 'to_path' => '/new/', 'status_code' => 301]);
        $post->delete();
        $post->restore();
        $this->assertSame(301, Redirect::where('from_path', '/old/')->value('status_code'));
    }

    public function test_bulk_delete_and_delete_forever_for_pieces(): void
    {
        $this->as(Role::Admin);
        $a = $this->makePost(['status' => 'published']);
        $b = $this->makePost(['status' => 'published']);
        Livewire::test(ListPosts::class)->set('activeTab', 'all')->callTableBulkAction('deleteSelected', [$a, $b]);
        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);

        Livewire::test(ListPosts::class)->set('activeTab', 'all')->filterTable('trashed', true)->callTableBulkAction('forceDeleteSelected', Post::withTrashed()->whereKey([$a->id, $b->id])->get());
        $this->assertSame(0, Post::withTrashed()->count());
    }

    public function test_the_piece_edit_page_has_a_visible_delete_button(): void
    {
        $this->as(Role::Admin);
        $post = $this->makePost(['status' => 'draft']);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionVisible('delete')
            ->callAction('delete')->assertRedirect();
        $this->assertSoftDeleted($post);
    }

    public function test_editors_cannot_delete(): void
    {
        $this->as(Role::Editor);
        $post = $this->makePost(['status' => 'draft']);
        Livewire::test(ListPosts::class)->set('activeTab', 'all')->assertTableActionHidden('delete', $post);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionHidden('delete');
    }

    public function test_bulk_delete_skips_writers_who_still_have_pieces(): void
    {
        $this->as(Role::Admin);
        $busy = Author::create(['slug' => 'busy', 'display_name' => 'Busy']);
        $this->makePost([], $busy);
        $idle = Author::create(['slug' => 'idle', 'display_name' => 'Idle']);

        Livewire::test(ListAuthors::class)->callTableBulkAction('deleteSelected', [$busy, $idle]);
        $this->assertModelExists($busy);
        $this->assertModelMissing($idle);
    }

    public function test_a_letter_being_sent_cannot_be_deleted_but_a_draft_can(): void
    {
        $this->as(Role::Admin);
        $sending = Newsletter::create(['subject' => 'a', 'audience' => 'readers', 'status' => 'sending']);
        $draft = Newsletter::create(['subject' => 'b', 'audience' => 'readers', 'status' => 'draft']);
        Livewire::test(ListNewsletters::class)->callTableAction('delete', $sending)->callTableAction('delete', $draft);
        $this->assertModelExists($sending);
        $this->assertModelMissing($draft);
    }

    public function test_marketing_plans_posts_and_team_jobs_can_be_deleted(): void
    {
        $this->as(Role::Admin);
        $plan = SocialPost::create(['kind' => 'weekly', 'title' => 'সপ্তাহ', 'texts' => ['plan' => ['সোম: কবিতা']]]);
        $done = SocialPost::create(['kind' => 'custom', 'title' => 'x', 'status' => 'dismissed', 'texts' => ['facebook' => 'f']]);
        $ready = SocialPost::create(['kind' => 'custom', 'title' => 'y', 'texts' => ['facebook' => 'f']]);
        Livewire::test(Marketing::class)->call('deleteSocial', $plan->id)->call('clearDone');
        $this->assertModelMissing($plan);
        $this->assertModelMissing($done);
        $this->assertModelExists($ready);

        $job = TeamRequest::create(['agent' => 'scout', 'job' => 'trends', 'status' => 'done']);
        $running = TeamRequest::create(['agent' => 'scout', 'job' => 'trends', 'status' => 'running']);
        Livewire::test(AiTeam::class)->call('deleteRequest', $job->id)->call('deleteRequest', $running->id);
        $this->assertModelMissing($job);
        $this->assertModelExists($running);
    }

    public function test_marketing_delete_needs_an_admin(): void
    {
        $this->as(Role::Editor);
        $plan = SocialPost::create(['kind' => 'weekly', 'title' => 'সপ্তাহ', 'texts' => ['plan' => []]]);
        Livewire::test(Marketing::class)->call('deleteSocial', $plan->id)->assertDontSee('এই পরিকল্পনাটি মুছে ফেলবেন?');
        $this->assertModelExists($plan);
    }
}
