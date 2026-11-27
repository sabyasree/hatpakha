{{-- D174 (owner): শব্দ-খেলা, the daily word jumble. Five words, the same for everyone; taps only, no keyboard.
     The game runs in the browser (public/js/hp-khela.js); the page itself is the same for every reader, so it caches. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $no = max(1, $game['no']);
    $kind = $kind ?? 'word';
    $riddle = in_array($kind, ['riddle', 'riddle2'], true);
    $hard = $kind === 'riddle2';
    $locked = ! empty($locked);
    $done = auth()->check() && $riddle ? \App\Support\GameScores::today(auth()->user())['games'] : [];
    $pts = fn ($k) => \App\Support\GameScores::riddlePoints($k, $game['date'])[0];
    $payload = ['kind' => $kind, 'no' => $no, 'date' => $game['date'], 'url' => Url::to($hard ? '/khela/dhadha/kothin' : ($riddle ? '/khela/dhadha' : '/khela')),
        'words' => array_map(fn ($w) => ['a' => base64_encode($w['answer']), 'n' => count(\App\Support\WordGame::letters($w['answer'])), 'f' => base64_encode(\App\Support\WordGame::letters($w['answer'])[0]), 'clue' => $w['clue'], 'tiles' => $w['tiles']], $game['words'])];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @unless ($locked) data-khela @endunless @auth data-score-url="{{ route('game.score') }}" data-score-token="{{ csrf_token() }}" @endauth>
        @include('partials.game-nav', ['on' => $riddle ? 'riddle' : 'word'])
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="font-hand text-2xl text-red">{{ $riddle ? 'রোজ দুটো ধাঁধা' : 'রোজ পাঁচটা শব্দ' }}</p>
                <h1 class="font-display text-4xl font-extrabold leading-tight">{{ $hard ? 'কঠিন ধাঁধা' : ($riddle ? 'ধাঁধা' : 'শব্দ-খেলা') }}</h1>
            </div>
            <p class="text-right text-sm font-bold text-ink2">খেলা নং {{ Bn::num((string) $no) }}<br><span data-khela-streak></span></p>
        </div>
        <p class="mt-2 text-ink2">{{ $riddle ? 'ধাঁধাটা পড়ে ভাবুন, তারপর নিচের অক্ষরে টিপে টিপে উত্তরটা বানান। কয়েকটা বাড়তি অক্ষরও মেশানো আছে।' : 'এলোমেলো অক্ষরগুলো টিপে টিপে ঠিক শব্দটা বানান। সূত্র দেওয়া আছে। সবার জন্য আজ একই পাঁচটা শব্দ।' }}</p>

        @if ($riddle)
            {{-- D243: two riddles a day, both playable; the points show before choosing --}}
            <nav class="mt-4 grid grid-cols-2 gap-3" aria-label="ধাঁধা বাছুন">
                @foreach ([['riddle', 'সহজ ধাঁধা', 'চেনা ধাঁধা, ছোট উত্তর', Url::to('/khela/dhadha'), 'bg-sky-tint'], ['riddle2', 'কঠিন ধাঁধা', 'ভাবতে হবে, বাড়তি অক্ষরও বেশি', Url::to('/khela/dhadha/kothin'), 'bg-peach-light']] as [$k, $name, $line, $href, $bg])
                    <a href="{{ $href }}" @if ($k === $kind) aria-current="page" @endif class="relative flex min-h-24 flex-col rounded-2xl border-2 border-navy p-3 leading-tight {{ $bg }} {{ $k === $kind ? 'shadow-[3px_3px_0_#13212C]' : 'opacity-90' }}">
                        <span class="font-display text-xl font-extrabold">{{ $name }}</span>
                        <span class="mt-0.5 text-sm text-ink2">{{ $line }}</span>
                        <span class="mt-auto pt-1 font-extrabold">@if (isset($done[$k]))<span class="text-sky-night">✓ হয়ে গেছে · {{ Bn::num((string) $done[$k]) }}</span>@else{{ Bn::num((string) $pts($k)) }} পয়েন্ট@endif @guest @if ($k === 'riddle2')<span class="block text-sm font-bold">@include('partials.lock-icon') অ্যাকাউন্ট লাগে</span>@endif @endguest</span>
                        @if ($k === $kind)<span class="absolute -top-3 right-2 rounded-full bg-navy px-2 py-0.5 text-xs font-bold text-white">এখন এটা খেলছেন</span>@endif
                    </a>
                @endforeach
            </nav>
            <p class="mt-2 text-ink2">@auth দুটোই খেলা যায়, দুটোর পয়েন্টই জমে। একটা অক্ষর দেখে নিলে অর্ধেক পয়েন্ট।@else অ্যাকাউন্ট ছাড়া সহজটা খেলা যায়; পয়েন্ট জমাতে আর কঠিনটা খেলতে অ্যাকাউন্ট লাগে।@endauth</p>
        @endif
        @if ($locked)
            @include('partials.game-lock', ['what' => 'কঠিন ধাঁধা', 'easy' => ['সহজ ধাঁধা', Url::to('/khela/dhadha')]])
        @else

        <ol class="mt-5 flex gap-2 {{ $riddle ? 'hidden' : '' }}" aria-label="আজকের শব্দ" data-khela-dots>
            @foreach ($game['words'] as $i => $w)
                <li class="flex h-3 flex-1 rounded-full bg-rule" data-dot></li>
            @endforeach
        </ol>

        {{-- The board --}}
        <div data-khela-board class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
            <p class="text-sm font-bold text-ink2"><span data-khela-count></span></p>
            <p class="mt-1 font-serif text-xl font-bold leading-snug" data-khela-clue></p>
            <div class="mt-5 flex min-h-16 flex-wrap justify-center gap-2" data-khela-slots aria-label="আপনার বানানো শব্দ"></div>
            <p class="mt-3 min-h-6 text-center font-bold" role="status" aria-live="polite" data-khela-msg></p>
            <div class="mt-3 flex flex-wrap justify-center gap-2" data-khela-tiles aria-label="অক্ষর"></div>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
                <button type="button" data-khela-hint class="flex h-11 items-center rounded-full border-2 border-navy bg-paper px-4 font-bold">একটা অক্ষর দেখান</button>
                <button type="button" data-khela-clear class="flex h-11 items-center rounded-full border-2 border-navy bg-paper px-4 font-bold">মুছে আবার শুরু</button>
                <button type="button" data-khela-skip class="mt-2 flex h-11 w-full items-center justify-center rounded-full font-bold text-ink2 underline">পারছি না, উত্তর দেখান</button>
            </div>
        </div>

        {{-- The result --}}
        <div data-khela-done hidden class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
            <p class="font-hand text-3xl text-red" data-khela-cheer></p>
            <p class="mt-1 text-2xl font-extrabold" data-khela-score></p>
            <p class="mt-2 text-3xl tracking-widest" data-khela-grid aria-hidden="true"></p>
            <p class="mt-2 font-bold" data-khela-streak-line></p>
            @include('partials.game-board-line')
            <div class="mt-4 grid gap-2">
                <button type="button" data-khela-share class="flex h-12 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">বন্ধুদের দেখান</button>
                <a data-khela-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">WhatsApp-এ পাঠান</a>
            </div>
            <p class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-khela-share-msg></p>
            <h2 class="mt-4 font-display text-xl font-extrabold">{{ $riddle ? 'উত্তর' : 'আজকের শব্দ' }}</h2>
            <ul class="mt-2 divide-y divide-rule" data-khela-answers></ul>
            <div data-report></div>
            <p class="mt-4 font-bold">নতুন খেলা আসবে রাত বারোটায়। <span class="text-ink2" data-khela-next></span></p>
            <a href="{{ url('/') }}/" class="mt-3 inline-flex min-h-11 items-center font-bold text-sky-night underline">এবার একটা ভালো লেখা পড়ুন →</a>
        </div>

        @endif
        @if ($riddle && ! empty($yesterday) && ! $locked)
            {{-- D243: yesterday's answer, for the reader who comes back --}}
            <details class="mt-5 rounded-2xl border-2 border-navy bg-white p-4">
                <summary class="flex min-h-11 cursor-pointer items-center font-bold text-red">কালকের ধাঁধার উত্তর দেখুন</summary>
                <p class="mt-1 font-serif text-lg leading-relaxed">“{{ $yesterday['clue'] }}” — <strong>{{ $yesterday['answer'] }}</strong></p>
            </details>
        @endif

        <noscript><p class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-bold">খেলাটি চালাতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু থাকতে হবে।</p></noscript>
        <details class="mt-6 rounded-2xl border-2 border-navy bg-white p-4">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between font-bold">কীভাবে খেলবেন <span aria-hidden="true">▾</span></summary>
            <ul class="mt-2 list-disc space-y-1 ps-6 text-ink2">
                @if ($riddle)<li>উত্তরের অক্ষরগুলোর সঙ্গে কয়েকটা বাড়তি অক্ষর মেশানো আছে। সব অক্ষর লাগবে না।</li>@endif
                <li>নিচের অক্ষরে টিপলে সেটি উপরের ঘরে বসে। ভুল হলে উপরের অক্ষরে টিপে ফেরত নিন।</li>
                <li>সব ঘর ভরলে নিজে থেকেই মিলিয়ে দেখা হয়।</li>
                <li>🟦 নিজে পেরেছেন · 🟨 একটা অক্ষর দেখে পেরেছেন · 🟥 উত্তর দেখেছেন।</li>
                <li>রোজ খেললে “টানা দিন” বাড়ে। হিসেব থাকে শুধু আপনার এই ফোনে।</li>
            </ul>
        </details>
        @unless ($locked)<script type="application/json" data-khela-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>@endunless
        {{-- D237: far below the letters a player taps, so no ad is touched by mistake --}}
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @unless ($locked)
    <script src="{{ asset('js/hp-report.js') }}?v=1" defer></script>
    <script src="{{ asset('js/hp-after.js') }}?v=2" defer></script>
    <script src="{{ asset('js/hp-khela.js') }}?v=4" defer></script>
    @endunless
</x-layouts.app>
