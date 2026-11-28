{{-- D328: বানান-যাচাই and এক শব্দে প্রকাশ. Five questions a day, four choices, one try; the rule or a note after each.
     Big buttons, no keyboard. Runs in the browser (public/js/hp-choice.js). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $locked = ! empty($locked);
    $banan = $kind === 'banan';
    $name = $banan ? 'বানান-যাচাই' : 'এক শব্দে প্রকাশ';
    $url = Url::to($banan ? '/khela/banan' : '/khela/ek-shobde');
    $payload = ['practice' => ! empty($practice)] + \App\Http\Controllers\GameController::shareFor(($kind === 'banan' ? 'banan' : 'ek-shobde'), $game['date']) + ['mode' => 'daily', 'game' => $kind, 'key' => $kind, 'id' => $game['date'], 'date' => $game['date'], 'no' => $game['no'], 'title' => 'হাতপাখা '.$name,
        'url' => $url, 'points' => \App\Support\WordChoice::POINTS, 'label' => $banan ? 'শব্দ' : 'প্রশ্ন', 'report' => true, 'showPrompt' => ! $banan,
        'items' => array_map(fn ($q) => ['p' => $banan ? 'কোন বানানটি ঠিক?' : $q['prompt'], 'a' => base64_encode($q['answer']), 'o' => $q['options'], 'n' => $q['note'],
            'm' => (($m = \App\Support\InMagazine::line($q['answer'])) ? base64_encode(json_encode($m, JSON_UNESCAPED_UNICODE)) : null)], $game['items'])];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @unless ($locked) data-choice @endunless @if (empty($practice)) @auth data-score-url="{{ route('game.score') }}" data-score-token="{{ csrf_token() }}" @endauth @endif>
        @include('partials.practice-banner')
        @include('partials.game-nav', ['on' => $kind])
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="font-hand text-xl text-red">{{ $banan ? 'রোজ পাঁচটা বানান' : 'রোজ পাঁচটা কথা, এক শব্দে' }}</p>
                <h1 class="font-display text-4xl font-extrabold leading-tight">{{ $name }}</h1>
            </div>
            <p class="text-right text-sm font-bold text-ink2">খেলা নং {{ Bn::num((string) $game['no']) }}</p>
        </div>
        <p class="mt-1 text-ink2">{{ $banan ? 'একটাই ঠিক; উত্তরের পরে নিয়ম দেখাবে।' : 'কথাটা এক শব্দে কী হবে, বেছে নিন।' }} ঠিক হলে ২০ পয়েন্ট।</p>

        @if ($locked)
            @include('partials.game-lock', ['what' => $name, 'easy' => ['বানান-যাচাই', Url::to('/khela/banan')]])
        @else
            @include('partials.choice-board', ['count' => count($game['items'])])
            <script type="application/json" data-c-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endif

        <details class="mt-6 rounded-2xl border-2 border-navy bg-white p-4">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between font-bold">কীভাবে খেলবেন <span aria-hidden="true">▾</span></summary>
            <ul class="mt-2 list-disc space-y-1 ps-6 text-ink2">
                @if ($banan)
                    <li>প্রতিটি শব্দের চারটে বানান দেখানো হয়, একটাই ঠিক।</li>
                    <li>ঠিক বানান মানে বাংলা একাডেমি (ঢাকা) আর পশ্চিমবঙ্গ বাংলা আকাদেমি দুই জায়গাতেই যা ঠিক। যে শব্দের বানান দুই জায়গায় আলাদা, সেটা এই খেলায় রাখা হয়নি।</li>
                @else
                    <li>একটা কথা দেখানো হয়, যেমন "যা আগে কখনো হয়নি"।</li>
                    <li>চারটে শব্দের মধ্যে যেটা পুরো কথাটা এক শব্দে বলে (অভূতপূর্ব), সেটায় টিপুন।</li>
                @endif
                <li>ঠিক হলে ২০ পয়েন্ট; ভুল হলে ঠিক উত্তরটা দেখানো হয়। একবারই সুযোগ।</li>
                <li>রোজ রাত বারোটায় (ভারতের সময়) নতুন পাঁচটা।</li>
            </ul>
        </details>
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @unless ($locked)
        <script src="{{ asset('js/hp-after.js') }}?v=5" defer></script>
        <script src="{{ asset('js/hp-report.js') }}?v=1" defer></script>
        <script src="{{ asset('js/hp-mag.js') }}?v=1" defer></script>
        <script src="{{ asset('js/hp-choice.js') }}?v=4" defer></script>
    @endunless
</x-layouts.app>
