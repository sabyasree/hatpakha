<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use App\Support\ContentAudit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Phase 2 content audit: suggestions only; an admin applies them; effects are real and reversible. */
class ContentAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_rules_flag_the_right_posts(): void
    {
        $spam = $this->makePost(['slug' => 's1', 'title' => 'Flight Coupon Code deals', 'body_html' => '<p>'.str_repeat('travel ', 100).'</p>']);
        $book = $this->makePost(['slug' => 's2', 'title' => 'Feluda', 'body_html' => '<p>Download or read online Feluda by Satyajit Ray</p>']);
        $poem = $this->makePost(['slug' => 's3', 'title' => 'Bargain', 'format' => 'prose', 'body_html' => '<p>The women<br>at traffic lights<br>in evenings<br>scrutinizing</p>']);
        $thin = $this->makePost(['slug' => 's4', 'title' => 'Vote', 'format' => 'prose', 'body_html' => '<p>Vote started. What do you think about your new MP and the future of our area today?</p>']);
        $ok = $this->makePost(['slug' => 's5', 'title' => 'গল্প', 'body_html' => '<p>'.str_repeat('একটি সুন্দর বাক্য। ', 60).'</p>']);

        $this->assertSame('remove', ContentAudit::assess($spam)['label']);
        $this->assertSame('remove', ContentAudit::assess($book)['label']);
        $this->assertSame('keep', ContentAudit::assess($poem)['label'], 'Short lines are verse, not thin prose.');
        $this->assertSame('noindex', ContentAudit::assess($thin)['label']);
        $this->assertSame('keep', ContentAudit::assess($ok)['label']);
    }

    public function test_same_title_is_only_a_duplicate_when_the_text_matches(): void
    {
        $a = $this->makePost(['slug' => 'e1', 'title' => 'Editorial', 'body_html' => '<p>'.str_repeat('Navratri has begun. ', 30).'</p>', 'published_at' => now()->subDays(3)]);
        $b = $this->makePost(['slug' => 'e2', 'title' => 'Editorial', 'body_html' => '<p>'.str_repeat('Work was hectic today. ', 30).'</p>', 'published_at' => now()->subDays(2)]);
        $c = $this->makePost(['slug' => 'e3', 'title' => 'Editorial', 'body_html' => '<p>'.str_repeat('Navratri has begun. ', 30).'</p>', 'published_at' => now()->subDay()]);
        $author = Author::create(['slug' => 'ed', 'display_name' => 'Ed']);
        foreach ([$a, $b, $c] as $p) {
            $p->authors()->sync([$author->id]);
        }

        $dups = ContentAudit::duplicates(Post::with('authors')->get());
        $this->assertSame([$c->id => $a->id], $dups);
    }

    public function test_applying_suggestions_hides_retires_or_redirects_and_is_logged(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $keep = $this->makePost(['slug' => 'target']);
        $n = $this->makePost(['slug' => 'hide-me']);
        $r = $this->makePost(['slug' => 'gone']);
        $m = $this->makePost(['slug' => 'dupe']);
        $n->forceFill(['audit_label' => 'noindex'])->saveQuietly();
        $r->forceFill(['audit_label' => 'remove'])->saveQuietly();
        $m->forceFill(['audit_label' => 'merge', 'audit_merge_into_id' => $keep->id])->saveQuietly();

        foreach ([$n, $r, $m] as $p) {
            ContentAudit::apply($p->fresh(), $admin);
        }

        $this->get('/hide-me/')->assertOk()->assertSee('noindex, follow', false);
        $this->get('/gone/')->assertStatus(410);
        $this->get('/dupe/')->assertRedirect(url('/target').'/');
        $this->assertSame('applied', $r->fresh()->audit_decision);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.audit_applied']);
    }

    public function test_decided_posts_are_never_re_suggested(): void
    {
        $p = $this->makePost(['slug' => 'decided', 'title' => 'Coupon Code']);
        $p->forceFill(['audit_label' => 'remove', 'audit_decision' => 'dismissed'])->saveQuietly();

        $this->artisan('hatpakha:audit', ['--connection' => 'does-not-exist'])->assertSuccessful();
        $this->assertSame('dismissed', $p->fresh()->audit_decision);
        $this->assertNotSame('applied', $p->fresh()->audit_decision);
    }

    public function test_admin_posts_table_renders_with_audit_tools(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $p = $this->makePost(['slug' => 'listed', 'title' => 'তালিকার লেখা']);
        $p->forceFill(['audit_label' => 'noindex', 'audit_reasons' => ['খুব ছোট গদ্য']])->saveQuietly();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        Livewire::test(ListPosts::class)->assertOk()->assertSee('তালিকার লেখা')
            ->assertTableActionVisible('applyAudit', $p)
            ->callTableAction('applyAudit', $p);
        $this->assertTrue($p->fresh()->noindex);
    }
}
