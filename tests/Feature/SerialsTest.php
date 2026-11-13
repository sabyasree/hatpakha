<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\SerialWriter;
use App\Enums\Role;
use App\Models\Author;
use App\Models\Post;
use App\Models\Serial;
use App\Models\User;
use App\Notifications\NewEpisode;
use App\Support\FollowNotifier;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** D108: serial stories. AI writers draft; the owner publishes each episode; readers follow and come back. */
class SerialsTest extends TestCase
{
    use RefreshDatabase;

    private array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k', 'ai.openai.key' => null]);
        Author::firstOrCreate(['slug' => 'hatpakha-desk'], ['display_name' => 'হাতপাখা ডেস্ক', 'is_desk' => true]);
        Claude::$fake = function ($purpose, $user, $schema) {
            $this->seen[] = [$purpose, $user, $schema];
            $ep = ['title' => 'বন্ধ দরজা', 'paragraphs' => ['রাত বারোটায় দরজায় টোকা পড়ল।', 'দরজা খুলে দেখি কেউ নেই, শুধু একটা চিঠি।'],
                'summary' => 'অমল রাতে একটি চিঠি পায়।', 'image_prompt' => 'an old door at night', 'teaser' => 'চিঠির খামে যে ঠিকানা, সে বাড়ি ত্রিশ বছর আগে পুড়ে গেছে।'];

            return in_array('cover_prompt', $schema['required'] ?? [], true)
                ? ['title' => 'ছায়ার বাড়ি', 'logline' => 'পুরনো বাড়ির দরজায় রোজ রাতে টোকা পড়ে।', 'setting' => 'উত্তর কলকাতা',
                    'characters' => [['name' => 'অমল', 'role' => 'ভাড়াটে']], 'episodes' => [['title' => 'বন্ধ দরজা', 'beat' => 'চিঠি'], ['title' => 'দ্বিতীয় রাত', 'beat' => 'ছায়া']],
                    'episode' => $ep, 'cover_prompt' => 'a dark old house']
                : ['episode' => ['title' => str_contains($user, 'Write episode 1,') ? 'বন্ধ দরজা' : 'দ্বিতীয় রাত'] + $ep];
        };
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_writer_plans_a_serial_and_drafts_episodes_one_week_ahead_never_publishing(): void
    {
        [$serial] = SerialWriter::start('serial_ghost');
        $this->assertSame('proposed', $serial->status);
        $this->assertSame('অলৌকিক', $serial->genre);
        $this->assertCount(2, $serial->bible['episodes']);
        $ep1 = $serial->episodes()->first();
        $this->assertSame('draft', $ep1->status, 'Nothing is published by the AI');
        $this->assertSame(['hatpakha-desk'], $ep1->authors->pluck('slug')->all(), 'House byline, never a made-up author');
        $this->assertSame(1, $ep1->episode_no);
        $this->assertStringContainsString('পর্ব ১', $ep1->title);
        $this->assertStringContainsString('<idea>', $this->seen[0][1], 'The owner\'s idea is passed as data');

        // D172: the writer is told how to bring readers back, and gives one "next episode" line
        $this->assertContains('teaser', $this->seen[1][2]['properties']['episode']['required']);
        $this->assertSame('চিঠির খামে যে ঠিকানা, সে বাড়ি ত্রিশ বছর আগে পুড়ে গেছে।', $ep1->episode_teaser);

        $this->assertSame(0, SerialWriter::keepAhead(), 'A proposed serial waits for the owner');
        $serial->activate();
        $this->assertSame(0, SerialWriter::keepAhead(), 'Episode 1 is still unpublished: already one ahead');
        $ep1->forceFill(['status' => 'published', 'published_at' => now()->subMinute()])->save();
        $this->assertSame(1, SerialWriter::keepAhead());
        $ep2 = $serial->episodes()->where('episode_no', 2)->first();
        $this->assertSame('draft', $ep2->status);
        $this->assertCount(3, $this->seen, 'Plan, episode 1, episode 2: separate requests so none is cut off');
        $this->assertStringContainsString('অমল রাতে একটি চিঠি পায়', $this->seen[2][1], 'The story so far is given, for continuity');
        $this->get($ep1->fresh()->url())->assertOk()->assertSee('পরের পর্বে')->assertSee('ত্রিশ বছর আগে পুড়ে গেছে');   // shown until episode 2 is out
        $ep2->forceFill(['status' => 'published', 'published_at' => now()->subSecond()])->save();
        $this->get($ep1->fresh()->url())->assertDontSee('ত্রিশ বছর আগে পুড়ে গেছে');
    }

