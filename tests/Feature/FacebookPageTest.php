<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\FacebookPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Facebook Page posting (owner, 2026-11-12): on a click, at a time, or by itself once the owner switches it on. */
class FacebookPageTest extends TestCase
{
    use RefreshDatabase;

    private function keys(bool $live = true): void
    {
        config(['services.facebook_page.id' => '307497312677802', 'services.facebook_page.token' => 'page-token', 'services.facebook_page.app_secret' => 'secret',
            'mail.off_live' => $live ? 'allow' : null]);
    }

    private function social(array $texts = [], array $attrs = []): SocialPost
    {
        $post = $this->makePost(['slug' => 'nodi-'.uniqid(), 'title' => 'নদীর গান']);

        return SocialPost::create($attrs + ['kind' => 'piece', 'subject_type' => $post->getMorphClass(), 'subject_id' => $post->id, 'title' => 'নদীর গান',
            'texts' => $texts + ['facebook' => "নতুন কবিতা: নদীর গান\n\n".$post->url(), 'whatsapp' => 'x', 'instagram' => 'y']]);
    }

    public function test_post_now_sends_the_text_and_link_with_a_proof_and_only_once(): void
    {
        $this->keys();
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '307497312677802_111'])]);
        $s = $this->social();

        [$ok, $id] = FacebookPage::publish($s, null);
        $this->assertTrue($ok);
        $this->assertSame('307497312677802_111', $id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/307497312677802/feed') && $r['message'] === 'নতুন কবিতা: নদীর গান'
            && $r['link'] === $s->subject->url() && $r['appsecret_proof'] === hash_hmac('sha256', 'page-token', 'secret'));
        $s->refresh();
        $this->assertSame('posted', $s->status);
        $this->assertSame([false, 'আগেই পোস্ট হয়েছে, বা তালিকা থেকে সরানো'], FacebookPage::publish($s));
        Http::assertSentCount(1);
    }

    public function test_never_from_staging_never_an_unpublished_piece_and_errors_are_shown(): void
    {
        $this->keys(live: false);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);
        $s = $this->social();
        $this->assertFalse(FacebookPage::publish($s)[0], 'Staging never posts to the real page');
        Http::assertNothingSent();

        $this->keys();
        $draft = $this->social([], []);
        $draft->subject->forceFill(['status' => 'draft'])->saveQuietly();
        $this->assertFalse(FacebookPage::publish($draft->fresh())[0]);

        $this->assertFalse(FacebookPage::publish($s->fresh())[0]);
        $this->assertSame('Invalid OAuth access token', $s->fresh()->fb_error);
        $this->assertSame('ready', $s->fresh()->status, 'Stays in the list to try again');
    }

    public function test_timed_posts_and_automatic_mode_only_when_the_owner_turns_it_on(): void
    {
        $this->keys();
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'p_1'])]);
        \App\Models\SocialPost::query()->update(['status' => 'dismissed']);   // the seeded relaunch post stays out of this test
        $timed = $this->social();
        $timed->forceFill(['fb_scheduled_at' => now()->addHour()])->save();
        $this->assertSame(0, FacebookPage::due());
        $this->travel(61)->minutes();
        $this->assertSame(1, FacebookPage::due());
        $this->assertSame('p_1', $timed->fresh()->fb_post_id);

        $this->social();
        \App\Models\Setting::put(FacebookPage::AUTO, '0');   // D182: on since the owner asked for fully automatic; the switch still turns it off
        $this->assertNull(FacebookPage::autoPost(), 'Off when the owner switches it off');
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\Marketing::class)->assertSee('নিজে থেকে পোস্ট: বন্ধ')->assertSee('ফেসবুকে দিন')->assertSee('আজ ও কালের পোস্ট')->call('toggleFacebookAuto');
        $this->assertTrue(FacebookPage::auto());
        $this->assertNotNull(FacebookPage::autoPost());

        // Scheduling from the desk, in Kolkata time.
        $s = $this->social();
        \Livewire\Livewire::test(\App\Filament\Pages\Marketing::class)->call('facebookSchedule', $s->id, now('Asia/Kolkata')->addDay()->format('Y-m-d\TH:i'));
        $this->assertNotNull($s->fresh()->fb_scheduled_at);
    }
}
