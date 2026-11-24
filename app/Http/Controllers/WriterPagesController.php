<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Certificate;
use App\Models\Post;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Calls;
use App\Support\Slug;
use App\Support\Url;
use Illuminate\Http\Request;

/** Writer features (owner, 2026-11-24): the public calls page (লেখার ডাক) and a writer's one-page portfolio (লেখক-পরিচিতি). */
class WriterPagesController extends Controller
{
    /** /lekhar-dak/: coming issues, this week's পাঠকের কলম, open contests and the editor's wish list. Real data only. */
    public function calls()
    {
        $issues = Calls::issues();
        $kolom = Calls::kolom();
        $contests = Calls::contests();
        $wishlist = Calls::wishlist();
        $seo = new Seo(
            title: 'লেখার ডাক',
            description: 'হাতপাখায় এখন কী লেখা চাই: সামনের সংখ্যা ও তার শেষ তারিখ, এ সপ্তাহের পাঠকের কলম, চলতি প্রতিযোগিতা আর সম্পাদক যা খুঁজছেন।',
            canonical: Url::to('/lekhar-dak'),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['লেখার ডাক', Url::to('/lekhar-dak')]])],
        );

        return view('lekhar-dak', compact('seo', 'issues', 'kolom', 'contests', 'wishlist') + ['replyLine' => \App\Support\WriterJourney::replyLine()]);
    }

    /**
     * /lekhok-porichiti/{slug}/: a printable one-page portfolio (photo, bio, pieces with dates under ISSN 2321-3035,
     * certificates with their check links, honours, a QR code to the writer's page). Public but noindex.
     */
    public function profile(Request $request, string $slug)
    {
        $author = Author::where('slug', Slug::normalize($slug))->where('is_desk', false)->first();
        abort_unless($author, 404);
        $posts = Post::published()->whereHas('authors', fn ($q) => $q->whereKey($author->id))->with(['section', 'issue'])->latest('published_at')->limit(200)->get();
        abort_if($posts->isEmpty(), 404);
        $certificates = Certificate::whereNull('revoked_at')->where('author_id', $author->id)->with('post')->latest('issued_at')->limit(60)->get();
        $choices = $posts->whereNotNull('readers_choice')->sortByDesc('readers_choice')->values();
        $qr = rescue(fn () => self::qr($author->url()), null, false);

        return view('lekhok-porichiti', [
            'seo' => new Seo(title: 'লেখক-পরিচিতি: '.$author->publicName(), description: 'হাতপাখায় '.$author->publicName().'-এর লেখা, শংসাপত্র ও সম্মান।', canonical: Url::to('/lekhok-porichiti/'.rawurlencode($author->slug)), noindex: true),
            'author' => $author, 'posts' => $posts, 'certificates' => $certificates, 'choices' => $choices, 'qr' => $qr,
        ]);
    }

    /** The writer page's address as a navy QR code (inline SVG), like the certificates (D73). */
    private static function qr(string $url): string
    {
        $options = new \chillerlan\QRCode\QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class, 'outputBase64' => false, 'svgAddXmlHeader' => false,
            'drawLightModules' => false, 'connectPaths' => true, 'addQuietzone' => false,
        ]);
        $svg = (new \chillerlan\QRCode\QRCode($options))->render($url);

        return str_replace(['fill="#000"', '<svg '], ['fill="#13212C"', '<svg width="110" height="110" role="img" aria-label="লেখক-পাতার QR কোড" '], $svg);
    }
}
