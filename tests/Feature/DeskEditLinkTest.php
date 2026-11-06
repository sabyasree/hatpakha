<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D63: editors get a direct "edit in desk" link on the site; nobody else does, and such pages are never cached. */
class DeskEditLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_editors_see_the_edit_link(): void
    {
        $post = $this->makePost(['status' => 'published', 'slug' => 'kobita', 'published_at' => now()]);
        $edit = '/desk/posts/'.$post->id.'/edit';

        $this->get('/kobita/')->assertOk()->assertDontSee($edit);
        $this->actingAs(User::factory()->create(['role' => Role::Writer]))->get('/kobita/')->assertDontSee($edit);

        $res = $this->actingAs(User::factory()->create(['role' => Role::Editor]))->get('/kobita/');
        $res->assertOk()->assertSee($edit)->assertSee('ডেস্কে সম্পাদনা');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->get($post->authors->first()->url())->assertSee('/desk/authors/');
    }
}
