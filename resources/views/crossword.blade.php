{{-- D177 (owner): শব্দজব্দ, a small daily crossword. Tap a square, then tap a letter: no keyboard needed.
     Runs in the browser (public/js/hp-jobdo.js); the page is the same for every reader, so it caches. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    use App\Support\WordGame;
    $level = $level ?? 'medium';
    [$levelName, , $levelKey, , , , $full, $cost] = WordGame::LEVELS[$level];
    $payload = ['no' => $game['no'], 'date' => $game['date'], 'size' => $game['size'], 'url' => WordGame::crosswordUrl($level), 'bank' => $game['bank'],
        'level' => $level, 'levelName' => $levelName, 'key' => $levelKey, 'full' => $full, 'cost' => $cost,
        'words' => array_map(fn ($w) => ['s' => base64_encode(json_encode($w['letters'], JSON_UNESCAPED_UNICODE)), 'n' => count($w['letters']), 'clue' => $w['clue'], 'row' => $w['row'], 'col' => $w['col'], 'down' => $w['down']], $game['words'])];
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[560px] px-5 pb-12 pt-6" data-jobdo @auth data-score-url="{{ route('game.score') }}" data-score-token="{{ csrf_token() }}" @endauth>
        @include('partials.game-nav', ['on' => 'cross'])
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="font-hand text-2xl text-red">রোজ একটা ছোট ছক</p>
                <h1 class="font-display text-4xl font-extrabold leading-tight">শব্দজব্দ</h1>
            </div>
            <p class="text-right text-sm font-bold text-ink2">ছক নং {{ Bn::num((string) $game['no']) }}</p>
        </div>
        {{-- D201: three levels a day, all open --}}
        <nav class="mt-3 flex gap-2" aria-label="ধাপ">
            @foreach (WordGame::LEVELS as $k => [$n, , , , , , $pts])
                <a href="{{ WordGame::crosswordUrl($k) }}" @if ($k === $level) aria-current="page" @endif
                   class="flex min-h-11 flex-1 flex-col items-center justify-center rounded-2xl border-2 border-navy px-2 py-1 text-center font-bold leading-tight {{ $k === $level ? 'bg-navy text-white' : 'bg-white' }}">
                    <span>{{ $n }}</span><span class="text-xs font-semibold">{{ Bn::num((string) $pts) }} পয়েন্ট</span>
                </a>
            @endforeach
        </nav>
        <p class="mt-3 text-ink2">একটা ঘরে টিপুন, তারপর নিচের অক্ষরে টিপুন। সূত্র মিলিয়ে সব ঘর ভরুন।
            @if ($level === 'easy') ছোট ছক, চেনা ছোট শব্দ। @elseif ($level === 'hard') বড় ছক, বইয়ের পাতার শব্দ; নিচে কয়েকটা বাড়তি অক্ষরও আছে। @endif</p>

        <div data-jobdo-board class="mt-5">
            <div class="mx-auto grid {{ $level === 'hard' ? 'max-w-[520px] gap-0.5' : 'max-w-[420px] gap-1' }}" style="grid-template-columns: repeat({{ $game['size'] }}, minmax(0, 1fr))" data-jobdo-grid role="group" aria-label="শব্দছক"></div>
            <p class="mt-4 min-h-14 rounded-2xl border-2 border-navy bg-white p-3 font-serif text-lg font-bold leading-snug" aria-live="polite" data-jobdo-clue></p>
            <p class="mt-2 min-h-6 text-center font-bold" role="status" aria-live="polite" data-jobdo-msg></p>
            <div class="mt-2 flex flex-wrap justify-center gap-1.5" data-jobdo-bank aria-label="অক্ষর"></div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                <button type="button" data-jobdo-back class="flex h-11 items-center rounded-full border-2 border-navy bg-paper px-4 font-bold">⌫ মুছুন</button>
                <button type="button" data-jobdo-check class="flex h-11 items-center rounded-full border-2 border-navy bg-paper px-4 font-bold">মিলিয়ে দেখুন</button>
                <button type="button" data-jobdo-reveal class="flex h-11 items-center rounded-full px-3 font-bold text-ink2 underline">এই ঘরটা দেখান</button>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div><h2 class="font-display text-xl font-extrabold">পাশাপাশি</h2><ol class="mt-1" data-jobdo-across></ol></div>
                <div><h2 class="font-display text-xl font-extrabold">উপর-নিচ</h2><ol class="mt-1" data-jobdo-down></ol></div>
            </div>
            <button type="button" data-jobdo-giveup class="mt-4 flex min-h-11 items-center font-bold text-ink2 underline">পারছি না, পুরো ছক দেখান</button>
        </div>

        <div data-jobdo-done hidden class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
            <p class="font-hand text-3xl text-red" data-jobdo-cheer></p>
            <p class="mt-1 text-2xl font-extrabold" data-jobdo-score></p>
            <div class="mt-4 grid gap-2">
                <button type="button" data-jobdo-share class="flex h-12 items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">বন্ধুদের দেখান</button>
                <a data-jobdo-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">WhatsApp-এ পাঠান</a>
            </div>
            <p class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-jobdo-share-msg></p>
            @include('partials.game-board-line')
            <div data-report></div>
            <p class="mt-3 font-bold">নতুন ছক আসবে রাত বারোটায়।</p>
        </div>

        <noscript><p class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-bold">খেলাটি চালাতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু থাকতে হবে।</p></noscript>
        <details class="mt-6 rounded-2xl border border-rule bg-white p-4">
            <summary class="flex min-h-11 cursor-pointer items-center font-bold">কীভাবে খেলবেন</summary>
            <ul class="mt-2 list-disc space-y-1 ps-6 text-ink2">
                <li>ছকের ঘরে বা নিচের সূত্রে টিপলে শব্দটা বাছা হয়। একই ঘরে আবার টিপলে দিক বদলায় (পাশাপাশি ↔ উপর-নিচ)।</li>
                <li>এক ঘরে একটা অক্ষর: “প্র”, “ন্ত”, “কা” প্রতিটাই একটা অক্ষর।</li>
                <li>“মিলিয়ে দেখুন” টিপলে ভুল ঘরগুলো লাল হয়। “এই ঘরটা দেখান” একটা অক্ষর বসিয়ে দেয় ({{ Bn::num((string) $cost) }} পয়েন্ট কমে)।</li>
                <li>রোজ তিনটে ছক: সহজ (৬০ পয়েন্ট), মাঝারি (১০০), কঠিন (১৫০)। তিনটেই খেলা যায়।</li>
            </ul>
        </details>
        <script type="application/json" data-jobdo-data>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </section>
    <script src="{{ asset('js/hp-report.js') }}?v=1" defer></script>
    <script src="{{ asset('js/hp-jobdo.js') }}?v=3" defer></script>
</x-layouts.app>
