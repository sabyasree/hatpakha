<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\HealthReport as Page;
use App\Models\Comment;
use App\Models\User;
use App\Support\HealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Owner (2026-11-12): the weekly / monthly health report is a desk page, not an email. */
class HealthReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_reads_and_shows_what_waits_for_the_owner(): void
    {
        $this->travelTo(now('Asia/Kolkata')->startOfWeek()->addDays(3)->setTime(12, 0));
        $post = $this->makePost(['title' => 'শীতের চিঠি']);
        DB::table('post_views')->insert(['post_id' => $post->id, 'day' => now('Asia/Kolkata')->toDateString(), 'views' => 30, 'search' => 10, 'social' => 15, 'internal' => 5]);
        DB::table('post_views')->insert(['post_id' => $post->id, 'day' => now('Asia/Kolkata')->subWeek()->toDateString(), 'views' => 10]);
        Comment::create(['post_id' => $post->id, 'author_name' => 'পাঠক', 'body' => 'ভালো লাগল', 'status' => 'pending']);

        $r = HealthReport::build('week');
        $this->assertSame(30, $r['readers']['reads']);
        $this->assertSame(200, $r['readers']['change']);
        $this->assertSame(HealthReport::LOOK, $r['comments']['status']);
        $this->assertSame('মন্তব্য অনুমোদনের অপেক্ষায়', collect($r['todo'])->pluck('label')->first());
        $this->assertSame(10, HealthReport::build('week', 1)['readers']['reads']);

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        Livewire::test(Page::class)->assertSee('সাইটের স্বাস্থ্য')->assertSee('শীতের চিঠি')->assertSee('আপনার জন্য অপেক্ষায়')
            ->call('show', 'month', 0)->assertSet('period', 'month')->assertSee('এই মাস')
            ->call('show', 'week', 99)->assertSet('back', 24);
        $this->assertTrue(Page::canAccess());
    }

    public function test_only_admins_see_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(Page::canAccess());
    }
}
