{{-- D174 (owner): শব্দ-খেলা, the daily word jumble. Five words, the same for everyone; taps only, no keyboard.
     The game runs in the browser (public/js/hp-khela.js); the page itself is the same for every reader, so it caches. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $no = max(1, $game['no']);
    $payload = ['no' => $no, 'date' => $game['date'], 'url' => Url::to('/khela'),
        'words' => array_map(fn ($w) => ['a' => base64_encode($w['answer']), 'n' => count(\App\Support\WordGame::letters($w['answer'])), 'f' => base64_encode(\App\Support\WordGame::letters($w['answer'])[0]), 'clue' => $w['clue'], 'tiles' => $w['tiles']], $game['words'])];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" data-khela>
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="font-hand text-2xl text-red">রোজ পাঁচটা শব্দ</p>
                <h1 class="font-display text-4xl font-extrabold leading-tight">শব্দ-খেলা</h1>
            </div>
            <p class="text-right text-sm font-bold text-ink2">খেলা নং {{ Bn::num((string) $no) }}<br><span data-khela-streak></span></p>
        </div>
        <p class="mt-2 text-ink2">এলোমেলো অক্ষরগুলো টিপে টিপে ঠিক শব্দটা বানান। সূত্র দেওয়া আছে। সবার জন্য আজ একই পাঁচটা শব্দ।</p>

        <ol class="mt-5 flex gap-2" aria-label="আজকের পাঁচটি শব্দ" data-khela-dots>
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
                <button type="button" data-khela-clear class="flex h-11 items-center rounded-full px-3 font-bold text-ink2 underline">মুছে আবার</button>
                <button type="button" data-khela-skip class="flex h-11 items-center rounded-full px-3 font-bold text-ink2 underline">পারছি না, উত্তর দেখান</button>
            </div>
        </div>

        {{-- The result --}}
        <div data-khela-done hidden class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
            <p class="font-hand text-3xl text-red" data-khela-cheer></p>
            <p class="mt-1 text-2xl font-extrabold" data-khela-score></p>
            <p class="mt-2 text-3xl tracking-widest" data-khela-grid aria-hidden="true"></p>
            <p class="mt-2 font-bold" data-khela-streak-line></p>
            <div class="mt-4 grid gap-2">
                <button type="button" data-khela-share class="flex h-12 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">বন্ধুদের দেখান</button>
                <a data-khela-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">WhatsApp-এ পাঠান</a>
            </div>
            <p class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-khela-share-msg></p>
            <h2 class="mt-4 font-display text-xl font-extrabold">আজকের শব্দ</h2>
            <ul class="mt-2 divide-y divide-rule" data-khela-answers></ul>
            <p class="mt-4 font-bold">নতুন খেলা আসবে রাত বারোটায়। <span class="text-ink2" data-khela-next></span></p>
            <a href="{{ url('/') }}/" class="mt-3 inline-flex min-h-11 items-center font-bold text-sky-night underline">এবার একটা ভালো লেখা পড়ুন →</a>
        </div>

        <noscript><p class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-bold">খেলাটি চালাতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু থাকতে হবে।</p></noscript>
        <details class="mt-6 rounded-2xl border border-rule bg-white p-4">
            <summary class="flex min-h-11 cursor-pointer items-center font-bold">কীভাবে খেলবেন</summary>
            <ul class="mt-2 list-disc space-y-1 ps-6 text-ink2">
                <li>নিচের অক্ষরে টিপলে সেটি উপরের ঘরে বসে। ভুল হলে উপরের অক্ষরে টিপে ফেরত নিন।</li>
                <li>সব ঘর ভরলে নিজে থেকেই মিলিয়ে দেখা হয়।</li>
                <li>🟦 নিজে পেরেছেন · 🟨 একটা অক্ষর দেখে পেরেছেন · 🟥 উত্তর দেখেছেন।</li>
                <li>রোজ খেললে “টানা দিন” বাড়ে। হিসেব থাকে শুধু আপনার এই ফোনে।</li>
            </ul>
        </details>
        <script type="application/json" data-khela-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </section>
    <script src="{{ asset('js/hp-khela.js') }}?v=1" defer></script>
</x-layouts.app>
