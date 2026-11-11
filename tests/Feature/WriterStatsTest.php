<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** "আমার পাঠক" (owner, 2026-11-12): a writer's own numbers and share card. Totals only. */
class WriterStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_sees_reads_likes_saves_and_a_share_card_but_not_places(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $author = Author::create(['slug' => 'rina', 'display_name' => 'রিনা দাস', 'user_id' => $user->id]);
        $post = $this->makePost(['title' => 'নদীর গান'], $author);
        $other = $this->makePost(['title' => 'অন্যের লেখা']);
        $ua = ['User-Agent' => 'Mozilla/5.0 (X11; Linux) Firefox/140', 'CF-IPCountry' => 'BD', 'CF-IPCity' => 'Dhaka'];
        $this->post('/v/'.$post->id, ['s' => 'social'], $ua)->assertNoContent();
        $this->assertSame(1, (int) DB::table('post_view_places')->where(['post_id' => $post->id, 'place' => 'Dhaka|BD'])->value('views'));
        DB::table('post_views')->insert(['post_id' => $other->id, 'day' => now()->toDateString(), 'views' => 99]);
        DB::table('saved_posts')->insert(['user_id' => User::factory()->create()->id, 'post_id' => $post->id, 'created_at' => now()]);

        $this->actingAs($user)->get('/account/stats/')->assertOk()
            ->assertSee('আমার পাঠক')->assertSee('নদীর গান')->assertDontSee('অন্যের লেখা')
            ->assertDontSee('ঢাকা, বাংলাদেশ')->assertDontSee('কীভাবে পৌঁছেছেন')->assertSee('data-share-card', false)->assertSee('hp-share-card.js', false);
        $this->actingAs($user)->get('/account/')->assertSee('/account/stats/', false);
    }

    public function test_bad_place_headers_are_ignored_and_page_needs_login(): void
    {
        $post = $this->makePost();
        $this->post('/v/'.$post->id, [], ['User-Agent' => 'Mozilla/5.0 (X11; Linux) Firefox/140', 'CF-IPCountry' => 'XX', 'CF-IPCity' => '<script>'])->assertNoContent();
        $this->assertSame(0, DB::table('post_view_places')->count());
        $this->get('/account/stats/')->assertRedirect();
    }

    public function test_empty_state_for_a_writer_with_nothing_published(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get('/account/stats/')->assertOk()->assertSee('এখনও আপনার কোনো লেখা প্রকাশিত হয়নি');
    }
}
