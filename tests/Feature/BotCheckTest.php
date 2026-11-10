<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\NewsletterList;
use App\Models\Author;
use App\Models\Comment;
use App\Models\User;
use App\Support\BotCheck;
use App\Support\NewsletterSender;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D121: likely bots are flagged for the owner's review; nothing is removed or blocked without a click. */
class BotCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        BotCheck::$dns = fn (string $domain) => $domain !== 'deadmail.example';
    }

    protected function tearDown(): void
    {
        BotCheck::$dns = null;
        parent::tearDown();
    }

    private function user(string $name, string $email, array $more = []): User
    {
        return User::factory()->create(['name' => $name, 'email' => $email, 'role' => Role::Reader, 'last_login_at' => null] + $more);
    }

    public function test_signals(): void
    {
        $this->assertSame([], BotCheck::reasons($this->user('Anup Kumar Santra', 'anupsantra3008@gmail.com')), 'A normal reader is not flagged');
        $this->assertSame([], BotCheck::reasons($this->user('মৌ চক্রবর্তী', 'mou.chakraborty@visva-bharati.ac.in')));
        $this->assertContains('disposable', BotCheck::reasons($this->user('Ravi', 'ravi@mailinator.com')));
        $this->assertContains('no_mail_domain', BotCheck::reasons($this->user('Ravi', 'ravi@deadmail.example')));
        $this->assertContains('spam_words', BotCheck::reasons($this->user('Best Casino Bonus', 'bonus@gmail.com')));
        $this->assertContains('link_in_name', BotCheck::reasons($this->user('Visit www.cheap-pills.xyz', 'x1@gmail.com')));

        // Weak signals only together, and only without any activity.
        $weird = $this->user('XkTrbQzpLm', 'qwrtzpkl8812734@gmail.com');
        $this->assertEqualsCanonicalizing(['random_email', 'random_name'], BotCheck::reasons($weird));
        $this->assertSame([], BotCheck::reasons($weird, active: true));
        $this->assertSame([], BotCheck::reasons($this->user('Rahul', 'rahul19852023@gmail.com')), 'One weak signal alone is not enough');
    }

    public function test_scan_flags_for_review_and_the_owner_decides(): void
    {
        $bot = $this->user('Cheap Viagra Shop', 'shop@gmail.com', ['email_verified_at' => now()]);
        $person = $this->user('Sangita Saha', 'esksangita@gmail.com');
        $writer = $this->user('Best Loans Writer', 'loans@gmail.com');
        $this->makePost(['status' => 'published'], Author::create(['slug' => 'lw', 'display_name' => 'LW', 'user_id' => $writer->id]));
        $editor = User::factory()->create(['name' => 'Casino Editor', 'role' => Role::Editor]);

        $n = BotCheck::scan();
        $this->assertSame(['spam_words'], $bot->fresh()->bot_reasons);
        $this->assertNull($person->fresh()->bot_reasons);
        $this->assertNotNull($writer->fresh()->bot_reasons, 'Strong signals still go to review, even for an active account');
        $this->assertNull($editor->fresh()->bot_reasons, 'Desk staff are never flagged');
        $this->assertSame(2, $n['flagged']);

        // While waiting: no letters, comments always checked. Not deleted, not blocked.
        $this->assertNotContains('shop@gmail.com', NewsletterSender::recipients('readers')->pluck('email')->all());
        $this->assertSame('pending', Comment::statusFor($bot->fresh()));
        $this->assertModelExists($bot);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        $this->assertSame('২', NewsletterList::getNavigationBadge());
        Livewire::withQueryParams(['source' => 'suspects'])->test(NewsletterList::class)
            ->assertCanSeeTableRecords([$bot, $writer])->assertSee('নামে বা ঠিকানায় স্প্যামের শব্দ')
            ->callTableAction('bot', $bot)
            ->callTableAction('human', $writer);

        $this->assertSame('bot', $bot->fresh()->bot_review);
        $this->assertTrue($bot->fresh()->mail_opt_out);
        $this->assertContains('loans@gmail.com', NewsletterSender::recipients('readers')->pluck('email')->all(), 'A cleared account gets letters again');
        BotCheck::scan();
        $this->assertSame('human', $writer->fresh()->bot_review, 'Never re-flagged after review');
        $this->assertNull(NewsletterList::getNavigationBadge());
    }

    public function test_a_bot_like_sign_up_is_flagged_and_a_confirmed_bot_cannot_sign_in(): void
    {
        $this->travel(-10)->seconds();
        $started = \App\Http\Controllers\CommentController::startedToken();
        $this->travel(10)->seconds();
        $this->post('/register/', ['name' => 'Casino Bonus 777', 'email' => 'promo@mailinator.com', 'password' => 'a-good-long-pass-9087',
            'password_confirmation' => 'a-good-long-pass-9087', 'terms' => '1', 'started' => $started]);
        $u = User::where('email', 'promo@mailinator.com')->first();
        $this->assertNotNull($u, 'Sign-up is not refused: a person reviews it');
        $this->assertContains('disposable', $u->bot_reasons);
        $this->assertContains('spam_words', $u->bot_reasons);

        BotCheck::decide($u, 'bot', null);
        auth()->logout();
        $this->post('/login/', ['email' => 'promo@mailinator.com', 'password' => 'a-good-long-pass-9087'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_owner_can_delete_flagged_accounts(): void
    {
        $a = $this->user('Casino One', 'c1@gmail.com');
        $b = $this->user('Casino Two', 'c2@gmail.com');
        BotCheck::scan();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::withQueryParams(['source' => 'suspects'])->test(NewsletterList::class)->callTableAction('delete', $a);
        $this->assertModelMissing($a);
        Livewire::withQueryParams(['source' => 'suspects'])->test(NewsletterList::class)->callTableBulkAction('deleteSelected', [$b]);
        $this->assertModelMissing($b);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted']);
    }
}
