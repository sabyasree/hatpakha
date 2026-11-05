<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** docs/SECURITY.md §2: 2FA is mandatory for the panel; app or email codes (D20). */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_requires_2fa_and_offers_app_and_email(): void
    {
        $panel = Filament::getPanel('desk');

        $this->assertTrue($panel->isMultiFactorAuthenticationRequired());
        $providers = collect($panel->getMultiFactorAuthenticationProviders())->map(fn ($p) => $p::class)->values()->all();
        $this->assertContains(AppAuthentication::class, $providers);
        $this->assertContains(EmailAuthentication::class, $providers);
    }

    public function test_email_code_is_sent_to_the_user_and_is_rate_limited(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => Role::Editor]);
        $user->toggleEmailAuthentication(true);
        $email = collect(Filament::getPanel('desk')->getMultiFactorAuthenticationProviders())
            ->first(fn ($p) => $p instanceof EmailAuthentication);

        $this->assertTrue($user->fresh()->hasEmailAuthentication());
        $this->assertTrue($email->sendCode($user));
        Notification::assertSentTo($user, VerifyEmailAuthentication::class);

        $email->sendCode($user);
        $this->assertFalse($email->sendCode($user), 'Third code in a row must be refused.');
    }
}
