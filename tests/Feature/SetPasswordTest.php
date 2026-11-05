<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_is_set_and_legacy_hash_and_2fa_cleared(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $user->forceFill(['legacy_password' => '$P$Bold', 'app_authentication_secret' => 'SECRET'])->save();

        $this->artisan('hatpakha:password', ['email' => 'owner@example.com', '--reset-2fa' => true])
            ->expectsQuestion('New password (hidden)', 'Bengali-poems-2026')
            ->expectsQuestion('Type it again', 'Bengali-poems-2026')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('Bengali-poems-2026', $user->password));
        $this->assertNull($user->legacy_password);
        $this->assertNull($user->app_authentication_secret);
    }

    public function test_mismatch_or_weak_password_changes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        $before = $user->password;

        $this->artisan('hatpakha:password', ['email' => 'a@example.com'])
            ->expectsQuestion('New password (hidden)', 'Bengali-poems-2026')
            ->expectsQuestion('Type it again', 'something-else-99')
            ->assertFailed();

        $this->artisan('hatpakha:password', ['email' => 'a@example.com'])
            ->expectsQuestion('New password (hidden)', 'short1')
            ->expectsQuestion('Type it again', 'short1')
            ->assertFailed();

        $this->assertSame($before, $user->fresh()->password);
    }
}
