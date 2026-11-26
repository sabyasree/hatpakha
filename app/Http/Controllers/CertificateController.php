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
            image: self::pictureUrl($certificate) ?? ($post ? \App\Support\ShareImage::forPost($post) : null),
            shareTitle: $post ? $post->title.' · হাতপাখায় প্রকাশিত লেখার শংসাপত্র' : null,
        );

        return view('certificate', compact('certificate', 'seo'));
    }

    /** Where a certificate's own share picture lives (1200×630), once its writer has opened it. */
    public static function picturePath(Certificate $c): string
    {
        return 'media/og/cert-'.$c->code.'.jpg';
    }

    public static function pictureUrl(Certificate $c): ?string
    {
        return is_file(public_path(self::picturePath($c))) ? asset(self::picturePath($c)).'?v='.filemtime(public_path(self::picturePath($c))) : null;
    }

    /**
     * 2026-11-26 (owner: on a computer "it's just opening facebook… not creating any post"): Facebook lets a computer
     * post only a link, so the link's preview must show the certificate itself. The certificate is drawn in the writer's
     * browser (our fonts, Bengali shaping), sent here once, checked, and kept as the page's share picture.
     * Only the certificate's own writer (or an editor) may send it; only a real PNG of the certificate's size is kept.
     */
    public function picture(\Illuminate\Http\Request $request, string $code)
    {
        $c = Certificate::where('code', strtoupper($code))->firstOrFail();
        $user = $request->user();
        abort_unless($c->isValid() && ($user?->author?->id === $c->author_id || $user?->hasRole(\App\Enums\Role::Editor)), 403);
        $data = (string) $request->input('png', '');
        abort_unless(str_starts_with($data, 'data:image/png;base64,') && strlen($data) < 6_000_000, 422);
        $bin = base64_decode(substr($data, 22), true);
        $info = $bin ? @getimagesizefromstring($bin) : false;
        abort_unless($info && $info[2] === IMAGETYPE_PNG && $info[0] >= 1200 && $info[0] <= 3400 && abs($info[0] / max(1, $info[1]) - 1600 / 1131) < 0.05, 422);
        $src = @imagecreatefromstring($bin);
        abort_unless($src, 422);
        // On a paper-coloured 1200×630 card, the whole certificate fitted to the height, centred.
        $out = imagecreatetruecolor(1200, 630);
        imagefill($out, 0, 0, imagecolorallocate($out, 0xFF, 0xFB, 0xF4));
        $h = 610;
        $w = (int) round($h * imagesx($src) / imagesy($src));
        imagecopyresampled($out, $src, (int) ((1200 - $w) / 2), 10, 0, 0, $w, $h, imagesx($src), imagesy($src));
        @mkdir(public_path('media/og'), 0755, true);
        imagejpeg($out, public_path(self::picturePath($c)), 86);
        imagedestroy($src);
        imagedestroy($out);

        return response()->json(['ok' => true]);
    }
}
