{{-- D243 (owner: "প্রবাদ পূরণ: add now and include in points"): four proverbs a day, one word missing in each; the
     player taps the right word out of four. Big buttons, no keyboard. Runs in the browser (public/js/hp-probad.js). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $locked = ! empty($locked);
    $payload = ['practice' => ! empty($practice)] + \App\Http\Controllers\GameController::shareFor('probad', $game['date']) + ['no' => $game['no'], 'date' => $game['date'], 'url' => Url::to('/khela/probad'),
        'items' => array_map(fn ($q) => ['before' => $q['before'], 'after' => $q['after'], 'a' => base64_encode($q['answer']), 'options' => $q['options'], 'meaning' => $q['meaning']], $game['items'])];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" @unless ($locked) data-probad @endunless @if (empty($practice)) @auth data-score-url="{{ route('game.score') }}" data-score-token="{{ csrf_token() }}" @endauth @endif>
        @include('partials.practice-banner')
        @include('partials.game-nav', ['on' => 'probad'])
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="font-hand text-2xl text-red">রোজ চারটে প্রবাদ</p>
                <h1 class="font-display text-4xl font-extrabold leading-tight">প্রবাদ পূরণ</h1>
            </div>
            <p class="text-right text-sm font-bold text-ink2">খেলা নং {{ Bn::num((string) $game['no']) }}</p>
        </div>
        <p class="mt-2 text-ink2">প্রবাদের একটা শব্দ নেই। নিচের চারটে শব্দের মধ্যে ঠিকটায় টিপুন। প্রথম বারে ঠিক হলে ২৫ পয়েন্ট, দ্বিতীয় বারে ১০।</p>

        @if ($locked)
            @include('partials.game-lock', ['what' => 'প্রবাদ পূরণ', 'easy' => ['শব্দ-খেলা', Url::to('/khela')]])
        @else
        <ol class="mt-5 flex gap-2" aria-label="আজকের প্রবাদ" data-probad-dots>
            @foreach ($game['items'] as $q)<li class="flex h-3 flex-1 rounded-full bg-rule" data-dot></li>@endforeach
        </ol>

        <div data-probad-board class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
            <p class="text-sm font-bold text-ink2" data-probad-count></p>
            <p class="mt-2 font-serif text-2xl font-bold leading-relaxed" data-probad-text aria-live="polite"></p>
            <p class="mt-3 min-h-6 text-center font-bold" role="status" aria-live="polite" data-probad-msg></p>
            <div class="mt-2 grid grid-cols-2 gap-3" data-probad-options></div>
            <p class="mt-4 hidden rounded-2xl bg-peach-light p-3 text-lg" data-probad-meaning></p>
            <button type="button" data-probad-next hidden class="mt-4 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">পরের প্রবাদ →</button>
        </div>

        <div data-probad-done hidden class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
            <p class="font-hand text-3xl text-red" data-probad-cheer></p>
            <p class="mt-1 text-2xl font-extrabold" data-probad-score></p>
            <p class="mt-2 flex gap-1.5" data-probad-grid aria-hidden="true"></p>
            <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink2"><span class="flex items-center gap-1"><i class="block h-4 w-4 rounded border-2 border-navy bg-sky-deep"></i>প্রথম বারে ঠিক</span><span class="flex items-center gap-1"><i class="block h-4 w-4 rounded border-2 border-navy bg-peach"></i>দ্বিতীয় বারে ঠিক</span><span class="flex items-center gap-1"><i class="block h-4 w-4 rounded border-2 border-navy bg-red"></i>হয়নি</span></p>
            @include('partials.game-board-line')
            <h2 class="mt-4 font-display text-xl font-extrabold">আজকের প্রবাদ</h2>
            <ul class="mt-2 divide-y divide-rule" data-probad-answers></ul>
            <div class="mt-4 grid gap-2">
                <button type="button" data-probad-share class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">বন্ধুদের দেখান</button>
                <a data-probad-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">WhatsApp-এ পাঠান</a>
            </div>
            <p class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-probad-share-msg></p>
            <div data-report></div>
            <p class="mt-4 font-bold">নতুন প্রবাদ আসবে রাত বারোটায় (ভারতের সময়)।</p>
        </div>
        @endif

        <noscript><p class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-bold">খেলাটি চালাতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু থাকতে হবে।</p></noscript>
        <details class="mt-6 rounded-2xl border-2 border-navy bg-white p-4">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between font-bold">কীভাবে খেলবেন <span aria-hidden="true">▾</span></summary>
            <ul class="mt-2 list-disc space-y-1 ps-6 text-ink2">
                <li>প্রবাদটা পড়ুন। যে শব্দটা নেই, সেখানে একটা ফাঁকা দাগ আছে।</li>
                <li>নিচের চারটে শব্দের মধ্যে ঠিকটায় টিপুন।</li>
                <li>প্রথম বারে ঠিক: ২৫ পয়েন্ট। দ্বিতীয় বারে ঠিক: ১০। দু বারেও না হলে: ০।</li>
                <li>উত্তরের পরে প্রবাদের মানে দেখানো হয়।</li>
            </ul>
        </details>
        @unless ($locked)<script type="application/json" data-probad-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>@endunless
        @if ($probadPage = \App\Http\Controllers\JanunController::liveUrl('probad'))
            <a href="{{ $probadPage }}" class="mt-8 flex min-h-11 items-center rounded-xl border-2 border-navy px-4 font-bold hover:bg-peach-light">বাংলা প্রবাদ-প্রবচন ও তার অর্থ: পুরো তালিকা →</a>   {{-- D301 --}}
        @endif
        {{-- far below the words a player taps, so no ad is touched by mistake --}}
        <x-ad-slot spot="list" :height="250" class="!mt-16" />
    </section>
    @unless ($locked)
    <script src="{{ asset('js/hp-after.js') }}?v=5" defer></script>
    <script src="{{ asset('js/hp-report.js') }}?v=1" defer></script>
    <script src="{{ asset('js/hp-probad.js') }}?v=4" defer></script>
    @endunless
</x-layouts.app>
