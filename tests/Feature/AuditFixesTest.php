<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Issue;
use App\Models\Post;
use App\Models\Serial;
use App\Models\User;
use App\Support\Bn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** D272 (owner: "check all the things and flows; make sure all things are perfect"): the audit's fixes, each held. */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00', 'Asia/Kolkata'));
    }

    public function test_an_issue_is_public_only_from_its_day_and_a_special_issue_has_its_own_address(): void
    {
        $dec = Issue::create(['slug' => '2026-12', 'title' => 'বর্ষসেরা সংখ্যা', 'status' => 'published', 'kind' => 'monthly', 'published_at' => '2026-12-27 03:30:00']);
        $this->get('/sonkha/2026-12/')->assertNotFound();
        $this->get('/sitemap-issues.xml')->assertDontSee('/sonkha/2026-12/', false);
        $this->assertStringContainsString('প্রকাশ হবে ২৭ ডিসেম্বর', $dec->goesOutText());
        Issue::create(['slug' => 'phire-asha', 'title' => 'ফিরে আসা সংখ্যা', 'status' => 'published', 'kind' => 'festival', 'published_at' => '2026-11-20 03:30:00']);
        $this->get('/sonkha/phire-asha/')->assertOk()->assertSee('ফিরে আসা সংখ্যা');
        Carbon::setTestNow(Carbon::parse('2026-12-27 09:30', 'Asia/Kolkata'));
        $this->get('/sonkha/2026-12/')->assertOk();
    }

    public function test_dates_are_shown_as_india_sees_them_and_status_follows_the_date(): void
    {
        $this->assertSame('৫ ডিসেম্বর ২০২৬', Bn::date(Carbon::parse('2026-12-04 20:30:00', 'UTC')), '2am on the 5th in India');
        $p = $this->makePost(['slug' => 'later', 'status' => 'published', 'published_at' => '2026-12-05 03:30:00']);
        $this->assertSame('scheduled', $p->fresh()->status, 'Published for a later day waits as scheduled');
        $p->forceFill(['published_at' => '2026-11-20 03:30:00'])->save();
        $this->assertSame('published', $p->fresh()->status);
    }

    public function test_a_declined_lone_first_episode_frees_the_writer_to_propose_again(): void
    {
        $writer = User::factory()->create();
        $serial = Serial::create(['title' => 'নদীর ওপারে', 'status' => 'proposed', 'day' => 6, 'user_id' => $writer->id]);
        $first = $this->makePost(['slug' => 'p1', 'status' => 'pending', 'published_at' => null]);
        $first->forceFill(['serial_id' => $serial->id, 'episode_no' => 1, 'submitted_by' => $writer->id])->save();
        \App\Support\Review::reject($first->fresh(), User::factory()->create(['role' => Role::Admin]), 'এবার নয়');
        $this->assertSame('dropped', $serial->fresh()->status);
    }

    public function test_a_google_signed_in_writer_sets_a_password_without_an_old_one(): void
    {
        $u = User::factory()->create(['google_id' => 'g-1', 'email_verified_at' => now()]);
        $this->actingAs($u)->withSession(['signed_in_via' => 'google'])
            ->post('/account/password', ['password' => 'Notun-pass-2026x', 'password_confirmation' => 'Notun-pass-2026x'])->assertSessionHasNoErrors();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Notun-pass-2026x', $u->fresh()->password));
        $other = User::factory()->create();
        $this->actingAs($other)->withSession(['signed_in_via' => 'password'])
            ->post('/account/password', ['password' => 'Notun-pass-2026x', 'password_confirmation' => 'Notun-pass-2026x'])->assertSessionHasErrors('current_password');
    }

    public function test_a_piece_waiting_for_the_writer_can_go_out_without_the_word_changes(): void
    {
        $writer = User::factory()->create();
        $p = $this->makePost(['slug' => 'wait', 'status' => 'confirm', 'published_at' => null]);
        $p->forceFill(['submitted_by' => $writer->id, 'pending_edits' => ['edits' => [['original' => 'ক', 'replacement' => 'খ', 'kind' => 'word']], 'at' => '2026-11-20T03:30:00Z']])->save();
        \App\Ai\EditorPackage::publishWithoutWords($p->fresh(), User::factory()->create(['role' => Role::Admin]));
        $this->assertTrue($p->fresh()->isLive(), 'The planned day had passed: it goes out now');
        $this->assertNull($p->fresh()->pending_edits);
    }
}
