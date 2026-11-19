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
     * D241: a guest tapped a feeling. It is kept in their session and they are sent to log in; back on the piece it is
     * recorded (PostController) and the card asks for a line. A POST with the form token: never a link that reacts.
     */
    public function later(\Illuminate\Http\Request $request, Post $post)
    {
        abort_unless($post->isLive(), 404);
        $path = parse_url($post->url(), PHP_URL_PATH).'#reactions';
        if ($request->user()) {
            Feelings::set($request->user(), $post, $request->string('feeling')->toString() ?: null);

            return redirect($post->url().'#reactions');
        }
        if (isset(Feelings::LIST[$request->input('feeling')])) {
            $request->session()->put('feel_later', ['post' => $post->id, 'feeling' => $request->input('feeling')]);
        }

        return redirect(Url::to('/login').'?next='.rawurlencode($path));
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
