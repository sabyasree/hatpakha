<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D92: release tools. Backups are MySQL-only (checked by hand with a restore); the staging scrub is tested here. */
class ReleaseToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_scrub_hides_private_data_and_never_runs_on_the_live_site(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin, 'email' => 'owner@example.com']);
        $writer = User::factory()->create(['role' => Role::Writer, 'email' => 'writer@gmail.com']);
        ContactMessage::create(['kind' => 'contact', 'topic' => 'question', 'name' => 'ক', 'email' => 'x@gmail.com', 'body' => 'একটি প্রশ্ন আছে']);

        config(['app.url' => 'https://hatpakha.com']);
        $this->artisan('hatpakha:staging-scrub --force')->assertFailed();
        $this->assertSame('writer@gmail.com', $writer->fresh()->email);

        config(['app.url' => 'https://new.hatpakha.com', 'seo.indexable' => false]);
        $this->artisan('hatpakha:staging-scrub --force')->assertSuccessful();
        $this->assertSame('user'.$writer->id.'@example.invalid', $writer->fresh()->email);
        $this->assertSame('owner@example.com', $owner->fresh()->email, 'Staff can still log in');
        $this->assertSame(0, ContactMessage::count());
        $this->artisan('hatpakha:staging-scrub --force')->assertSuccessful();   // safe to run again
    }

    public function test_backup_skips_politely_where_there_is_no_mysql(): void
    {
        $this->artisan('hatpakha:backup-db')->expectsOutputToContain('skipped')->assertSuccessful();
    }
}
