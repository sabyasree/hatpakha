<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** D291: an old uploaded PDF opens only if a live piece links to it (a pirated Feluda book was the most visited file). */
class UploadedPdfTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = public_path('wp-content/uploads/test-pdf');
        File::ensureDirectoryExists($this->dir);
        file_put_contents($this->dir.'/Writer_Own_Piece.pdf', '%PDF-1.4 writer');
        file_put_contents($this->dir.'/Feluda-Samagra-Part-1.pdf', '%PDF-1.4 someone else');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_a_pdf_linked_from_a_live_piece_opens_and_any_other_is_gone(): void
    {
        $this->makePost(['slug' => 'sangeet', 'status' => 'published',
            'body_html' => '<p>পড়ুন <a href="/wp-content/uploads/test-pdf/Writer_Own_Piece.pdf">পিডিএফ</a></p>']);
        // Linked only from a removed piece: still gone.
        $this->makePost(['slug' => 'feluda-samagra', 'status' => 'archived',
            'body_html' => '<p><a href="/wp-content/uploads/test-pdf/Feluda-Samagra-Part-1.pdf">ডাউনলোড</a></p>']);

        $ok = $this->get('/wp-content/uploads/test-pdf/Writer_Own_Piece.pdf')->assertOk();
        $this->assertSame('application/pdf', $ok->headers->get('Content-Type'));
        $this->assertStringContainsString('noindex', (string) $ok->headers->get('X-Robots-Tag'));

        $this->get('/wp-content/uploads/test-pdf/Feluda-Samagra-Part-1.pdf')->assertStatus(410);
        $this->assertContains('2015/10/Feluda-Samagra-Part-1Banglaebooksclassics.blogspot.com_.pdf', \App\Http\Controllers\LegacyController::BLOCKED_PDFS);
        $this->get('/wp-content/uploads/test-pdf/missing.pdf')->assertNotFound();
        $this->get('/wp-content/uploads/test-pdf/..%2F..%2F..%2F.env.pdf')->assertNotFound();
    }

    public function test_the_web_server_sends_uploaded_pdfs_to_the_app_before_serving_real_files(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));
        $pdf = strpos($htaccess, 'RewriteRule ^wp-content/uploads/.+\.pdf$ index.php');
        $this->assertNotFalse($pdf);
        $this->assertLessThan(strpos($htaccess, 'RewriteCond %{REQUEST_FILENAME} !-f'), $pdf);
    }
}
