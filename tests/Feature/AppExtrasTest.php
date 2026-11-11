<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PushAnnouncement;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushNotifier;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** D132: richer install window, offline "পরে পড়ব", share to Hatpakha, section choices, numbers and announcements. */
class AppExtrasTest extends TestCase
{
    use RefreshDatabase;

    private function keys(): void
    {
        [$pub, $priv] = WebPush::newKeys();
        config(['services.webpush.public_key' => $pub, 'services.webpush.private_key' => $priv]);
    }

    private function sub(array $topics, ?int $user = null, string $endpoint = 'https://fcm.googleapis.com/fcm/send/'): PushSubscription
    {
        [$pub] = WebPush::newKeys();
        $e = $endpoint.uniqid();

        return PushSubscription::create(['user_id' => $user, 'endpoint' => $e, 'endpoint_hash' => PushSubscription::hashOf($e), 'p256dh' => $pub, 'auth' => WebPush::b64e(random_bytes(16)), 'topics' => $topics]);
    }

    public function test_manifest_has_store_style_screenshots_and_a_share_target(): void
    {
        $m = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertNotEmpty($m['description']);
        $this->assertGreaterThanOrEqual(1, count($m['screenshots']));
        foreach ($m['screenshots'] as $s) {
            [$w, $h] = getimagesize(public_path(ltrim($s['src'], '/')));
            $this->assertSame($s['sizes'], $w.'x'.$h);
            $this->assertSame('narrow', $s['form_factor']);
            $this->assertLessThanOrEqual(2.3, $h / $w, 'Chrome shows screenshots up to 2.3:1');
        }
        $this->assertSame('/submit/', $m['share_target']['action']);
        $this->assertSame('GET', $m['share_target']['method']);
    }

    public function test_shared_text_opens_in_the_submission_form(): void
    {
        $writer = User::factory()->create(['role' => Role::Writer, 'email_verified_at' => now()]);
        $this->get('/submit/?title=মেঘ&text='.rawurlencode("প্রথম লাইন\nদ্বিতীয় লাইন"))->assertRedirect();   // guests sign in first
        $html = $this->actingAs($writer)->get('/submit/?title=মেঘ&text='.rawurlencode("প্রথম লাইন\nদ্বিতীয় <b>লাইন</b>"))->assertOk()->getContent();
        $this->assertStringContainsString('শেয়ার করা লেখাটি নিচে বসানো আছে', $html);
        $this->assertStringContainsString("প্রথম লাইন\nদ্বিতীয় &lt;b&gt;লাইন&lt;/b&gt;</textarea>", $html, 'Shown as plain text, escaped');
        $this->assertStringContainsString('value="মেঘ"', $html);
    }

    public function test_offline_reading_list_and_morning_fetch_are_in_the_worker(): void
    {
        $js = $this->get('/sw.js')->getContent();
        $this->assertStringContainsString("const SAVED = 'hp-saved-v1'", $js);
        $this->assertStringContainsString("credentials: 'omit'", $js, 'Kept pages are the guest version, never personal');
        $this->assertStringContainsString("'periodicsync'", $js);
        $this->assertStringContainsString("'/push/opened'", $js);
    }

    public function test_section_choices_notify_on_new_pieces_and_counts_are_kept(): void
    {
        $this->keys();
        $this->asLiveSite();
        $poetry = \App\Models\Section::firstOrCreate(['slug' => 'poetry'], ['name_bn' => 'কবিতা', 'format' => 'poem', 'is_active' => true]);
        $wants = $this->sub(['section:'.$poetry->id]);
        $other = $this->sub(['section:999']);
        Http::fake(['*' => Http::response('', 201)]);
        $post = $this->makePost(['status' => 'published', 'section_id' => $poetry->id, 'published_at' => now()->subHour()]);
        $this->assertSame(1, PushNotifier::forPost($post));
        Http::assertSent(fn ($r) => $r->url() === $wants->endpoint);
        Http::assertNotSent(fn ($r) => $r->url() === $other->endpoint);
        $this->assertSame(1, (int) DB::table('push_stats')->where('kind', 'section')->value('sent'));

        $this->postJson('/push/opened', ['kind' => 'section'])->assertNoContent();
        $this->postJson('/push/opened', ['kind' => '<script>'])->assertNoContent();
        $this->assertSame(1, (int) DB::table('push_stats')->where('kind', 'section')->value('opened'));
        $this->assertSame(1, (int) DB::table('push_stats')->where('kind', 'other')->value('opened'), 'Unknown kinds are counted as "other"');

        // The choice is offered on the section page and only for real sections.
        $this->get($poetry->url())->assertOk()->assertSee('data-push-topic="section:'.$poetry->id.'"', false);
        $b = WebPush::newKeys();
        $this->postJson('/push/', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/x1', 'keys' => ['p256dh' => $b[0], 'auth' => WebPush::b64e(random_bytes(16))], 'add' => ['section:424242']])->assertStatus(422);
    }

    public function test_the_owner_sends_an_announcement_in_batches_and_sees_the_numbers(): void
    {
        $this->keys();
        $this->asLiveSite();
        foreach (range(1, 5) as $i) {
            $this->sub($i <= 2 ? ['daily'] : ['author:1']);
        }
        Http::fake(['*' => Http::response('', 201)]);
        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Pages\PushNotices::class)
            ->assertSee('৫')
            ->callAction('announce', ['title' => 'অগ্রহায়ণ সংখ্যা', 'body' => 'নতুন সংখ্যা বেরোল', 'url' => 'https://evil.example/', 'audience' => 'all'])
            ->assertHasActionErrors(['url']);
        \Livewire\Livewire::test(\App\Filament\Pages\PushNotices::class)
            ->callAction('announce', ['title' => 'অগ্রহায়ণ সংখ্যা', 'body' => 'নতুন সংখ্যা বেরোল', 'url' => url('/sonkha/2026-11').'/', 'audience' => 'all'])
            ->assertHasNoActionErrors();
        $a = PushAnnouncement::firstOrFail();
        $this->assertSame(5, $a->recipients);
        Http::assertNothingSent();   // nothing goes until the scheduler's batch

        $this->assertSame(2, PushNotifier::announceBatch(2));
        $this->assertSame('sending', $a->fresh()->status);
        $this->assertSame(3, PushNotifier::announceBatch(200));
        $this->assertSame('sent', $a->fresh()->status);
        $this->assertSame(0, PushNotifier::announceBatch(200), 'Never sent twice');
        $this->assertSame(5, (int) DB::table('push_stats')->where('kind', 'announce')->value('sent'));

        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Pages\PushNotices::canAccess(), 'Admins only');
    }
}
