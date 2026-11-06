<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Author;
use App\Models\Certificate;
use App\Models\User;
use App\Notifications\WriterNote;
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
            Notification::assertSentTo($writer, WriterNote::class, fn ($n) => $n->link === $c->verifyUrl());
        }
        $this->assertSame([], Certificate::issueFor($post->fresh()), 'Never twice');

        $this->get($c->verifyUrl())->assertOk()->assertSee('প্রকাশনা সনদ')->assertSee('মেঘলা দাস')->assertSee('সন্ধ্যার নদী')
            ->assertSee('ISSN 2321-3035')->assertSee($c->code)->assertSee('ছবি নামান (PNG)')->assertSee('js/hp-cert.js', false);

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->callAction('revokeCertificate');
        $this->get($c->verifyUrl())->assertOk()->assertSee('বাতিল')->assertDontSee('ছবি নামান (PNG)');
    }

    public function test_no_certificate_for_the_desk_byline_or_unpublished_pieces(): void
    {
        $desk = Author::create(['display_name' => 'হাতপাখা ডেস্ক', 'slug' => 'desk', 'is_desk' => true]);
        $this->assertSame([], Certificate::issueFor($this->makePost(['status' => 'published', 'slug' => 'd', 'published_at' => now()->subDay()], $desk)));
        $this->assertSame([], Certificate::issueFor($this->makePost(['status' => 'draft', 'slug' => 'e'])));
    }
}
