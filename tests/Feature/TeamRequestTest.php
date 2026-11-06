<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Ai\TeamJobs;
use App\Enums\Role;
use App\Filament\Pages\AiTeam;
use App\Models\SocialPost;
use App\Models\TeamRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D72: the owner gives the AI team work by hand: now, again, or in their own words. */
class TeamRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
    }

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    public function test_a_member_job_waits_for_the_scheduler_then_runs_and_can_be_redone(): void
    {
        Claude::$fake = fn () => ['facebook' => 'f', 'whatsapp' => 'w', 'instagram' => 'i', 'plan' => ['সোমবার: কবিতা']];
        Livewire::test(AiTeam::class)->callAction('give', ['job' => 'weekly', 'redo' => false], ['agent' => 'marketing'])->assertHasNoActionErrors();
        $r = TeamRequest::sole();
        $this->assertSame('queued', $r->status, 'Nothing runs inside the web request');

        $this->assertSame(1, TeamJobs::runQueued());
        $this->assertSame('done', $r->fresh()->status);
        $this->assertSame(1, SocialPost::where('kind', 'weekly')->count());

        Livewire::test(AiTeam::class)->call('again', $r->id)->assertSee('আপনার দেওয়া কাজ');
        TeamJobs::runQueued();
        $this->assertSame(2, SocialPost::where('kind', 'weekly')->count(), 'Redo makes a new one even though one exists');
    }

    public function test_own_words_are_routed_to_a_job_or_answered_in_writing(): void
    {
        Livewire::test(AiTeam::class)->callAction('ask', ['agent' => null, 'instruction' => 'ভাইফোঁটা নিয়ে একটা পোস্ট'])->assertHasNoActionErrors();
        Claude::$fake = fn ($purpose) => $purpose === 'chief'
            ? ['agent' => 'marketing', 'job' => 'custom', 'post' => '', 'topic' => 'ভাইফোঁটার শুভেচ্ছা']
            : ['facebook' => 'ভাইফোঁটার শুভেচ্ছা', 'whatsapp' => 'w', 'instagram' => 'i'];
        TeamJobs::runQueued();
        $r = TeamRequest::sole();
        $this->assertSame(['done', 'marketing', 'custom'], [$r->status, $r->agent, $r->job]);
        $this->assertTrue(SocialPost::where('kind', 'custom')->where('title', 'ভাইফোঁটার শুভেচ্ছা')->exists());

        TeamJobs::queue(null, null, 'শীতের সংখ্যার জন্য ১০টি বিষয়');
        Claude::$fake = fn ($purpose) => $purpose === 'chief'
            ? ['agent' => 'editor', 'job' => 'answer', 'post' => '', 'topic' => '']
            : ['answer' => "১. কুয়াশা\n২. পৌষমেলা"];
        TeamJobs::runQueued();
        $this->assertSame("১. কুয়াশা\n২. পৌষমেলা", TeamRequest::latest('id')->first()->result);
        Livewire::test(AiTeam::class)->assertSee('পৌষমেলা')->assertSee('আবার করুন');
    }

    public function test_paused_member_does_not_work_and_nothing_runs_twice(): void
    {
        \App\Ai\Team::setPaused('marketing', true);
        TeamJobs::queue('marketing', 'daily');
        TeamJobs::runQueued();
        $r = TeamRequest::sole();
        $this->assertSame('failed', $r->status);
        $this->assertStringContainsString('থামানো', $r->result);
        $this->assertSame(0, TeamJobs::runQueued(), 'A finished request is never picked up again');
    }
}
