<?php

namespace Tests\Feature;

use App\Auth\LegacyWordPressPassword;
use App\Enums\Role;
use App\Models\Author;
use App\Models\User;
use App\Notifications\ResetPasswordBn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Phase 3a writer accounts (docs/SECURITY.md §2). */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function started(int $secondsAgo = 10): string
    {
        return Crypt::encryptString((string) (now()->timestamp - $secondsAgo));
    }

    public function test_register_login_logout_and_account_page(): void
    {
        $this->get('/register/')->assertOk()->assertSee('noindex', false);
        $this->post('/register/', ['name' => 'রিনা দাস', 'email' => 'Rina@Example.com', 'password' => 'kobita12345', 'password_confirmation' => 'kobita12345', 'terms' => '1', 'started' => $this->started()])
            ->assertRedirect(url('/account').'/');
        $user = User::where('email', 'rina@example.com')->first();
        $this->assertSame(Role::Reader, $user->role);
        // D166: a reader's "আমার" opens "আমার পড়া"; no writing pages until they write.
        $this->get('/account/')->assertRedirect(url('/account/reading').'/');
        $this->get('/account/reading/')->assertOk()->assertSee('নমস্কার, রিনা')->assertSee('আমার পড়া')->assertSee('পরে পড়ব')->assertDontSee('আমার লেখা</span>', false);

        $this->post('/logout/')->assertRedirect(url('/').'/');
        $this->get('/account/')->assertRedirect(url('/login'));
        $this->post('/login/', ['email' => 'rina@example.com', 'password' => 'wrong-pass1'])->assertSessionHasErrors('email');
        $this->post('/login/', ['email' => 'RINA@example.com', 'password' => 'kobita12345'])->assertRedirectContains('/account');
    }

    public function test_bots_are_stopped_by_honeypot_and_speed_check(): void
    {
        $base = ['name' => 'Bot', 'email' => 'bot@example.com', 'password' => 'kobita12345', 'password_confirmation' => 'kobita12345', 'terms' => '1'];
        $this->post('/register/', $base + ['website' => 'http://spam', 'started' => $this->started()])->assertSessionHasErrors('email');
        $this->post('/register/', $base + ['started' => $this->started(1)])->assertSessionHasErrors('email');
        $this->post('/register/', $base + ['started' => 'forged'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    }

    public function test_old_wordpress_password_logs_in_and_is_upgraded(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->forceFill(['password' => null, 'legacy_password' => '$P$6zzzz1234TCTAWDr01Il3OqCLyHfkl/'])->save();

        $this->post('/login/', ['email' => 'old@example.com', 'password' => 'secret'])->assertRedirect(url('/account').'/');
        $this->assertNull($user->fresh()->legacy_password);
    }

    public function test_password_reset_does_not_reveal_accounts_and_works(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'w@example.com']);
        $same = 'এই ইমেলে অ্যাকাউন্ট থাকলে';
        $this->post('/forgot-password/', ['email' => 'nobody@example.com'])->assertSessionHas('status', fn ($s) => str_contains($s, $same));
        $this->post('/forgot-password/', ['email' => 'w@example.com'])->assertSessionHas('status', fn ($s) => str_contains($s, $same));

        $token = null;
        Notification::assertSentTo($user, ResetPasswordBn::class, function ($n) use (&$token) { $token = $n->token; return true; });
        $this->post('/reset-password/', ['token' => $token, 'email' => 'w@example.com', 'password' => 'notunpass123', 'password_confirmation' => 'notunpass123'])
            ->assertRedirect(url('/login').'/');
        $this->post('/login/', ['email' => 'w@example.com', 'password' => 'notunpass123'])->assertRedirect(url('/account').'/');
    }

    public function test_account_lists_only_my_writings(): void
    {
        $me = User::factory()->create();
        $mine = $this->makePost(['slug' => 'mine', 'title' => 'আমার কবিতা', 'status' => 'pending']);
        $mine->authors()->sync([Author::create(['slug' => 'me', 'display_name' => 'Me', 'user_id' => $me->id])->id]);
        $this->makePost(['slug' => 'theirs', 'title' => 'অন্যের লেখা']);

        $this->actingAs($me)->get('/account/')->assertOk()->assertSee('আমার কবিতা')->assertSee('সম্পাদকের কাছে')->assertDontSee('অন্যের লেখা');
        $this->actingAs($me)->get('/account/writings/')->assertOk()->assertSee('আমার কবিতা')->assertSee('জমা পড়েছে')->assertDontSee('অন্যের লেখা');
    }

    public function test_every_post_status_has_a_bengali_label(): void
    {
        $this->assertEqualsCanonicalizing(\App\Models\Post::STATUSES, array_keys(\App\Filament\Resources\Posts\Tables\PostsTable::STATUS));
        $this->assertEqualsCanonicalizing(\App\Models\Post::STATUSES, array_keys(\App\Http\Controllers\AccountController::STATUS));
    }

    public function test_old_login_url_is_a_real_page_again(): void
    {
        $this->get('/login/')->assertOk()->assertSee('লগ-ইন');
    }

    /** Owner + UX review: a busy writer's account stays short: 20 rows a page, filters, search, separate settings. */
    public function test_busy_writer_gets_short_pages_filters_and_search(): void
    {
        $me = User::factory()->create(['email_verified_at' => now()]);
        $a = Author::create(['slug' => 'busy', 'display_name' => 'Busy', 'user_id' => $me->id]);
        foreach (range(1, 45) as $i) {
            $this->makePost(['slug' => 'p'.$i, 'title' => 'প্রকাশিত '.$i, 'status' => 'published', 'published_at' => now()->subDays($i)], $a);
        }
        $this->makePost(['slug' => 'fix', 'title' => 'ঠিক করার লেখা', 'status' => 'changes', 'review_note' => 'শেষটা ছোট করুন'], $a);
        $this->actingAs($me);

        $home = $this->get('/account/')->assertOk()->assertSee('আপনার কাজ আছে')->assertSee('শেষটা ছোট করুন')->assertSee('৪৫টি লেখা প্রকাশিত');
        $this->assertLessThan(4, substr_count($home->getContent(), 'প্রকাশিত ১'), 'Home shows only a few recent pieces');

        $page = $this->get('/account/writings/?status=published')->assertOk()->assertSee('আরও দেখুন (২৫টি বাকি)');
        $this->assertSame(20, substr_count($page->getContent(), 'data-row>'));
        $this->get('/account/writings/?status=published&page=3')->assertOk()->assertDontSee('আরও দেখুন');
        $this->get('/account/writings/?q='.urlencode('প্রকাশিত 7'))->assertOk()->assertSee('প্রকাশিত 7')->assertDontSee('প্রকাশিত 8');
        $this->get('/account/writings/?status=action')->assertSee('ঠিক করার লেখা')->assertDontSee('প্রকাশিত 1<');
        $this->get('/account/profile/')->assertOk()->assertSee('আপনার নাম (বাংলায়)')->assertSee('রাখুন')->assertSee('আরও কিছু যোগ করুন')->assertSee('aria-current="page"', false);
        $this->get('/account/settings/')->assertOk()->assertSee('পাসওয়ার্ড বদলান')->assertSee('সপ্তাহে একটা ই-মেল')->assertSee('লগ-আউট')->assertSee('id="letters"', false)->assertDontSee('প্রোফাইল রাখুন');
        $this->post('/account/newsletter/', ['newsletter' => 0])->assertRedirect(url('/account/settings').'/#letters');
    }

    /** D85: an admin looks at the site through a writer's eyes; look only, never as staff, logged, ends. */
    public function test_admin_views_the_site_as_a_writer_look_only(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'name' => 'Owner']);
        $writer = User::factory()->create(['role' => Role::Writer, 'name' => 'Busy Writer']);
        $author = Author::create(['display_name' => 'Busy Writer', 'slug' => 'busy-writer', 'user_id' => $writer->id]);
        $post = \App\Models\Post::create(['title' => 'Busy one', 'slug' => 'busy-one', 'status' => 'draft', 'body_html' => '<p>x</p>']);
        $post->authors()->attach($author->id, ['position' => 0]);
        $editor = User::factory()->create(['role' => Role::Editor]);

        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('desk'));
        $this->assertSame($writer->id, array_key_first(\App\Http\Middleware\ViewAs::choices()), 'The busiest writer comes first; staff are never offered');
        $this->assertArrayNotHasKey($editor->id, \App\Http\Middleware\ViewAs::choices());
        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->callAction('viewAs', ['user' => $writer->id])->assertHasNoActionErrors()->assertRedirect(url('/account').'/');
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.view_as', 'subject_id' => $writer->id, 'user_id' => $admin->id]);

        // Owner report: someone found by searching (not among the first 30) was "invalid".
        $quiet = User::factory()->create(['role' => Role::Reader, 'name' => 'Dr Quiet Reader']);
        User::factory()->count(31)->create(['role' => Role::Reader])->each(function (User $u, int $i) {
            $a = Author::create(['display_name' => 'W'.$i, 'slug' => 'w-'.$i, 'user_id' => $u->id]);
            $p = \App\Models\Post::create(['title' => 'P'.$i, 'slug' => 'p-'.$i, 'status' => 'draft', 'body_html' => '<p>x</p>']);
            $p->authors()->attach($a->id, ['position' => 0]);
        });
        $this->assertArrayNotHasKey($quiet->id, \App\Http\Middleware\ViewAs::choices());
        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->callAction('viewAs', ['user' => $quiet->id])->assertHasNoActionErrors();
        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->callAction('viewAs', ['user' => $writer->id])->assertHasNoActionErrors();

        $page = $this->get('/account/')->assertOk()->assertSee('Busy Writer')->assertSee('Busy one')->assertSee('চোখে দেখছেন')->assertSee('বন্ধ করুন');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->get('/account/writings/')->assertOk()->assertSee('Busy one');

        // Nothing can be changed as them
        $this->post('/account/profile/', ['name' => 'Hacked'])->assertRedirect();
        $this->post('/account/newsletter/', ['newsletter' => 0])->assertRedirect();
        $this->assertSame('Busy Writer', $writer->fresh()->name);
        $this->assertFalse((bool) $writer->fresh()->mail_opt_out);
        $this->postJson('/submit/autosave/', ['title' => 'x'])->assertForbidden();

        // The desk stays the admin's own
        $this->assertNotSame(403, $this->get('/desk')->status(), 'A writer would be refused; the admin is not');

        // Stop: back to the admin
        $this->post('/view-as/stop/')->assertRedirect('/desk');
        $this->get('/account/reading/')->assertOk()->assertSee('Owner')->assertDontSee('চোখে দেখছেন');

        // Never as staff; ends by itself after 30 minutes
        $this->assertNull(\App\Http\Middleware\ViewAs::start($editor));
        \App\Http\Middleware\ViewAs::start($writer);
        $this->travel(31)->minutes();
        $this->get('/account/reading/')->assertOk()->assertDontSee('চোখে দেখছেন')->assertSee('দেখা শেষ হয়েছে');
    }

    public function test_only_an_admin_can_view_as(): void
    {
        $writer = User::factory()->create(['role' => Role::Writer]);
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertNull(\App\Http\Middleware\ViewAs::start($writer));
        // a forged session flag does nothing for a non-admin
        $this->withSession(['view_as' => ['user' => $writer->id, 'by' => auth()->id(), 'at' => now()->timestamp]])
            ->get('/account/reading/')->assertOk()->assertDontSee('চোখে দেখছেন');
    }

    /** D94 (owner): once submitted or published, the writer can't change a piece; they ask the editor. */
    public function test_published_piece_is_locked_and_the_writer_asks_the_editor(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);
        $me = User::factory()->create(['role' => Role::Writer]);
        $author = Author::create(['display_name' => 'Rina', 'slug' => 'rina', 'user_id' => $me->id]);
        $post = $this->makePost(['status' => 'published', 'title' => 'মেঘ', 'slug' => 'megh', 'published_at' => now()->subDay(),
            'submitted_by' => $me->id, 'audio_path' => '/media/audio/x.mp3', 'audio_status' => 'approved'], $author);
        $this->actingAs($me);

        // the text can't be changed
        $this->post('/submit/'.$post->id.'/', ['title' => 'বদলানো', 'body' => 'নতুন'])->assertForbidden();
        $this->postJson('/submit/'.$post->id.'/autosave/', ['title' => 'বদলানো'])->assertForbidden();
        $this->assertSame('মেঘ', $post->fresh()->title);
        // nor the live reading
        $this->post('/account/audio/'.$post->id.'/', ['remove' => 1])->assertRedirect();
        $this->assertSame('approved', $post->fresh()->audio_status);
        $this->get('/account/writings/')->assertOk()->assertSee('বদল চাই')->assertDontSee('পাঠ সরান');
        $this->get('/submit/'.$post->id.'/')->assertOk()->assertSee('সম্পাদককে পাঠান');

        // ask instead
        $this->post('/account/change/'.$post->id.'/', ['what' => 'তৃতীয় লাইনে বানান ভুল'])->assertSessionHas('status');
        $m = \App\Models\ContactMessage::sole();
        $this->assertSame(['change', $me->id, $post->id], [$m->kind, $m->user_id, $m->post()?->id]);
        \Illuminate\Support\Facades\Notification::assertSentTo($admin, \App\Notifications\TemplateMail::class, fn ($n) => $n->key === 'contact_admin');
        $this->post('/account/change/'.$post->id.'/', ['what' => ''])->assertSessionHasErrors('what', null, 'change');
        foreach ([2, 3, 4] as $n) {
            $this->post('/account/change/'.$post->id.'/', ['what' => 'আরেকটি অনুরোধ '.$n]);
        }
        $this->assertSame(3, \App\Models\ContactMessage::count(), 'At most three open requests');

        // nobody else's piece
        $this->actingAs(User::factory()->create(['role' => Role::Writer]));
        $this->post('/account/change/'.$post->id.'/', ['what' => 'অন্যের লেখা বদলাও'])->assertNotFound();
    }
}
