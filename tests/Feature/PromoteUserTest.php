<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_be_made_admin_from_the_console(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('hatpakha:role', ['email' => 'owner@example.com'])->assertSuccessful();

        $this->assertSame(Role::Admin, $user->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed']);
    }

    public function test_unknown_role_or_email_fails(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->artisan('hatpakha:role', ['email' => 'a@example.com', 'role' => 'god'])->assertFailed();
        $this->artisan('hatpakha:role', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
