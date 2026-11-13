<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Author;
use App\Models\Certificate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** D71: the certificate itself, issued from the post page, mailed to the writer, withdrawable. */
class CertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_issues_from_the_post_page_writer_is_told_and_sees_the_certificate(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $writer = User::factory()->create(['email_verified_at' => now()]);
        $author = Author::create(['user_id' => $writer->id, 'display_name' => 'মেঘলা দাস', 'slug' => 'meghla']);
        $post = $this->makePost(['status' => 'published', 'slug' => 'sondhya', 'title' => 'সন্ধ্যার নদী', 'published_at' => now()->subDay()], $author);

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->callAction('issueCertificate')->assertHasNoActionErrors();

        $c = Certificate::sole();
        $this->assertSame('মেঘলা দাস', $c->author_name_snapshot);
        if (\App\Support\NewsletterSender::realMail()) {
            Notification::assertSentTo($writer, \App\Notifications\TemplateMail::class, fn ($n) => $n->key === 'certificate_ready' && $n->url === $c->verifyUrl());
        }
        $this->assertSame([], Certificate::issueFor($post->fresh()), 'Never twice');

        $this->get($c->verifyUrl())->assertOk()->assertSee('প্রকাশনা শংসাপত্র')->assertSee('মেঘলা দাস')->assertSee('সন্ধ্যার নদী')
            ->assertSee('ISSN 2321-3035')->assertSee($c->code)->assertSee('ছবি নামান (PNG)')->assertSee('js/hp-cert.js', false);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->callAction('revokeCertificate');
        $this->get($c->verifyUrl())->assertOk()->assertSee('বাতিল')->assertDontSee('ছবি নামান (PNG)');
    }

    public function test_no_certificate_for_the_desk_byline_or_unpublished_pieces(): void
    {
        $desk = Author::create(['display_name' => 'হাতপাখা ডেস্ক', 'slug' => 'desk', 'is_desk' => true]);
        $this->assertSame([], Certificate::issueFor($this->makePost(['status' => 'published', 'slug' => 'd', 'published_at' => now()->subDay()], $desk)));
        $this->assertSame([], Certificate::issueFor($this->makePost(['status' => 'draft', 'slug' => 'e'])));

        // Owner (2026-11-12): the desk's own pieces (classics, desk drafts) and editorials get none; a guest writer's piece does.
        $sukumar = Author::create(['display_name' => 'সুকুমার রায়', 'slug' => 'sukumar', 'is_guest' => true]);
        $classic = $this->makePost(['status' => 'published', 'slug' => 'abol', 'published_at' => now()->subDay()], $sukumar);
        $classic->forceFill(['ai_drafted_at' => now()->subWeek()])->saveQuietly();
        $this->assertSame([], Certificate::issueFor($classic->fresh()));
        $edSection = \App\Models\Section::firstOrCreate(['slug' => 'editorial'], ['name_bn' => 'সম্পাদকীয়', 'format' => 'prose']);
        $owner = Author::create(['display_name' => 'সম্পাদক', 'slug' => 'ed']);
        $this->assertSame([], Certificate::issueFor($this->makePost(['status' => 'published', 'slug' => 'sompadokiyo', 'section_id' => $edSection->id, 'published_at' => now()->subDay()], $owner)));
        $guest = Author::create(['display_name' => 'অতিথি', 'slug' => 'otithi', 'is_guest' => true]);
        $this->assertCount(1, Certificate::issueFor($this->makePost(['status' => 'published', 'slug' => 'g', 'published_at' => now()->subDay()], $guest)));

        // Those already given to the desk's pieces are withdrawn in one click (and can be brought back one by one).
        $wrong = Certificate::create(['author_id' => $sukumar->id, 'post_id' => $classic->id, 'kind' => 'publication', 'title_snapshot' => 't', 'author_name_snapshot' => 'সুকুমার রায়']);
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\Certificates::class)->assertActionVisible('withdrawDesk')->callAction('withdrawDesk');
        $this->assertNotNull($wrong->fresh()->revoked_at);
        $this->assertSame(1, Certificate::whereNull('revoked_at')->count(), 'The guest writer keeps theirs');
    }

    /** D73: automatic for new pieces, a writer's own request, four designs, QR code, signature. */
    public function test_automatic_issue_only_for_new_pieces_and_writer_can_ask_for_an_old_one(): void
    {
        $writer = User::factory()->create(['email_verified_at' => now()]);
        $author = Author::create(['user_id' => $writer->id, 'display_name' => 'রবি সেন', 'slug' => 'robi']);
        $old = $this->makePost(['status' => 'published', 'slug' => 'old', 'title' => 'পুরনো লেখা', 'published_at' => now()->subYears(3)], $author);
        $new = $this->makePost(['status' => 'published', 'slug' => 'new', 'title' => 'নতুন লেখা', 'published_at' => now()->addMinute()], $author);
        $this->travel(2)->minutes();

        $this->assertSame(1, Certificate::autoIssue(), 'Only the piece published after the switch');
        $this->assertSame($new->id, Certificate::sole()->post_id);

        $this->actingAs($writer)->get('/account/writings/')->assertOk()->assertSee('শংসাপত্র চাই');
        $made = $this->actingAs($writer)->post('/account/certificate/'.$old->id.'/');
        $this->assertTrue(Certificate::where('post_id', $old->id)->exists(), 'Automatic on: given at once');
        $made->assertRedirect(Certificate::where('post_id', $old->id)->sole()->verifyUrl());   // owner: "whats next?" - straight to it
        $this->followRedirects($made)->assertSee('আপনার সার্টিফিকেট তৈরি হয়েছে')->assertSee('PDF / প্রিন্ট')->assertSee('WhatsApp');

        \App\Models\Setting::put('certificates.auto', '0');
        $other = User::factory()->create(['email_verified_at' => now()]);
        $p3 = $this->makePost(['status' => 'published', 'slug' => 'p3', 'published_at' => now()->subYear()],
            Author::create(['user_id' => $other->id, 'display_name' => 'মিতা', 'slug' => 'mita']));
        $this->actingAs($other)->post('/account/certificate/'.$p3->id.'/')->assertRedirect();
        $this->assertFalse(Certificate::where('post_id', $p3->id)->exists(), 'Automatic off: a request for the owner');
        $this->assertSame(1, \App\Models\EditorTask::open('certificate_request')->count());
        $this->actingAs($writer)->post('/account/certificate/'.$p3->id.'/')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\Certificates::class)->assertSee('লেখকদের অনুরোধ')
            ->call('approveRequest', \App\Models\EditorTask::open('certificate_request')->value('id'));
        $this->assertTrue(Certificate::where('post_id', $p3->id)->exists());
    }

    public function test_four_designs_with_qr_and_signature(): void
    {
        $author = Author::create(['display_name' => 'অতিথি লেখক', 'slug' => 'guest', 'is_guest' => true]);
        $issue = \App\Models\Issue::create(['title' => 'শারদীয়া ১৪৩৩', 'slug' => 'sharodiya-1433', 'status' => 'published', 'published_at' => now()->subDay()]);
        $inIssue = $this->makePost(['status' => 'published', 'slug' => 'i', 'published_at' => now()->subDay(), 'issue_id' => $issue->id], $author);
        $plain = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()->subDay()], $author);
        $a = Certificate::issueFor($inIssue)[0];
        $b = Certificate::issueFor($plain)[0];
        $c = Certificate::create(['author_id' => $author->id, 'post_id' => $plain->id, 'kind' => 'contest', 'place' => 2, 'title_snapshot' => 't', 'author_name_snapshot' => 'অতিথি লেখক', 'issue_label' => 'বর্ষার গল্প']);
        $h = Certificate::create(['author_id' => $author->id, 'kind' => 'honour', 'title_snapshot' => 'মাসের লেখক', 'author_name_snapshot' => 'অতিথি লেখক']);
        $this->assertSame(['issue', 'publication', 'contest', 'honour'], [$a->theme(), $b->theme(), $c->theme(), $h->theme()]);

        $this->get($a->verifyUrl())->assertOk()->assertSee('সংখ্যায় প্রকাশের শংসাপত্র')->assertSee('শারদীয়া ১৪৩৩')->assertSee('<svg', false);
        $this->get($c->verifyUrl())->assertOk()->assertSee('প্রতিযোগিতার শংসাপত্র')->assertSee('দ্বিতীয়')->assertSee('বর্ষার গল্প');
        $this->get($h->verifyUrl())->assertOk()->assertSee('সম্মাননা শংসাপত্র')->assertSee('মাসের লেখক');
        $this->get($b->verifyUrl())->assertOk()->assertSee('প্রকাশনা শংসাপত্র')->assertSee('সম্পাদক, হাতপাখা');

        // Signature: re-encoded PNG, white made transparent, shown with the signer's name.
        $img = imagecreatetruecolor(300, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imageline($img, 10, 50, 290, 60, imagecolorallocate($img, 0, 0, 0));
        $tmp = tempnam(sys_get_temp_dir(), 'sig');
        imagejpeg($img, $tmp);
        $path = \App\Support\Signature::store($tmp);
        $this->assertFileExists(public_path($path));
        $png = imagecreatefrompng(public_path($path));
        $this->assertSame(127, imagecolorsforindex($png, imagecolorat($png, 5, 5))['alpha'], 'Paper is transparent');
        \App\Models\Setting::put('certificates.signature', $path);
        \App\Models\Setting::put('certificates.signer_name', 'সম্পাদকের নাম');
        $this->get($b->verifyUrl())->assertSee('সম্পাদকের নাম')->assertSee($path);
        @unlink(public_path($path));

        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        Filament::setCurrentPanel(Filament::getPanel('desk'));
        Livewire::test(\App\Filament\Pages\Certificates::class)->assertOk()->assertSee('শংসাপত্রের ধরন')->assertSee('সম্মাননা শংসাপত্র')
            ->call('withdraw', $b->id);
        $this->assertNotNull($b->fresh()->revoked_at);
        Livewire::test(\App\Filament\Pages\Certificates::class)->call('restore', $b->id);
        $this->assertNull($b->fresh()->revoked_at);
        Livewire::test(EditPost::class, ['record' => $plain->getRouteKey()])->assertSee('শংসাপত্রের পাতা খুলুন');
    }
}
