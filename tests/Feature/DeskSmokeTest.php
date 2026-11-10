<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Section;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Every /desk screen renders (list, create, edit) for an admin. Catches 500s before editors do. */
class DeskSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_screen_renders(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        $post = $this->makePost(['slug' => 'smoke', 'title' => 'সম্পাদকীয়']);
        $section = Section::where('slug', 'poetry')->first() ?? Section::create(['slug' => 'editorial', 'name_bn' => 'সম্পাদকীয়', 'name_en' => 'Editorial', 'format' => 'prose', 'sort' => 5]);
        $records = [
            'Posts' => $post,
            'Sections' => $section,
            'Authors' => Author::create(['slug' => 'a', 'display_name' => 'A']),
            'Issues' => Issue::create(['slug' => '2020-10', 'title' => 'শারদীয়া', 'kind' => 'festival', 'status' => 'published']),
            'Comments' => Comment::create(['post_id' => $post->id, 'author_name' => 'পাঠক', 'body' => 'ভালো', 'status' => 'approved']),
            'Redirects' => Redirect::create(['from_path' => '/old/', 'to_path' => '/new/', 'status_code' => 301]),
            'Users' => User::factory()->create(),
            'Pages' => Page::create(['slug' => 'about', 'title' => 'কথা', 'body_html' => '<p>x</p>']),
            'AuditLogs' => \App\Models\AuditLog::record('user.created', $post),
        ];

        foreach ($records as $res => $record) {
            $singular = rtrim($res, 's');
            $ns = "App\\Filament\\Resources\\{$res}\\Pages\\";
            Livewire::test($ns."List{$res}")->assertOk();
            if (class_exists($ns."Create{$singular}")) {
                Livewire::test($ns."Create{$singular}")->assertOk();
            }
            if (class_exists($ns."Edit{$singular}")) {
                Livewire::test($ns."Edit{$singular}", ['record' => $record->getRouteKey()])->assertOk();
            }
        }
    }

    public function test_editor_can_read_and_decide_on_the_post_page(): void
    {
        $writer = User::factory()->create(['name' => 'নতুন লেখক']);
        $sub = $this->makePost(['slug' => 'draft-ab12', 'title' => 'নদীর গান', 'status' => 'pending', 'published_at' => null, 'submitted_at' => now()]);
        $sub->forceFill(['submitted_by' => $writer->id, 'originality_confirmed_at' => now()])->save();
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));

        Livewire::test(\App\Filament\Resources\Posts\Pages\EditPost::class, ['record' => $sub->getRouteKey()])->assertOk()
            ->assertSee('জমার তথ্য')->assertSee('নতুন লেখক')->assertSee('লেখক নিশ্চিত করেছেন')
            ->assertActionVisible('approve')->assertActionVisible('requestChanges')->assertActionVisible('reject')
            ->callAction('approve', ['at' => now()->toDateTimeString(), 'slug' => 'nodir-gan'])
            ->assertRedirect();
        $this->assertSame('published', $sub->fresh()->status);
        $this->assertSame('nodir-gan', $sub->fresh()->slug);
    }

    public function test_renaming_a_live_post_leaves_a_301(): void
    {
        $post = $this->makePost(['slug' => 'purono-nam', 'title' => 'ক']);
        $post->update(['slug' => 'notun-nam']);
        $this->get('/purono-nam/')->assertRedirect(\App\Support\Url::to('/notun-nam'))->assertStatus(301);

        // A pending piece getting its first real URL makes no redirect.
        $draft = $this->makePost(['slug' => 'draft-zz', 'title' => 'খ', 'status' => 'pending', 'published_at' => null]);
        $draft->update(['slug' => 'kh']);
        $this->assertDatabaseMissing('redirects', ['from_path' => '/draft-zz/']);
    }

    public function test_redirect_form_stores_paths_the_way_they_are_looked_up(): void
    {
        $this->assertSame('/2016/03/kobita/', \App\Filament\Resources\Redirects\Schemas\RedirectForm::path('https://hatpakha.com/2016/03/Kobita'));
        $this->assertSame('/কবিতা/', \App\Filament\Resources\Redirects\Schemas\RedirectForm::path('/%E0%A6%95%E0%A6%AC%E0%A6%BF%E0%A6%A4%E0%A6%BE'));
        $this->assertNull(\App\Filament\Resources\Redirects\Schemas\RedirectForm::path('  '));
    }

    public function test_dashboard_and_navigation_for_editor_and_admin(): void
    {
        $writer = User::factory()->create();
        $sub = $this->makePost(['slug' => 'waiting', 'title' => 'অপেক্ষার কবিতা', 'status' => 'pending', 'published_at' => null, 'submitted_at' => now()->subDays(2)]);
        $sub->forceFill(['submitted_by' => $writer->id])->save();
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $comment = \App\Models\Comment::create(['post_id' => $sub->id, 'author_name' => 'পাঠক', 'body' => 'খুব ভালো লাগল', 'status' => 'pending']);
        // One inbox: the submission and the comment are on the same page, with their actions.
        Livewire::test(\App\Filament\Pages\Dashboard::class)->assertOk()
            ->assertSee('অপেক্ষার কবিতা')->assertSee('দেখুন ও সিদ্ধান্ত')->assertSee('খুব ভালো লাগল')->assertDontSee('এই মাসের খরচ')
            ->call('approveComment', $comment->id);
        $this->assertSame('approved', $comment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'comment.approved']);
        $this->assertNull(\App\Filament\Resources\Posts\PostResource::getNavigationGroup(), 'লেখা is a top-level menu item');
        $this->assertFalse(\App\Filament\Resources\AuditLogs\AuditLogResource::canAccess());
        $this->assertSame('1', \App\Filament\Resources\Posts\PostResource::getNavigationBadge());
        $this->assertFalse(\App\Filament\Resources\Sections\SectionResource::canAccess(), 'Editors cannot change sections (URLs)');
        $this->assertFalse(\App\Filament\Resources\Redirects\RedirectResource::canAccess());
        $this->assertFalse(\App\Filament\Resources\Posts\PostResource::canDelete($sub), 'Editors never delete');
        $this->assertSame('লেখা', \App\Filament\Resources\Posts\PostResource::getNavigationLabel());

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Livewire::test(\App\Filament\Pages\Dashboard::class)->assertSee('এই মাসের খরচ');
        $this->assertTrue(\App\Filament\Resources\AuditLogs\AuditLogResource::canAccess());
        $this->assertTrue(\App\Filament\Resources\Sections\SectionResource::canAccess());
        $this->assertTrue(Filament::getPanel('desk')->hasProfile(), 'Everyone can manage password and 2FA');
        $this->get('/desk/profile')->assertRedirectContains('/desk/');   // 2FA must be set up first
    }
}