    public function test_publishing_the_first_episode_starts_the_serial_and_readers_can_follow_it(): void
    {
        [$serial] = SerialWriter::start('serial_mystery');
        $ep1 = $serial->episodes()->first();
        $ep1->update(['status' => 'published', 'published_at' => now()->subMinute()]);
        $serial->refresh();
        $this->assertSame('active', $serial->status);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $serial->slug);

        $this->get('/dharabahik/')->assertOk()->assertSee($serial->title);
        $this->get($serial->url())->assertOk()->assertSee('পর্ব ১: বন্ধ দরজা')->assertSee('নতুন পর্ব এলে জানান')->assertSee('প্রতি শনিবার');
        $this->get($ep1->url())->assertOk()->assertSee('ক্রমশ…')->assertSee('পরের পর্ব আসবে শনিবার')->assertDontSee('>সমাপ্ত<', false);
        $this->get('/category/dharabahik/')->assertRedirect($serial->url() === '' ? '' : \App\Support\Url::to('/dharabahik'));

        $reader = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($reader)->post(route('serial.follow', $serial))->assertRedirect();
        $this->assertTrue($serial->isFollowedBy($reader));

        // Episode 2 goes live: the follower is told once, with a one-click stop.
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $ep2 = $this->makePost(['serial_id' => $serial->id, 'episode_no' => 2, 'title' => $serial->title.' (পর্ব ২): দ্বিতীয় রাত', 'section_id' => $ep1->section_id, 'slug' => 'ep-2']);
        $ep1->forceFill(['followers_notified_at' => now()])->saveQuietly();
        FollowNotifier::run();
        Notification::assertSentTo($reader, NewEpisode::class, fn (NewEpisode $n) => str_contains($n->unfollowUrl, '/newsletter/unsubscribe/s/'));
        $this->get($ep1->url())->assertSee('পরের পর্ব: পর্ব ২: দ্বিতীয় রাত');
    }

    public function test_desk_shows_proposals_to_the_owner_only(): void
    {
        [$serial] = SerialWriter::start('serial_family');
        $this->get('/dharabahik/'.($serial->slug ?? 'x').'/')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Resources\Serials\SerialResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Resources\Serials\Pages\ListSerials::class)->assertOk()->assertSee('ছায়ার বাড়ি');
        Livewire::test(\App\Filament\Resources\Serials\Pages\EditSerial::class, ['record' => $serial->getRouteKey()])
            ->assertSee('উত্তর কলকাতা')->assertSee('অমল')->callAction('start');
        $this->assertSame('active', $serial->fresh()->status);
    }

    public function test_episode_titles_never_repeat_the_serial_name_or_number(): void
    {
        // Owner's screenshot (2026-11-09): "লাল কাচ (পর্ব ২): লাল কাচ (পর্ব ২): খাতা".
        $this->assertSame('খাতা', SerialWriter::ownTitle('লাল কাচ (পর্ব ২): লাল কাচ (পর্ব ২): খাতা', 'লাল কাচ'));
        $this->assertSame('রঙের নিচে রং', SerialWriter::ownTitle('উল্টো হরফ (পর্ব ২): রঙের নিচে রং'));
        $this->assertSame('খাতা', SerialWriter::ownTitle('পর্ব ২: খাতা'));
        $this->assertSame('খাতা', SerialWriter::ownTitle('লাল কাচ: খাতা', 'লাল কাচ'));
        $this->assertSame('বন্ধ দরজা', SerialWriter::ownTitle('বন্ধ দরজা'));

        $serial = Serial::create(['title' => 'লাল কাচ', 'status' => 'active', 'slug' => 'lal-kach', 'day' => 6]);
        $this->assertSame('লাল কাচ (পর্ব ২): খাতা', SerialWriter::fullTitle($serial, 2, 'লাল কাচ (পর্ব ২): খাতা'));
        $p = $this->makePost(['slug' => 'lk2', 'serial_id' => $serial->id, 'episode_no' => 2, 'title' => 'লাল কাচ (পর্ব ২): লাল কাচ (পর্ব ২): খাতা']);
        $this->assertSame('পর্ব ২: খাতা', $p->episodeLabel());
    }

    public function test_a_live_serial_is_in_the_sitemap_with_its_last_episode_date(): void
    {
        // Production, 2026-11-10: the sitemap crashed once a serial had a published episode (the date came back as text).
        $serial = Serial::create(['title' => 'লাল কাচ', 'status' => 'active', 'slug' => 'lal-kach', 'day' => 6]);
        $this->makePost(['status' => 'published', 'serial_id' => $serial->id, 'episode' => 1, 'published_at' => '2026-11-08 18:00:00']);
        $this->get('/sitemap-pages.xml')->assertOk()->assertSee($serial->url(), false)->assertSee('<lastmod>2026-11-08T18:00:00', false);
    }
}
