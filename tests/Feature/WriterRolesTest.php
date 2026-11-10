<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Author;
use App\Models\User;
use App\Support\NewsletterSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner, 2026-11-10: accounts with pieces become writers, the rest readers; the newsletter counts match. */
class WriterRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_with_pieces_become_writers_and_the_counts_show_why(): void
    {
        $writer = User::factory()->create(['role' => Role::Reader, 'email_verified_at' => now()]);
        $this->makePost(['status' => 'pending'], Author::create(['slug' => 'w', 'display_name' => 'W', 'user_id' => $writer->id]));
        $reader = User::factory()->create(['role' => Role::Reader, 'email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => Role::Admin, 'email_verified_at' => now()]);
        $this->makePost(['status' => 'published'], Author::create(['slug' => 'a', 'display_name' => 'A', 'user_id' => $admin->id]));
        User::factory()->create(['role' => Role::Reader, 'email_verified_at' => null]);
        User::factory()->create(['role' => Role::Reader, 'email_verified_at' => now(), 'mail_opt_out' => true]);

        (include database_path('migrations/2026_11_10_000100_writer_roles_from_posts.php'))->up();

        $this->assertSame(Role::Writer, $writer->fresh()->role);
        $this->assertSame(Role::Reader, $reader->fresh()->role);
        $this->assertSame(Role::Admin, $admin->fresh()->role);

        $b = NewsletterSender::breakdown();
        $this->assertSame(5, $b['accounts']);
        $this->assertSame(1, $b['unverified']);
        $this->assertSame(1, $b['opted_out']);
        $this->assertSame(4, $b['readers']);   // D120: every account that didn't switch the letter off, verified or not
        $this->assertSame(2, $b['writers']);   // the new writer + the admin with a published piece
    }

    public function test_the_list_page_shows_accounts_and_can_stop_letters_admins_only(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $reader = User::factory()->create(['name' => 'পাঠক এক', 'email' => 'one@example.test', 'role' => Role::Reader, 'email_verified_at' => null]);
        User::factory()->create(['name' => 'Bot', 'role' => Role::Reader, 'spam_reason' => 'bot-like sign-up']);
        \App\Models\NewsletterSubscriber::create(['email' => 'signup@example.test', 'status' => 'subscribed', 'consented_at' => now()]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));

        $this->assertContains('one@example.test', NewsletterSender::recipients('readers')->pluck('email')->all(), 'Unverified accounts are on the list (D120)');
        \Livewire\Livewire::test(\App\Filament\Pages\NewsletterList::class)->assertOk()
            ->searchTable('one@')->assertCanSeeTableRecords([$reader])
            ->callTableAction('stop', $reader);
        \Livewire\Livewire::withQueryParams(['source' => 'signups'])->test(\App\Filament\Pages\NewsletterList::class)->assertSee('signup@example.test');
        $this->assertTrue($reader->fresh()->mail_opt_out);
        $this->assertNotContains('one@example.test', NewsletterSender::recipients('readers')->pluck('email')->all());

        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(\App\Filament\Pages\NewsletterList::canAccess());
    }
}
