<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Seo\Seo;

/** Public verification page for a printed/shared certificate. Shows no private data. */
class CertificateController extends Controller
{
    public function __invoke(string $code)
    {
        $certificate = Certificate::with(['author', 'post'])->where('code', strtoupper($code))->firstOrFail();
        // 2026-11-25 (owner: writers don't share their certificates): a shared link showed the site's general card and
        // "শংসাপত্র যাচাই · CODE". Now it shows the piece's own picture, its title and what the certificate is.
        $post = $certificate->post;
        $seo = new Seo(
            title: 'শংসাপত্র যাচাই · '.$certificate->code,
            description: $post ? '"'.$post->title.'" হাতপাখায় প্রকাশিত হয়েছে (ISSN 2321-3035)। এটি তার শংসাপত্র, এখানে যাচাই করা যায়।' : 'হাতপাখার দেওয়া শংসাপত্র, এখানে যাচাই করা যায়।',
            noindex: true,
            image: $post ? \App\Support\ShareImage::forPost($post) : null,
            shareTitle: $post ? $post->title.' · হাতপাখায় প্রকাশিত লেখার শংসাপত্র' : null,
        );

        return view('certificate', compact('certificate', 'seo'));
    }
}
