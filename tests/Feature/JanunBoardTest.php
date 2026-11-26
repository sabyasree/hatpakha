<?php

namespace Tests\Feature;

use App\Ai\JanunDesk;
use App\Enums\Role;
use App\Filament\Pages\JanunBoard;
use App\Models\FestivalTime;
use App\Models\JanunPage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D309: the desk list of what the জেনে রাখুন job is doing (read-only). */
class JanunBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_sees_every_page_its_state_and_why(): void
    {
        Carbon::setTestNow('2026-11-27 06:00:00');
        JanunDesk::seed();
        JanunPage::where('key', 'probad')->update(['status' => 'scheduled', 'publish_at' => '2026-11-27 04:30:00']);
        JanunPage::where('key', 'saraswati-mantra')->update(['status' => 'held', 'check' => json_encode(['removed' => 2, 'mantras' => [['name' => 'প্রণাম মন্ত্র', 'kept' => false]]])]);
        JanunPage::where('key', 'bagdhara')->update(['tries' => 3]);
        FestivalTime::create(['festival' => 'kali', 'cycle' => 2027, 'label' => 'অমাবস্যা তিথি', 'day' => '2027-10-29', 'status' => 'held', 'note' => 'found on 0 page(s)']);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        Livewire::test(JanunBoard::class)->assertSee('জেনে রাখুন')->assertSee('বাংলা প্রবাদ-প্রবচন ও তার অর্থ')->assertSee('তারিখ ঠিক')
            ->assertSee('27 Nov, 10:00am')->assertSee('মন্ত্র বাদ (দুটি সূত্রে হুবহু মেলেনি): প্রণাম মন্ত্র')->assertSee('২টি অংশ যাচাইয়ে ভুল')
            ->assertSee('আর চেষ্টা হবে না')->assertSee('কালীপুজো')->assertSee('found on 0 page(s)');
        $this->assertSame('১', JanunBoard::getNavigationBadge());
        Carbon::setTestNow();
    }

    public function test_only_admins_see_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(JanunBoard::canAccess());
    }
}
