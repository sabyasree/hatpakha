{{-- D328: অক্ষরজাল. A 4×4 grid of অক্ষর; drag across touching tiles (or tap them one by one) to make words. Three
     minutes. Every word the grid holds is shown at the end. Runs in the browser (public/js/hp-jal.js). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $locked = ! empty($locked);
    $dict = \App\Support\Akshorjal::dictionary($game['date']);
    $payload = ['practice' => ! empty($practice)] + \App\Http\Controllers\GameController::shareFor('akshorjal', $game['date']) + ['date' => $game['date'], 'no' => $game['no'], 'grid' => $game['grid'], 'seconds' => \App\Support\Akshorjal::SECONDS, 'url' => Url::to('/khela/akshorjal'),
        // the day's words, so a find is checked at once; their tiles too, so a word is told apart from its letters
        'w' => base64_encode(json_encode(array_map(fn ($w) => [$w, count($dict[$w])], $game['words']), JSON_UNESCAPED_UNICODE)),
        // D330: up to three of the grid's longer words as the magazine used them, shown at the end
        'mag' => collect($game['words'])->filter(fn ($w) => mb_strlen($w) >= 3)->take(8)->map(fn ($w) => ($m = \App\Support\InMagazine::line($w)) ? base64_encode(json_encode($m, JSON_UNESCAPED_UNICODE)) : null)->filter()->take(3)->values()->all()];
@endphp
<x-layouts.app :seo="$seo">
    <style>html[data-playing] [data-bottom-nav] { display: none; }
        @keyframes hpPop { 0% { transform: scale(1); } 40% { transform: scale(1.25); } 100% { transform: scale(1); } } .hp-pop { animation: hpPop .45s ease-out; }
        @media (prefers-reduced-motion: reduce) { .hp-pop { animation: none; } }</style>
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @unless ($locked) data-jal @endunless @if (empty($practice)) @auth data-score-url="{{ route('game.score') }}" data-score-token="{{ csrf_token() }}" @endauth @endif>
        @include('partials.practice-banner')
        @include('partials.game-nav', ['on' => 'jal'])
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="font-hand text-2xl text-red">অক্ষরের জালে লুকোনো শব্দ</p>
                <h1 class="font-display text-4xl font-extrabold leading-tight">অক্ষরজাল</h1>
            </div>
            <p class="text-right text-sm font-bold text-ink2">খেলা নং {{ Bn::num((string) $game['no']) }}</p>
        </div>
        <p class="mt-2 text-ink2">ছুঁয়ে থাকা অক্ষরে আঙুল টেনে শব্দ বানান। তিন মিনিট। দুই অক্ষরে ৫, তিনে ১০, চার বা বেশিতে ২০ পয়েন্ট।</p>

        @if ($locked)
            @include('partials.game-lock', ['what' => 'অক্ষরজাল', 'easy' => ['বানান-যাচাই', Url::to('/khela/banan')]])
        @else
            <div data-jal-intro class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
                <p class="text-lg">আজকের জালে <b>{{ Bn::num((string) count($game['words'])) }}</b>টা শব্দ লুকিয়ে আছে। শুরু টিপলে তিন মিনিটের ঘড়ি চলবে।</p>
                <button type="button" data-jal-go class="mt-3 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">শুরু করুন</button>
                <ul class="mt-3 list-disc space-y-1 ps-6 text-ink2">
                    <li>ছুঁয়ে থাকা অক্ষরগুলো পরপর টিপুন, তারপর "জমা দিন"। চাইলে আঙুল টেনেও বানাতে পারেন।</li>
                    <li>একই শব্দে একটা অক্ষর একবারই।</li>
                    <li>অন্য অ্যাপে গেলে বা ফোন এলে ঘড়ি থেমে থাকে।</li>
                </ul>
            </div>

            <div data-jal-board hidden class="mt-5">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-red px-4 py-1.5 text-lg font-extrabold text-white" data-jal-clock aria-live="off">৩:০০</span>
                    <span class="text-lg font-bold">পয়েন্ট <b class="text-2xl text-red" data-jal-points>০</b></span>
                </div>
                <p class="mt-2 flex h-11 items-center justify-center rounded-2xl bg-sky-tint font-serif text-[26px] font-bold text-sky-night" data-jal-word aria-live="polite"></p>
                {{-- small enough that the whole grid and its buttons fit a 360×640 phone (UX review) --}}
                <div class="relative mx-auto mt-2 aspect-square w-full max-w-[min(400px,52vh)] touch-none select-none" data-jal-wrap>
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
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <a data-jal-wa href="#" target="_blank" rel="noopener" class="flex h-12 flex-1 items-center justify-center rounded-full bg-navy px-5 font-bold text-white">WhatsApp-এ ফল পাঠান</a>
                    <button type="button" data-jal-share class="min-h-11 font-bold text-sky-night underline">বন্ধুদের দেখান</button>
                </div>
                @include('partials.game-board-line')
                <h2 class="mt-4 font-display text-xl font-extrabold">আজকের জালের সব শব্দ</h2>
                <p class="text-sm text-ink2">গাঢ় রঙে আপনার পাওয়া, হালকায় বাকি থাকা।</p>
                <ul class="mt-2 flex flex-wrap gap-2" data-jal-all></ul>
                <div data-jal-mag hidden class="mt-4"><h3 class="font-bold">এই শব্দগুলো হাতপাখার লেখায়</h3><div class="mt-1 grid gap-2" data-jal-mag-list></div></div>
                <p class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-jal-share-msg></p>
                <p class="mt-4 font-bold">নতুন জাল আসবে রাত বারোটায় (ভারতের সময়)।</p>
            </div>
            <script type="application/json" data-jal-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
            <noscript><p class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-bold">খেলাটি চালাতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু থাকতে হবে।</p></noscript>
        @endif
        <details class="mt-6 rounded-2xl border-2 border-navy bg-white p-4">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between font-bold">কোন শব্দ গোনা হয়? <span aria-hidden="true">▾</span></summary>
            <p class="mt-2 text-ink2">চেনা বাংলা শব্দ, ক্রিয়ার চেনা রূপ (খেলাম, যাবে) আর বাংলায় চলা ইংরেজি শব্দও (বাস, টেবিল) গোনা হয়। মানুষ বা জায়গার নাম নয়। পুরোনো আর নতুন, দুই রকম চেনা বানানই চলে (যেমন পাখি, পাখী)। খেলা শেষে জালের সব শব্দ দেখানো হয়।</p>
        </details>
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @unless ($locked)
        <script src="{{ asset('js/hp-after.js') }}?v=5" defer></script>
        <script src="{{ asset('js/hp-mag.js') }}?v=1" defer></script>
        <script src="{{ asset('js/hp-jal.js') }}?v=3" defer></script>
    @endunless
</x-layouts.app>
