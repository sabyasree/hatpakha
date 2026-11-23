<?php

namespace App\Http\Controllers;

use App\Models\ReaderEntry;
use App\Models\ReaderPrompt;
use App\Seo\Seo;
use App\Support\Kolom;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** D175: পাঠকের কলম. The week's theme with a one-minute form (no account needed), and the picks once published. */
class KolomController extends Controller
{
    public function index()
    {
        $current = ReaderPrompt::current();
        $past = ReaderPrompt::where('status', 'published')->latest('published_at')->limit(12)->with('picked')->get();
        $latest = $past->first();

        return view('kolom', self::extras($current) + ['current' => $current, 'past' => $past, 'latest' => $latest, 'prompt' => null, 'seo' => new Seo(
            title: 'পাঠকের কলম: এ সপ্তাহের বিষয়ে আপনিও লিখুন',
            description: 'প্রতি সপ্তাহে একটি বিষয়: ছয় শব্দের গল্প, না-পাঠানো চিঠি বা এক লাইনের স্মৃতি। এক মিনিটে লিখে পাঠান; সেরা দশটি রবিবার হাতপাখায় ছাপা হয়।',
            canonical: Url::to('/kolom'),
        )]);
    }

    public function show(string $slug)
    {
        $prompt = ReaderPrompt::where('slug', $slug)->firstOrFail();
        if ($prompt->status !== 'published') {
            return redirect(Url::to('/kolom'));   // open or being judged: the main page has the form
        }
        $prompt->load('picked');

        $current = ReaderPrompt::current();

        return view('kolom', self::extras($current) + ['current' => $current, 'past' => collect(), 'latest' => null, 'prompt' => $prompt, 'seo' => new Seo(
            title: 'পাঠকের কলম: '.$prompt->title,
            description: 'পাঠকের কলম: “'.$prompt->title.'” বিষয়ে পাঠকদের সেরা লেখা।',
            canonical: $prompt->url(),
        )]);
    }

    /**
     * D266 (owner: "make this section interesting"; two UX reviews): what the page shows around the form, all from real
     * records: how long is left, how many people wrote (only from ten up), and next Monday's theme from the fixed list.
     */
    private static function extras(?ReaderPrompt $current): array
    {
        $left = $current?->closes_at && $current->closes_at->isFuture() ? now()->diff($current->closes_at) : null;
        $writers = $current ? ReaderEntry::where('reader_prompt_id', $current->id)->distinct()->count('sender_hash') : 0;

        return [
            'leftText' => $left ? ($left->days ? \App\Support\Bn::num((string) $left->days).' দিন '.\App\Support\Bn::num((string) $left->h).' ঘণ্টা' : ($left->h ? \App\Support\Bn::num((string) $left->h).' ঘণ্টা' : \App\Support\Bn::num((string) max(1, $left->i)).' মিনিট')) : null,
            'writers' => $writers >= 10 ? $writers : null,
            'next' => Kolom::nextTheme(),
        ];
    }

    public function store(Request $request)
    {
        $prompt = ReaderPrompt::current();
        if (! $prompt || ! $prompt->isOpen()) {
            throw ValidationException::withMessages(['body' => 'এ সপ্তাহের লেখা নেওয়া শেষ। সোমবার নতুন বিষয় আসবে।']);
        }
        if (filled($request->input('website'))) {   // a field people never see: only robots fill it
            return redirect(Url::to('/kolom').'#form')->with('kolom_sent', true);
        }
        $data = $request->validate([
            'name' => [$request->user() ? 'nullable' : 'required', 'string', 'max:80'],
            'body' => ['required', 'string', 'min:8', 'max:'.$prompt->limit()],
            'anonymous' => ['nullable', 'boolean'],
            'agree' => ['accepted'],
        ], [
            'name.required' => 'আপনার নাম লিখুন (ছাপার সময় নাম গোপন রাখতে চাইলে নিচের ঘরে টিক দিন)।',
            'body.required' => 'লেখাটি লিখুন।',
            'body.min' => 'আর একটু লিখুন।',
            'body.max' => 'লেখাটি একটু বড় হয়ে গেছে: সর্বোচ্চ '.\App\Support\Bn::num((string) $prompt->limit()).' অক্ষর।',
            'agree.accepted' => 'লেখাটি আপনার নিজের, আর হাতপাখা ছাপতে পারে: এই ঘরে টিক দিন।',
        ]);
        $body = trim(preg_replace("/[ \t]+/u", ' ', preg_replace("/\R{3,}/u", "\n\n", strip_tags($data['body']))));
        // D266: a "one-line memory" stays a line or two; a six-word story is one line
        if ($prompt->kind === 'six') {
            $body = trim(preg_replace('/\s+/u', ' ', $body));
        }
        $lines = count(array_filter(preg_split('/\R/u', $body), fn ($l) => trim($l) !== ''));
        if ($lines > $prompt->maxLines()) {
            throw ValidationException::withMessages(['body' => $prompt->kind === 'memory'
                ? 'এক লাইনের স্মৃতি: এক-দুই লাইনে লিখুন।'
                : 'লেখাটি একটু ছোট করুন: সর্বোচ্চ '.\App\Support\Bn::num((string) $prompt->maxLines()).' লাইন।']);
        }
        if ($prompt->kind === 'six' && count(preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY)) > 8) {
            throw ValidationException::withMessages(['body' => 'ছয় শব্দের গল্প: ছয়টি শব্দের মধ্যে রাখুন।']);
        }
        if (preg_match('~https?://|www\.~i', $body)) {
            throw ValidationException::withMessages(['body' => 'লেখায় লিংক দেওয়া যায় না।']);
        }
        \App\Http\Controllers\Auth\AuthController::checkHuman($request, 'body', 'kolom');
        $hash = Kolom::senderHash($request);
        if (ReaderEntry::where('reader_prompt_id', $prompt->id)->where('sender_hash', $hash)->count() >= Kolom::PER_PERSON) {
            throw ValidationException::withMessages(['body' => 'এ সপ্তাহে আপনার '.\App\Support\Bn::num((string) Kolom::PER_PERSON).'টি লেখা পেয়ে গেছি। ধন্যবাদ! পরের সপ্তাহে আবার লিখবেন।']);
        }
        ReaderEntry::create([
            'reader_prompt_id' => $prompt->id, 'user_id' => $request->user()?->id,
            'name' => trim(strip_tags((string) ($data['name'] ?? ''))) ?: (string) $request->user()?->name,
            'anonymous' => $request->boolean('anonymous'), 'body' => $body, 'sender_hash' => $hash,
        ]);

        return redirect(Url::to('/kolom').'#form')->with('kolom_sent', true);
    }
}
