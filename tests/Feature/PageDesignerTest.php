<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Support\PageDesigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The magazine's page designer (owner, 2026-11-12): every picture placed properly, like a real magazine. */
class PageDesignerTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    private function picture(string $name, int $w, int $h): string
    {
        $dir = public_path('wp-content/uploads/test-pd');
        @mkdir($dir, 0775, true);
        $img = imagecreatetruecolor($w, $h);
        imagejpeg($img, $dir.'/'.$name);
        $this->files[] = $dir.'/'.$name;

        return 'wp-content/uploads/test-pd/'.$name;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        @rmdir(public_path('wp-content/uploads/test-pd'));
        parent::tearDown();
    }

    public function test_the_designer_chooses_how_each_piece_opens(): void
    {
        $wide = $this->picture('wide.jpg', 1200, 700);
        $tall = $this->picture('tall.jpg', 800, 1100);
        $long = '<p>'.str_repeat('নদীর ধারে বসে থাকি অনেকক্ষণ। ', 80).'</p>';

        $this->assertSame('hero', PageDesigner::design($this->makePost(['featured_image_path' => $wide, 'format' => 'prose', 'body_html' => $long]))['layout']);
        $this->assertSame('feature', PageDesigner::design($this->makePost(['featured_image_path' => $tall, 'format' => 'prose', 'body_html' => $long]))['layout']);
        $this->assertSame('vignette', PageDesigner::design($this->makePost(['featured_image_path' => $tall, 'format' => 'poem']))['layout']);
        $this->assertSame('hero', PageDesigner::design($this->makePost(['featured_image_path' => $wide, 'format' => 'poem']))['layout']);
        $this->assertSame('none', PageDesigner::design($this->makePost())['layout']);
        $this->assertSame('none', PageDesigner::design($this->makePost(['featured_image_path' => 'wp-content/uploads/missing.jpg']))['layout'], 'A missing file leaves no hole');
        $this->assertSame('ছবি: এআই-এর সাহায্যে আঁকা', PageDesigner::design($this->makePost(['featured_image_path' => $wide, 'image_is_ai' => true]))['credit']);
    }

    public function test_pictures_inside_the_text_are_placed_with_their_shape_and_caption(): void
    {
        $inside = $this->picture('ghat.jpg', 900, 600);
        $upright = $this->picture('door.jpg', 600, 900);
        $cover = $this->picture('cover.jpg', 1200, 700);
        $html = '<p>প্রথম অনুচ্ছেদ।</p><p><img src="/'.$inside.'" alt="ঘাট"></p>'
            .'<figure><img src="https://hatpakha.com/'.$upright.'" alt="দরজা"><figcaption>পুরোনো দরজা</figcaption></figure>'
            .'<p><img src="https://example.org/x.jpg"></p><iframe src="https://www.youtube.com/embed/x"></iframe>'
            .'<p><a href="/'.$cover.'"><img src="/wp-content/uploads/test-pd/cover-1024x597.jpg"></a></p><p>শেষ।</p>';
        $out = PageDesigner::body($html, '/'.$cover, $cover);

        $this->assertStringContainsString('<figure class="mag-fig mag-fig-wide"><img src="/wp-content/uploads/test-pd/ghat.jpg"', $out);
        $this->assertStringContainsString('aspect-ratio: 900 / 600', $out);
        $this->assertStringContainsString('class="mag-fig mag-fig-tall"', $out);
        $this->assertStringContainsString('<figcaption>পুরোনো দরজা</figcaption>', $out);
        $this->assertStringNotContainsString('example.org', $out, 'Other sites\' pictures are left out');
        $this->assertStringNotContainsString('iframe', $out);
        $this->assertStringNotContainsString('cover-1024x597', $out, 'The piece\'s own picture is not shown twice');
        $this->assertStringNotContainsString('<p><figure', $out);
        $this->assertNotFalse(simplexml_load_string('<div>'.$out.'</div>'), 'Valid XHTML for the e-book too');
    }

    public function test_the_magazine_and_ebook_carry_the_pictures(): void
    {
        $tall = $this->picture('feature.jpg', 800, 1100);
        $wide = $this->picture('inside.jpg', 1000, 600);
        $issue = Issue::create(['slug' => '2026-11', 'title' => 'অগ্রহায়ণ সংখ্যা', 'status' => 'published', 'published_at' => now()]);
        $this->makePost(['slug' => 'golpo', 'title' => 'দীর্ঘ গল্প', 'format' => 'prose', 'issue_id' => $issue->id, 'featured_image_path' => $tall,
            'body_html' => '<p>'.str_repeat('গল্পের কথা। ', 200).'</p><p><img src="/'.$wide.'" alt=""></p><p>শেষ।</p>']);

        $html = $this->get('/sonkha/2026-11/boi/')->assertOk()->getContent();
        $this->assertStringContainsString('data-kind="feature"', $html);
        $this->assertStringContainsString('src="/'.$tall.'"', $html);
        $this->assertStringContainsString('src="/'.$wide.'"', $html);

        $epub = \App\Support\IssueBook::epub($issue);
        $zip = new \ZipArchive;
        $zip->open($epub);
        $this->assertNotFalse($zip->locateName('OEBPS/images/p1.jpg'));
        $this->assertNotFalse($zip->locateName('OEBPS/images/p2.jpg'));
        $this->assertStringContainsString('images/p1.jpg', (string) $zip->getFromName('OEBPS/content.opf'));
        $zip->close();
        @unlink($epub);
    }
}
