<?php

namespace Tests\Feature;

use App\Ai\BacklogScreen;
use App\Ai\Claude;
use App\Enums\Role;
use App\Filament\Pages\RelaunchLetters as RelaunchPage;
use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use App\Notifications\RelaunchLetter;
use App\Support\RelaunchLetters;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** D104: old unanswered submissions: read first, ask only about the good ones, never publish without the writer's yes. */
class RelaunchLettersTest extends TestCase
{
    use RefreshDatabase;

    private int $wp = 9000;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function writer(string $name = 'পুরনো লেখক'): Author
    {
        $u = User::factory()->create(['name' => $name]);

        return Author::create(['slug' => 'old-'.uniqid(), 'display_name' => $name, 'user_id' => $u->id]);
    }

    private function old(Author $a, array $attrs = []): Post
    {
        return $this->makePost(array_merge(['status' => 'pending', 'published_at' => null, 'submitted_by' => null, 'legacy_wp_id' => $this->wp++,
            'submitted_at' => now()->subYears(8), 'title' => 'পুরনো কবিতা '.$this->wp,
            'body_html' => '<p>'.str_repeat('নদীর ধারে সন্ধ্যা নামে, পাখিরা ফেরে বাসায়। ', 4).'</p>'], $attrs), $a);
    }

    private function goLive(): void
    {
        config(['app.url' => 'https://hatpakha.com', 'mail.default' => 'smtp']);
    }

    public function test_old_submissions_cannot_be_published_without_the_writers_yes(): void
    {
        $p = $this->old($this->writer());
        try {
            $p->update(['status' => 'published', 'published_at' => now()]);
            $this->fail('An old submission was published without the writer');
        } catch (ValidationException) {
        }
        $this->assertSame('pending', $p->fresh()->status);

        $p->refresh()->forceFill(['relaunch_status' => 'yes'])->saveQuietly();
        $p->update(['status' => 'published', 'published_at' => now()]);
        $this->assertSame('published', $p->fresh()->status, 'After the yes, the owner can publish');

        $new = $this->makePost(['status' => 'pending', 'submitted_by' => null, 'published_at' => null]);
        $new->update(['status' => 'published', 'published_at' => now()]);
        $this->assertSame('published', $new->fresh()->status, 'Pieces that are not old WordPress submissions are not affected');
    }

    public function test_ai_editor_reads_each_piece_once_and_never_overrides_the_owner(): void
    {
        $a = $this->writer();
        $good = $this->old($a, ['title' => 'ভালো লেখা']);
        $weak = $this->old($a, ['title' => 'দুর্বল লেখা']);
        $tiny = $this->old($a, ['title' => 'ফাঁকা', 'body_html' => '<p>হ্যালো</p>']);
        $mine = $this->old($a, ['title' => 'আমার বাছাই']);
        RelaunchLetters::setWorth($mine, 'ask');
        $seen = [];
        Claude::$fake = function ($purpose, $user) use (&$seen) {
            $seen[] = $purpose;

            return str_contains($user, 'দুর্বল') ? ['decision' => 'skip', 'score' => 1, 'reason' => 'খুব দুর্বল।'] : ['decision' => 'ask', 'score' => 4, 'reason' => 'ভালো ছন্দ।'];
        };

        $this->assertSame(3, BacklogScreen::backlog());
        $this->assertSame(['backlog_screen', 'backlog_screen'], $seen, 'The empty piece is skipped without asking the AI; the owner\'s choice is not re-read');
        $this->assertSame('ask', $good->fresh()->relaunch_worth);
        $this->assertSame(4, $good->fresh()->relaunch_score);
        $this->assertSame('skip', $weak->fresh()->relaunch_worth);
        $this->assertSame('skip', $tiny->fresh()->relaunch_worth);
        $this->assertSame('ask', $mine->fresh()->relaunch_worth);
        $this->assertTrue($mine->fresh()->relaunch_worth_by_owner);
        $this->assertSame(0, BacklogScreen::backlog(), 'Each piece is read once');
        $this->assertSame('pending', $good->fresh()->status, 'Reading never publishes');
    }

    public function test_letters_wait_for_reading_approval_live_site_and_real_mail(): void
    {
        Notification::fake();
        $a = $this->writer();
        $p = $this->old($a);
        $this->assertStringContainsString('পড়া শেষ', RelaunchLetters::waitingFor());
        $p->forceFill(['relaunch_worth' => 'ask'])->saveQuietly();
        $this->assertStringContainsString('অনুমোদন', RelaunchLetters::waitingFor());

        RelaunchLetters::approve(User::factory()->create(['role' => Role::Admin]));
        $this->assertStringContainsString('hatpakha.com', RelaunchLetters::waitingFor(), 'Never from staging');
        config(['app.url' => 'https://new.hatpakha.com']);
        $this->assertSame(0, RelaunchLetters::send());

        config(['app.url' => 'https://hatpakha.com', 'mail.default' => 'log']);
        $this->assertStringContainsString('SMTP', RelaunchLetters::waitingFor());
        $this->goLive();
        $this->assertNull(RelaunchLetters::waitingFor());

        RelaunchLetters::save('নতুন বিষয়: ক্ষমা চাইছি', RelaunchLetters::DEFAULT_BODY);
        $this->assertFalse(RelaunchLetters::approved(), 'Changed words need a new approval');
        Notification::assertNothingSent();
    }

