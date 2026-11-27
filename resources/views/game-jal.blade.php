{{-- D328: অক্ষরজাল. A 4×4 grid of অক্ষর; drag across touching tiles (or tap them one by one) to make words. Three
     minutes. Every word the grid holds is shown at the end. Runs in the browser (public/js/hp-jal.js). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $locked = ! empty($locked);
    $dict = \App\Support\Akshorjal::dictionary();
    $payload = ['date' => $game['date'], 'no' => $game['no'], 'grid' => $game['grid'], 'seconds' => \App\Support\Akshorjal::SECONDS, 'url' => Url::to('/khela/akshorjal'),
        // the day's words, so a find is checked at once; their tiles too, so a word is told apart from its letters
        'w' => base64_encode(json_encode(array_map(fn ($w) => [$w, count($dict[$w])], $game['words']), JSON_UNESCAPED_UNICODE))];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @unless ($locked) data-jal @endunless @auth data-score-url="{{ route('game.score') }}" data-score-token="{{ csrf_token() }}" @endauth>
        @include('partials.game-nav', ['on' => 'jal'])
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="font-hand text-2xl text-red">অক্ষরের জালে লুকোনো শব্দ</p>
                <h1 class="font-display text-4xl font-extrabold leading-tight">অক্ষরজাল</h1>
            </div>
            <p class="text-right text-sm font-bold text-ink2">খেলা নং {{ Bn::num((string) $game['no']) }}</p>
        </div>
        <p class="mt-2 text-ink2">পাশাপাশি, ওপর-নিচে বা কোনাকুনি ছুঁয়ে থাকা অক্ষরে আঙুল টেনে শব্দ বানান। তিন মিনিট। দুই অক্ষরের শব্দে ৫, তিনে ১০, চার বা তার বেশিতে ২০ পয়েন্ট।</p>

        @if ($locked)
            @include('partials.game-lock', ['what' => 'অক্ষরজাল', 'easy' => ['বানান-যাচাই', Url::to('/khela/banan')]])
        @else
            <div data-jal-intro class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
                <p class="text-lg">আজকের জালে অন্তত <b>{{ Bn::num((string) count($game['words'])) }}</b>টা শব্দ লুকিয়ে আছে। ঘড়ি চলবে শুরু টিপলে।</p>
                <ul class="mt-2 list-disc space-y-1 ps-6 text-ink2">
                    <li>প্রথম অক্ষরে আঙুল রেখে টেনে নিয়ে যান, শেষ অক্ষরে আঙুল তুলুন।</li>
                    <li>টানতে অসুবিধে হলে অক্ষরগুলো একটা একটা করে টিপুন, তারপর "জমা দিন"।</li>
                    <li>একই শব্দে একটা অক্ষর একবারই।</li>
                </ul>
                <button type="button" data-jal-go class="mt-4 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">শুরু করুন</button>
            </div>

            <div data-jal-board hidden class="mt-5">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-red px-4 py-1.5 text-lg font-extrabold text-white" data-jal-clock aria-live="off">৩:০০</span>
                    <span class="text-lg font-bold">পয়েন্ট <b class="text-2xl text-red" data-jal-points>০</b></span>
                </div>
                <p class="mt-3 flex h-14 items-center justify-center rounded-2xl bg-sky-tint font-serif text-[28px] font-bold text-sky-night" data-jal-word aria-live="polite"></p>
                <div class="relative mx-auto mt-3 aspect-square w-full max-w-[400px] touch-none select-none" data-jal-wrap>
                    <div class="grid h-full grid-cols-4 gap-2.5" data-jal-grid role="grid" aria-label="অক্ষরের জাল"></div>
                    <svg class="pointer-events-none absolute inset-0 h-full w-full" data-jal-line aria-hidden="true"></svg>
                </div>
                <p class="mt-2 min-h-6 text-center text-lg font-bold" role="status" aria-live="polite" data-jal-msg></p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" data-jal-clear class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white font-bold">মুছুন</button>
                    <button type="button" data-jal-submit class="flex h-12 items-center justify-center rounded-full bg-navy font-bold text-white">জমা দিন</button>
                </div>
                <h2 class="mt-4 font-bold">পাওয়া শব্দ <span data-jal-count></span></h2>
                <ul class="mt-2 flex flex-wrap gap-2" data-jal-found></ul>
                <button type="button" data-jal-stop class="mt-5 flex h-12 w-full items-center justify-center rounded-full border-2 border-navy bg-white font-bold">এখনই শেষ করুন</button>
            </div>

            <div data-jal-done hidden class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
                <p class="font-hand text-3xl text-red" data-jal-cheer></p>
                <p class="mt-1 text-2xl font-extrabold" data-jal-score></p>
                <p class="mt-1 text-lg" data-jal-sum></p>
                @include('partials.game-board-line')
                <h2 class="mt-4 font-display text-xl font-extrabold">আজকের জালের সব শব্দ</h2>
                <p class="text-sm text-ink2">গাঢ় রঙে আপনার পাওয়া, হালকায় বাকি থাকা।</p>
                <ul class="mt-2 flex flex-wrap gap-2" data-jal-all></ul>
                <div class="mt-4 grid gap-2">
                    <button type="button" data-jal-share class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">বন্ধুদের দেখান</button>
                    <a data-jal-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">WhatsApp-এ পাঠান</a>
                </div>
                <p class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-jal-share-msg></p>
                <p class="mt-4 font-bold">নতুন জাল আসবে রাত বারোটায়।</p>
            </div>
            <script type="application/json" data-jal-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
            <noscript><p class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-bold">খেলাটি চালাতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু থাকতে হবে।</p></noscript>
        @endif
        <details class="mt-6 rounded-2xl border-2 border-navy bg-white p-4">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between font-bold">কোন শব্দ গোনা হয়? <span aria-hidden="true">▾</span></summary>
            <p class="mt-2 text-ink2">রোজকার চেনা বাংলা শব্দ: জিনিস, কাজ, রং, খাবার, গাছপালা, সম্পর্ক। মানুষ বা জায়গার নাম গোনা হয় না। পশ্চিমবঙ্গ আর বাংলাদেশ দুই জায়গার চেনা বানানই চলে (যেমন পাখি ও পাখী)। খেলা শেষে জালের সব শব্দ দেখানো হয়।</p>
        </details>
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @unless ($locked)
        <script src="{{ asset('js/hp-after.js') }}?v=1" defer></script>
        <script src="{{ asset('js/hp-jal.js') }}?v=1" defer></script>
    @endunless
</x-layouts.app>
