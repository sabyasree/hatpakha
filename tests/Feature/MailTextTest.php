<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ContactReply;
use App\Notifications\WriterNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 2026-11-26 (owner): a reply written in the desk keeps its paragraphs and line breaks in the e-mail. */
class MailTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editors_paragraphs_and_line_breaks_reach_the_email_escaped(): void
    {
        $text = "অভিনন্দন! লেখা ফেসবুকে দেওয়া খুব সহজ:\n\n১. লেখার পাতা থেকে\nলেখার শেষে \"ফেসবুকে শেয়ার\" টিপুন।\n\n২. ফোনে থাকলে <b>শেয়ার</b> চিহ্ন টিপুন।";
        $user = User::factory()->create(['name' => 'Rina']);
        foreach ([(new WriterNote('বিষয়', $text, ''))->toMail($user), (new ContactReply('Rina', 'প্রশ্ন', $text))->toMail($user)] as $mail) {
            $html = (string) $mail->render();
            $this->assertStringContainsString('১. লেখার পাতা থেকে<br>লেখার শেষে', $html);
            $this->assertStringContainsString('&lt;b&gt;শেয়ার&lt;/b&gt;', $html, 'Escaped');
            $this->assertGreaterThanOrEqual(3, substr_count($html, 'অভিনন্দন') + substr_count($html, '১. লেখার') + substr_count($html, '২. ফোনে'));
            $this->assertStringNotContainsString('সহজ: ১. লেখার', $html, 'Not joined into one line');
        }
    }
}
