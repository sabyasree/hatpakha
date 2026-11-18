<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\Handover;
use App\Support\ReadingHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** D237 (owner: "add the 8 feature recommendations... make sure all are user friendly"). */
class MoreForReadersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function docx(string $body, string $fonts = ''): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$fonts.$body.'</w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'লেখা.docx', null, null, true);
    }

    public function test_a_word_file_is_read_into_the_form_and_bad_files_are_refused_in_plain_words(): void
    {
        $writer = User::factory()->create(['role' => Role::Writer, 'email_verified_at' => now()]);
        $this->post('/submit/word/', ['word' => $this->docx('<w:p/>')])->assertRedirect();   // not for guests
        $this->actingAs($writer);
        $this->get('/submit/')->assertOk()->assertSee('ওয়ার্ড ফাইল থেকে লেখা আনুন')->assertSee('১৪ দিনের মধ্যে');

        $p = fn (string $runs) => '<w:p>'.$runs.'</w:p>';
        $r = fn (string $text, string $props = '') => '<w:r>'.($props ? '<w:rPr>'.$props.'</w:rPr>' : '').'<w:t xml:space="preserve">'.$text.'</w:t></w:r>';
        $json = $this->postJson('/submit/word/', ['word' => $this->docx(
            $p($r('জানলার কাচে ').$r('বৃষ্টির', '<w:b/>').$r(' দাগ,').'<w:r><w:br/></w:r>'.$r('উঠোনে শাড়ি।', '<w:i/>'))
            .$p('').$p('').$p($r('মা ডাকছেন &lt;script&gt;alert(1)&lt;/script&gt;'))
            .$p($r('মোটা নয়', '<w:b w:val="0"/>')).$p(''))])->assertOk()->json();
        $this->assertSame('<p>জানলার কাচে <strong>বৃষ্টির</strong> দাগ,<br>উঠোনে শাড়ি।</p><p><br></p><p>মা ডাকছেন &lt;script&gt;alert(1)&lt;/script&gt;</p><p>মোটা নয়</p>',
            str_replace(['<br />', '<em>', '</em>'], ['<br>', '', ''], $json['html']));
        $this->assertStringContainsString('<em>উঠোনে শাড়ি।</em>', $json['html']);
        $this->assertSame(3, $json['paragraphs']);

        $this->postJson('/submit/word/', ['word' => UploadedFile::fake()->create('lekha.pdf', 20)])->assertStatus(422)->assertJsonValidationErrors('word');
        $this->postJson('/submit/word/', ['word' => UploadedFile::fake()->create('lekha.docx', 20)])->assertStatus(422);   // not a real Word file
        $this->postJson('/submit/word/', ['word' => $this->docx($p(''))])->assertStatus(422);   // nothing written in it
        $old = $this->postJson('/submit/word/', ['word' => $this->docx($p($r('Avgvi †mvbvi evsjv')), '<w:p><w:r><w:rPr><w:rFonts w:ascii="SutonnyMJ" w:hAnsi="SutonnyMJ"/></w:rPr></w:r></w:p>')])->assertStatus(422)->json('errors.word.0');
        $this->assertStringContainsString('বিজয়', $old, 'An old-font file is explained, never pasted as nonsense');
    }

    public function test_a_reader_sees_their_reading_run_and_can_wipe_it(): void
    {
        $reader = User::factory()->create(['email_verified_at' => now()]);
        $post = $this->makePost(['status' => 'published', 'slug' => 'p', 'published_at' => now()->subDays(9)]);
        foreach (['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-16', '2026-11-17'] as $day) {
            Carbon::setTestNow(Carbon::parse($day.' 21:00', 'Asia/Kolkata'));
            ReadingHistory::record($reader, $post, 5);    // opened and left: not a day of reading
            $this->assertFalse(ReadingHistory::streak($reader)['today']);
            ReadingHistory::record($reader, $post, 70);
            ReadingHistory::record($reader, $post, 90);   // twice in a day is one day
        }
        $this->assertSame(['now' => 2, 'best' => 3, 'today' => true], ReadingHistory::streak($reader));
        $this->actingAs($reader)->get('/account/reading/')->assertSee('টানা ২ দিন পড়ছেন')->assertSee('আপনার সেরা: টানা ৩ দিন');
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:00', 'Asia/Kolkata'));
        $this->assertSame(2, ReadingHistory::streak($reader)['now'], 'Alive until the end of the next day');
        $this->get('/account/reading/')->assertSee('আজ একটা লেখা পড়লেই টানা দিন গোনা চলবে।');
        Carbon::setTestNow(Carbon::parse('2026-11-19 09:00', 'Asia/Kolkata'));
        $this->assertSame(0, ReadingHistory::streak($reader)['now']);
        $this->get('/account/reading/')->assertSee('নতুন করে শুরু করুন')->assertDontSee('ভেঙে');   // never a scolding

        $quiet = User::factory()->create(['email_verified_at' => now(), 'reading_history' => false]);
        ReadingHistory::record($quiet, $post, 90);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('reading_days')->where('user_id', $quiet->id)->count(), 'Not kept for a reader who switched history off');
        ReadingHistory::clear($reader);
        $this->assertSame(0, ReadingHistory::streak($reader)['best']);
    }

    public function test_the_desk_hears_when_a_writer_has_waited_ten_days(): void
    {
        $this->makePost(['status' => 'pending', 'slug' => 'new', 'title' => 'নতুন লেখা', 'submitted_at' => now()->subDays(3)]);
        Handover::replyDue();
        $this->assertFalse(\App\Models\EditorTask::where('key', 'handover.reply_due')->where('status', 'open')->exists());
        $this->makePost(['status' => 'pending', 'slug' => 'old', 'title' => 'পুরনো জমা', 'submitted_at' => now()->subDays(11)]);
        Handover::replyDue();
        $flag = \App\Models\EditorTask::where('key', 'handover.reply_due')->where('status', 'open')->firstOrFail();
        $this->assertStringContainsString('পুরনো জমা', $flag->payload['errors'][0]);
        $this->assertCount(1, $flag->payload['errors']);
    }

    public function test_support_is_offered_only_when_the_owner_set_a_upi_address(): void
    {
        $this->get('/pashe-thakun/')->assertNotFound();
        $this->get('/')->assertDontSee('pashe-thakun');
        config(['services.support.upi' => 'hatpakha@okbank']);
        $this->get('/pashe-thakun/')->assertOk()->assertSee('hatpakha@okbank')->assertSee('upi://pay?pa=hatpakha%40okbank', false)->assertSee('নিজের ইচ্ছেমতো');
        $this->get('/')->assertSee('pashe-thakun');
        config(['services.support.upi' => 'not a upi <script>']);
        $this->get('/pashe-thakun/')->assertNotFound();
    }

    public function test_the_game_pages_carry_one_ad_below_the_game(): void
    {
        config(['services.adsense.slots' => ['list' => '6'], 'services.adsense.force' => true]);
        foreach (['/khela/', '/khela/dhadha/', '/khela/shobdojobdo/'] as $url) {
            $this->assertSame(1, substr_count($this->get($url)->assertOk()->getContent(), 'data-ad-slot="6"'), $url);
        }
    }
}
