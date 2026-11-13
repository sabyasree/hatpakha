<?php

namespace Tests\Feature;

use App\Ai\Claude;
use App\Enums\Role;
use App\Models\EditorTask;
use App\Models\ReaderEntry;
use App\Models\ReaderPrompt;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\Kolom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** D175 (owner): পাঠকের কলম. The agents open, promote, screen and propose; the owner publishes. */
class KolomTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Claude::$fake = null;
        parent::tearDown();
    }

    private function entry(array $extra = []): array
    {
        return $extra + ['name' => 'মিতা সেন', 'body' => 'মা ডাকলেন। আমি ফিরিনি। এখন ফিরি।', 'agree' => '1'];
    }

    public function test_monday_opens_a_theme_and_makes_the_invitation_ready_for_social(): void
    {
        $p = Kolom::openWeek();
        $this->assertSame(['open', 'six', 'ছয় শব্দে মা'], [$p->status, $p->kind, $p->title]);
        $s = SocialPost::where('kind', 'kolom')->sole();
        $this->assertStringContainsString($p->url(), $s->texts['facebook']);
        $this->assertStringContainsString('ছয় শব্দে মা', $s->texts['instagram']);
        $this->assertSame($p->url(), \App\Filament\Pages\Marketing::link($s), 'Facebook links to the week\'s page');
        $this->assertSame('ready', $s->fresh()->status, 'Waits on the marketing page; goes out by itself only with the owner\'s automatic switch');

        $this->get('/kolom/')->assertOk()->assertSee('ছয় শব্দে মা')->assertSee('name="body"', false)->assertSee('পাঠান');
        $this->get('/')->assertSee('পাঠকের কলম')->assertSee('ছয় শব্দে মা');

        $second = Kolom::openWeek();
        $this->assertSame('closed', $p->fresh()->status);
        $this->assertSame('ছোটবেলার বন্ধুকে', $second->title, 'Themes go in order');
    }

    public function test_anyone_can_send_an_entry_but_nothing_shows_until_the_owner_publishes(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\PerRouteThrottle::class);   // the 6-a-minute limit is not what this checks
        $p = Kolom::openWeek();
        $this->post('/kolom/', $this->entry())->assertRedirect(url('/kolom').'/#form')->assertSessionHas('kolom_sent');
        $e = ReaderEntry::sole();
        $this->assertSame(['মিতা সেন', 'new', null], [$e->name, $e->status, $e->user_id]);
        $this->get('/kolom/')->assertDontSee('আমি ফিরিনি');

        $this->post('/kolom/', $this->entry(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/kolom/', $this->entry(['agree' => null]))->assertSessionHasErrors('agree');
        $this->post('/kolom/', $this->entry(['body' => 'এক দুই তিন চার পাঁচ ছয় সাত আট নয় দশ']))->assertSessionHasErrors('body');   // six words
        $this->post('/kolom/', $this->entry(['body' => 'দেখুন https://spam.example']))->assertSessionHasErrors('body');
        $this->post('/kolom/', $this->entry(['body' => '<script>alert(1)</script> মা আছেন পাশে']))->assertRedirect();
        $this->assertStringNotContainsString('<script', ReaderEntry::latest('id')->first()->body);
        $this->post('/kolom/', $this->entry(['website' => 'http://bot']))->assertRedirect();
        $this->assertSame(2, ReaderEntry::count(), 'The robot\'s entry is dropped quietly');

        $this->post('/kolom/', $this->entry(['body' => 'তৃতীয় লেখা মায়ের জন্য']))->assertRedirect();
        $this->post('/kolom/', $this->entry(['body' => 'চতুর্থ লেখা মায়ের জন্য']))->assertSessionHasErrors('body');   // three per person a week
    }

    public function test_the_ai_screens_and_proposes_ten_and_the_owner_publishes_them(): void
    {
        config(['ai.enabled' => true, 'ai.anthropic.key' => 'k']);
        $p = Kolom::openWeek();
        foreach (range(1, 12) as $i) {
            ReaderEntry::create(['reader_prompt_id' => $p->id, 'name' => 'পাঠক '.$i, 'body' => 'লেখা নম্বর '.$i, 'sender_hash' => 'h'.$i, 'anonymous' => $i === 12]);
        }
        Claude::$fake = fn ($purpose, $user) => ['entries' => ReaderEntry::all()->map(fn ($e) => ['id' => $e->id, 'ok' => $e->name !== 'পাঠক 1', 'score' => $e->name === 'পাঠক 12' ? 9 : 5, 'note' => 'ঠিক আছে'])->all()];
        $this->assertSame(12, Kolom::screen());
        $this->assertSame('flagged', ReaderEntry::where('name', 'পাঠক 1')->value('status'));

        Kolom::pickWeek();
        $this->assertSame('closed', $p->fresh()->status);
        $this->assertSame(10, $p->picked()->count());
        $this->assertSame('পাঠক 12', $p->picked()->first()->name, 'Best first');
        $this->assertSame(0, $p->entries()->where('status', 'picked')->where('name', 'পাঠক 1')->count(), 'A flagged entry is never proposed');
        $this->assertSame(1, EditorTask::open('kolom')->count());
        $this->get('/kolom/')->assertDontSee('লেখা নম্বর 12');   // not public yet (D42)
        $this->post('/kolom/', $this->entry())->assertSessionHasErrors('body');   // closed

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\ReaderEntries\Pages\ListReaderEntries::class)
            ->assertSee('লেখা নম্বর 12')->callAction('publish')->assertHasNoActionErrors();
        $this->assertSame('published', $p->fresh()->status);
        $this->assertSame(0, EditorTask::open('kolom')->count());
        $this->assertSame(2, SocialPost::where('kind', 'kolom')->count(), 'The invitation, then the results');

        auth()->logout();
        $this->get('/kolom/')->assertOk()->assertSee('লেখা নম্বর 12')->assertSee('নাম প্রকাশে অনিচ্ছুক')->assertDontSee('পাঠক 12')->assertDontSee('লেখা নম্বর 1”');
        $this->get($p->url())->assertOk()->assertSee('লেখা নম্বর 5')->assertSee('wa.me', false);
        $this->get('/sitemap-sections.xml')->assertSee('/kolom/', false);
    }

    public function test_without_ai_the_owner_still_gets_a_proposal_and_can_change_it(): void
    {
        config(['ai.enabled' => false]);
        $p = Kolom::openWeek();
        $a = ReaderEntry::create(['reader_prompt_id' => $p->id, 'name' => 'ক', 'body' => 'প্রথম লেখা এখানে', 'sender_hash' => 'a']);
        $b = ReaderEntry::create(['reader_prompt_id' => $p->id, 'name' => 'খ', 'body' => 'দ্বিতীয় লেখা এখানে', 'sender_hash' => 'b']);
        Kolom::pickWeek();
        $this->assertSame(2, $p->picked()->count());

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        \Livewire\Livewire::test(\App\Filament\Resources\ReaderEntries\Pages\ListReaderEntries::class)->callTableAction('reject', $b);
        Kolom::publish($p->fresh());
        auth()->logout();
        $this->get('/kolom/')->assertSee('প্রথম লেখা এখানে')->assertDontSee('দ্বিতীয় লেখা এখানে');
    }
}
