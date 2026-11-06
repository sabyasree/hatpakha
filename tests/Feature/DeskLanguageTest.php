<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D45: /desk in Bengali (default) or English, per user; every desk string has an English version. */
class DeskLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_desk_string_has_english(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true);
        $files = array_merge(
            glob(app_path('Filament/{*,*/*,*/*/*,*/*/*/*}.php'), GLOB_BRACE),
            [app_path('Ai/IssuePlanner.php'), app_path('Ai/EditorPackage.php')],
            glob(resource_path('views/filament/{*,*/*}.blade.php'), GLOB_BRACE),
        );
        $missing = [];
        foreach ($files as $f) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/u", file_get_contents($f), $m);
            foreach ($m[1] as $k) {
                $k = str_replace("\\'", "'", $k);
                if (preg_match('/[\x{0980}-\x{09FF}]/u', $k) && ! array_key_exists($k, $en)) {
                    $missing[] = basename($f).': '.$k;
                }
            }
        }
        $this->assertSame([], $missing, 'Add these to lang/en.json');
    }

    public function test_switching_language_changes_the_desk_for_that_user_only(): void
    {
        $owner = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($owner)->get('/desk-language/en')->assertRedirect();
        $this->assertSame('en', $owner->fresh()->desk_locale);

        app()->setLocale('en');
        \App\Support\Bn::$latin = true;
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\Dashboard::class)->assertSee('Waiting for your approval')->assertDontSee('আপনার অনুমোদনের অপেক্ষায়');
        $this->assertSame('Pieces', \App\Filament\Resources\Posts\PostResource::getNavigationLabel());
        $this->assertSame('12', \App\Support\Bn::num('12'));

        app()->setLocale('bn');
        \App\Support\Bn::$latin = false;
        $this->assertSame('লেখা', \App\Filament\Resources\Posts\PostResource::getNavigationLabel());
        $this->assertSame('১২', \App\Support\Bn::num('12'));
        $this->get('/desk-language/xx')->assertNotFound();
    }

    public function test_public_site_is_never_switched(): void
    {
        $this->makePost(['slug' => 'pub']);
        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'desk_locale' => 'en']))
            ->get('/pub/')->assertOk()->assertSee('পাঠকের মতামত');
    }
}
