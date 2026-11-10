<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\MailTemplatesPage;
use App\Models\User;
use App\Notifications\SubmissionDecision;
use App\Support\MailTemplates;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D123: every e-mail on one desk page: preview, the owner's own wording, back to the original. */
class MailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('desk'));

        return $u;
    }

    public function test_every_template_previews_with_sample_values(): void
    {
        foreach (array_keys(MailTemplates::all()) as $key) {
            $html = MailTemplates::preview($key);
            $this->assertDoesNotMatchRegularExpression('/\{[\x{0980}-\x{09FF}_]+\}/u', $html, "No unfilled placeholder in {$key}");
            $this->assertStringContainsString('হাতপাখা', $html, $key);
        }
    }

    public function test_the_owner_rewords_an_email_and_it_is_sent_that_way_then_resets(): void
    {
        $this->admin();
        $post = $this->makePost(['status' => 'changes', 'title' => 'বৃষ্টির দিন', 'review_note' => 'শেষ লাইনটা আবার দেখুন।']);
        $writer = User::factory()->create(['name' => 'মেঘলা']);

        Livewire::test(MailTemplatesPage::class)->assertOk()->assertSee('বদল চাওয়া')
            ->callAction('edit', ['subject' => 'নতুন বিষয়: {লেখা}', 'greeting' => 'প্রিয় {নাম}', 'body' => "প্রথম কথা।\n\n[বোতাম]\n\nশেষ কথা: {সম্পাদকের_কথা}", 'button' => 'খুলুন', 'salutation' => '— সম্পাদক'],
                arguments: ['key' => 'decision_changes']);

        $mail = (new SubmissionDecision($post, 'changes'))->toMail($writer);
        $this->assertSame('নতুন বিষয়: বৃষ্টির দিন', $mail->subject);
        $this->assertSame('প্রিয় মেঘলা', $mail->greeting);
        $this->assertSame(['প্রথম কথা।'], $mail->introLines);
        $this->assertSame(['শেষ কথা: শেষ লাইনটা আবার দেখুন।'], $mail->outroLines);
        $this->assertSame('খুলুন', $mail->actionText);

        Livewire::test(MailTemplatesPage::class)->callAction('reset', arguments: ['key' => 'decision_changes']);
        $this->assertSame('হাতপাখা: "বৃষ্টির দিন" — একটু বদল চাই', (new SubmissionDecision($post, 'changes'))->toMail($writer)->subject);
    }

    public function test_an_empty_placeholder_drops_its_paragraph(): void
    {
        $mail = MailTemplates::mail('decision_approved', ['{নাম}' => 'ক', '{লেখা}' => 'খ', '{প্রকাশ}' => 'লেখাটি এখন প্রকাশিত।', '{সম্পাদকের_কথা}' => ''], 'https://hatpakha.com/');
        $this->assertStringNotContainsString('সম্পাদকের কথা', implode(' ', $mail->introLines));
    }

    public function test_editors_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Editor]));
        $this->assertFalse(MailTemplatesPage::canAccess());
    }
}