    public function test_only_writers_of_good_pieces_get_one_letter_listing_only_those_pieces(): void
    {
        Notification::fake();
        $this->goLive();
        RelaunchLetters::approve(User::factory()->create(['role' => Role::Admin]));
        $both = $this->writer('দুই রকম');
        $good = $this->old($both, ['title' => 'ভালো লেখা', 'relaunch_worth' => 'ask']);
        $weak = $this->old($both, ['title' => 'দুর্বল লেখা', 'relaunch_worth' => 'skip']);
        $onlyWeak = $this->writer('শুধু দুর্বল');
        $this->old($onlyWeak, ['relaunch_worth' => 'skip']);
        $optedOut = $this->writer('চান না');
        $optedOut->user->forceFill(['mail_opt_out' => true])->save();
        $this->old($optedOut, ['relaunch_worth' => 'ask']);

        $this->assertSame(1, RelaunchLetters::send());
        Notification::assertSentTo($both->user, RelaunchLetter::class, function (RelaunchLetter $n) {
            $html = (string) $n->toMail($this->app->make(User::class)->forceFill(['name' => 'দুই রকম']))->render();

            return $n->titles === ['ভালো লেখা'] && str_contains($html, 'ক্ষমা') && str_contains($html, '/lekha-uttor/');
        });
        Notification::assertNotSentTo($onlyWeak->user, RelaunchLetter::class);
        Notification::assertNotSentTo($optedOut->user, RelaunchLetter::class);
        $this->assertSame('asked', $good->fresh()->relaunch_status);
        $this->assertNull($weak->fresh()->relaunch_status);
        $this->assertSame(0, RelaunchLetters::send(), 'One letter per writer, ever');
    }

    public function test_sending_is_slow_and_newest_submissions_first(): void
    {
        Notification::fake();
        $this->goLive();
        config(['mail.relaunch_per_hour' => 2]);
        RelaunchLetters::approve(User::factory()->create(['role' => Role::Admin]));
        $authors = [];
        foreach ([9, 3, 6] as $years) {
            $a = $authors[$years] = $this->writer('লেখক '.$years);
            $this->old($a, ['relaunch_worth' => 'ask', 'submitted_at' => now()->subYears($years)]);
        }
        $this->assertSame(2, RelaunchLetters::send());
        Notification::assertSentTo($authors[3]->user, RelaunchLetter::class);
        Notification::assertSentTo($authors[6]->user, RelaunchLetter::class);
        Notification::assertNotSentTo($authors[9]->user, RelaunchLetter::class);
    }

    public function test_writer_answers_from_the_signed_link_per_piece(): void
    {
        $a = $this->writer('উত্তরদাতা');
        $one = $this->old($a, ['title' => 'প্রথম লেখা', 'relaunch_worth' => 'ask', 'relaunch_status' => 'asked']);
        $two = $this->old($a, ['title' => 'দ্বিতীয় লেখা', 'relaunch_worth' => 'ask', 'relaunch_status' => 'asked']);
        $weak = $this->old($a, ['title' => 'বাদ পড়া লেখা', 'relaunch_worth' => 'skip']);
        $url = RelaunchLetters::replyUrl($a);

        $this->get('/lekha-uttor/'.$a->id)->assertForbidden();
        $this->get($url)->assertOk()->assertSee('প্রথম লেখা')->assertSee('দ্বিতীয় লেখা')->assertDontSee('বাদ পড়া লেখা')->assertSee('noindex', false);

        $this->post($url, ['answer' => [$one->id => 'yes', $two->id => 'no', $weak->id => 'yes'], 'elsewhere' => [$one->id => '1']])
            ->assertRedirect($url);
        $this->assertSame('yes', $one->fresh()->relaunch_status);
        $this->assertTrue($one->fresh()->relaunch_elsewhere);
        $this->assertSame('no', $two->fresh()->relaunch_status);
        $this->assertNull($weak->fresh()->relaunch_status, 'A piece left out can not be answered');
        $this->assertSame('pending', $one->fresh()->status, 'A yes never publishes by itself');
        $this->get($url)->assertSee('ধন্যবাদ');
    }

    public function test_owner_desk_page_shows_the_screening_and_can_change_it(): void
    {
        $a = $this->writer();
        $weak = $this->old($a, ['title' => 'দুর্বল কিন্তু ভালো', 'relaunch_worth' => 'skip', 'relaunch_score' => 2, 'relaunch_note' => 'ছন্দ ভাঙা।']);
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(RelaunchPage::canAccess());

        $this->actingAs($admin = User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(RelaunchPage::class)->assertOk()->call('showList', 'skip')->assertSee('দুর্বল কিন্তু ভালো')->assertSee('ছন্দ ভাঙা।')
            ->call('setWorth', $weak->id, 'ask')
            ->set('body', RelaunchLetters::DEFAULT_BODY)->call('approve');
        $this->assertSame('ask', $weak->fresh()->relaunch_worth);
        $this->assertTrue(RelaunchLetters::approved());
    }
}
