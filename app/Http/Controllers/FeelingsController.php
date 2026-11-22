<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Seo\JsonLd;
use App\Seo\Seo;
use App\Support\Feelings;
use App\Support\Url;

/** D232: "পাঠকের পছন্দ": each month's readers' choice, and the pieces readers answered most, by how the piece left them. */
class FeelingsController extends Controller
{
    /** D237: "হাতপাখার পাশে থাকুন". Voluntary, by UPI, nothing is unlocked by it. Only when the owner has set a UPI address. */
    public function support()
    {
        $upi = trim((string) config('services.support.upi'));
        abort_unless(preg_match('/^[A-Za-z0-9._-]{2,80}@[A-Za-z0-9.-]{2,40}$/', $upi) === 1, 404);
        $seo = new Seo(title: 'হাতপাখার পাশে থাকুন', description: 'হাতপাখা পড়তে বা লিখতে কোনো খরচ নেই। ভালো লাগলে নিজের ইচ্ছেমতো সাহায্য করতে পারেন।', canonical: Url::to('/pashe-thakun'),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['পাশে থাকুন', Url::to('/pashe-thakun')]])]);

        // D238 (owner: "can I add QR to UPI"): the same payment link as a QR code, drawn here (the library the
        // certificates already use), so a reader on a computer can scan it with the phone's UPI app.
        $pay = 'upi://pay?'.http_build_query(['pa' => $upi, 'pn' => (string) (config('services.support.name') ?: 'Hatpakha'), 'cu' => 'INR', 'tn' => 'Hatpakha']);
        $qr = rescue(fn () => 'data:image/svg+xml;base64,'.base64_encode((new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
            'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class, 'outputBase64' => false, 'svgAddXmlHeader' => false, 'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M, 'addQuietzone' => true,
        ])))->render($pay)), null, true);

        return view('support', ['seo' => $seo, 'upi' => $upi, 'qr' => $qr, 'name' => trim((string) config('services.support.name')), 'pay' => $pay]);
    }

    /**
     * D241, D252: a reader without an account taps a feeling. Since D252 it is recorded at once (no sign-in): counted
     * once a phone by a random id the phone keeps in a cookie. With JavaScript the page updates in place; without it,
     * the reader is sent back to the card. A POST with the form token: never a link that reacts.
     */
    public function later(\Illuminate\Http\Request $request, Post $post)
    {
        abort_unless($post->isLive(), 404);
        $back = $post->url().'#reactions';
        if ($request->user()) {
            $d = Feelings::set($request->user(), $post, $request->string('feeling')->toString() ?: null);

            return $request->expectsJson() ? response()->json($d) : redirect($back);
        }
        $id = (string) $request->cookie('hp_dev');
        if (! preg_match('/^[a-f0-9]{32}$/', $id)) {
            $id = bin2hex(random_bytes(16));
        }
        $d = Feelings::guestSet($post, $id, (string) $request->ip(), $request->string('feeling')->toString() ?: null);
        $cookie = cookie('hp_dev', $id, 60 * 24 * 365 * 2, '/', null, null, true, false, 'Lax');
        if ($d === null) {
            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => 'আজ এই নেটওয়ার্ক থেকে অনেকে এই লেখায় সাড়া দিয়েছেন। কাল আবার চেষ্টা করুন।'], 429)->withCookie($cookie)
                : redirect($back)->withCookie($cookie);
        }

        return ($request->expectsJson() ? response()->json($d + ['guest' => true]) : redirect($back)->with('felt', (bool) $d['feeling']))->withCookie($cookie);
    }

    /** D252: a published piece's picture card, for anyone (no sign-in); nothing private is on it. Not indexed. */
    public function card(Post $post)
    {
        abort_unless($post->isLive(), 404);
        $by = $post->authors->map(fn ($a) => $a->publicName())->implode(', ');

        return view('share-card', ['post' => $post, 'by' => $by, 'seo' => new Seo(title: 'লেখার ছবি: '.$post->title, description: 'হাতপাখায় প্রকাশিত “'.$post->title.'”-এর ছবি।',
            canonical: Url::to('/chhobi/'.$post->id), noindex: true)]);
    }

    public function index()
    {
        $winners = Post::published()->with('authors')->whereNotNull('readers_choice')->orderByDesc('readers_choice')->limit(12)->get();
        $lists = collect(array_keys(Feelings::LIST))->mapWithKeys(fn ($k) => [$k => Feelings::top($k)])->filter(fn ($l) => $l->isNotEmpty());
        $seo = new Seo(title: 'পাঠকের পছন্দ', description: 'হাতপাখার পাঠকেরা কোন লেখা পড়ে কী অনুভব করেছেন: যে লেখা মন ছুঁয়েছে, ভাবিয়েছে, হাসিয়েছে, চমকে দিয়েছে, মনে থেকে গেছে।',
            canonical: Url::to('/pathoker-pochhondo'), noindex: $winners->isEmpty() && $lists->isEmpty(),
            jsonLd: [JsonLd::breadcrumbs([['হাতপাখা', Url::to('/')], ['পাঠকের পছন্দ', Url::to('/pathoker-pochhondo')]])]);

        return view('feelings', compact('winners', 'lists', 'seo'));
    }
}
