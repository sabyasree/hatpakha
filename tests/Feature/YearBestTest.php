<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** বর্ষসেরা, the year's best (owner, 2026-11-12): proposed in December, a magazine, news on a click. */
class YearBestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_years_best_is_proposed_fairly_read_as_a_magazine_and_announced_on_a_click(): void
    {
        $this->travelTo(now('Asia/Kolkata')->setDate(2026, 12, 1)->setTime(9, 0));
        \Illuminate\Support\Facades\Notification::fake();
        config(['mail.default' => 'smtp']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $busy = Author::create(['slug' => 'busy', 'display_name' => 'রিনা দাস', 'user_id' => $user->id]);
        foreach (range(1, 4) as $i) {   // one writer with four strong pieces: only two may go in
            $p = $this->makePost(['slug' => "busy-$i", 'title' => "রিনার লেখা $i", 'published_at' => now()->setDate(2026, $i + 1, 5)], $busy);
            $p->forceFill(['likes_count' => 50])->saveQuietly();
        }
        foreach (range(1, 12) as $i) {
            $this->makePost(['slug' => "p-$i", 'title' => "লেখা $i", 'published_at' => now()->setDate(2026, ($i % 11) + 1, 10)]);
        }
        $desk = $this->makePost(['slug' => 'desk', 'title' => 'ডেস্কের লেখা', 'published_at' => now()->setDate(2026, 6, 1)]);
        $desk->forceFill(['ai_drafted_at' => now(), 'likes_count' => 999])->saveQuietly();
        DB::table('post_views')->insert(['post_id' => $desk->id, 'day' => '2026-06-02', 'views' => 9999]);

        $c = \App\Ai\Archivist::anthology();   // AI off: chosen by the readers' scores alone
        $this->assertNotNull($c);
        $this->assertSame('বর্ষসেরা ২০২৬', $c->title);
        $this->assertSame('proposed', $c->status, 'Waits for the owner');
        $this->assertSame([12, 1], $c->months);
        $ids = array_map('intval', $c->post_ids);
        $this->assertNotContains($desk->id, $ids, 'Never the desk\'s own pieces');
        $this->assertSame(2, \App\Models\Post::whereIn('id', $ids)->whereHas('authors', fn ($q) => $q->whereKey($busy->id))->count(), 'At most two per writer');
        $this->assertNull(\App\Ai\Archivist::anthology(), 'Once a year');

        // Before publishing: only editors see the magazine.
        $this->get('/collection-preview/'.$c->id.'/')->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->get('/collection-preview/'.$c->id.'/')->assertOk()->assertSee('খসড়া দেখছেন')->assertSee('এই সংকলনে')->assertSee('ভূমিকা')->assertSee('রিনার লেখা');

        // Published: the magazine is public, the cover proposal becomes the cover, the news goes out once, on a click.
        $c->forceFill(['proposed_cover_path' => 'media/covers/2026/12/c.jpg'])->save();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \App\Filament\Resources\Collections\CollectionResource::approve($c->fresh());
        $c = $c->fresh();
        $this->assertSame('media/covers/2026/12/c.jpg', $c->cover_path);
        auth()->logout();
        $this->get($c->url())->assertOk()->assertSee('পত্রিকা খুলুন');
        $this->get('/sonkolon/'.$c->slug.'/boi/')->assertOk()->assertSee('বর্ষসেরা ২০২৬')->assertSee('src="'.url('/media/covers/2026/12/c.jpg').'"', false);

        $this->assertSame(1, \App\Support\CollectionNews::tellWriters($c, $admin));
        if (\App\Support\NewsletterSender::realMail()) {
            \Illuminate\Support\Facades\Notification::assertSentTo($user, \App\Notifications\TemplateMail::class, fn ($n) => $n->key === 'anthology_piece');
        }
        $this->assertSame(0, \App\Support\CollectionNews::tellWriters($c->fresh(), $admin), 'Once only');

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Resources\Collections\Pages\EditCollection::class, ['record' => $c->id])
            ->assertActionVisible('previewMagazine')->assertActionHidden('tellWriters')->assertSee('প্রচ্ছদ');
    }
}
