{{-- D328: the board and the result of a four-choice game (public/js/hp-choice.js). $count: questions; $after: whether
     to show today's points under the result (daily games and the quiz); $extra: markup for the result (optional). --}}
<ol class="mt-5 flex gap-1.5" aria-label="প্রশ্ন" data-c-dots>
    @for ($i = 0; $i < $count; $i++)<li class="flex h-3 flex-1 rounded-full bg-rule" data-dot></li>@endfor
</ol>

<div data-c-board class="mt-5 rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C]">
    <p class="text-sm font-bold text-ink2" data-c-count></p>
    <p class="mt-2 font-serif text-2xl font-bold leading-relaxed" data-c-prompt aria-live="polite"></p>
    <div class="mt-4 grid grid-cols-2 gap-3" data-c-options></div>
    <p class="mt-3 min-h-6 text-center text-lg font-bold" role="status" aria-live="polite" data-c-msg></p>
    <p class="rounded-2xl bg-peach-light p-3 text-lg" data-c-note hidden></p>
    <div class="mt-2" data-c-mag hidden></div>
    <a href="#" class="mt-3 flex min-h-11 items-center font-bold text-sky-night underline" data-c-link hidden></a>
    <button type="button" data-c-next hidden class="mt-4 flex h-14 w-full items-center justify-center rounded-full bg-red px-6 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">পরের প্রশ্ন →</button>
</div>

<div data-c-done hidden class="mt-5 rounded-3xl border-2 border-navy bg-peach-light p-5 shadow-[4px_4px_0_#13212C]">
    <p class="font-hand text-3xl text-red" data-c-cheer></p>
    <p class="mt-1 text-2xl font-extrabold" data-c-score></p>
    <p class="mt-2 flex flex-wrap gap-1.5" data-c-grid aria-hidden="true"></p>
    {!! $extra ?? '' !!}
    @if ($after ?? true)
        @include('partials.game-board-line')
    @endif
    <h2 class="mt-4 font-display text-xl font-extrabold">উত্তরগুলো</h2>
    <ul class="mt-2 divide-y divide-rule" data-c-answers></ul>
    @unless ($noShare ?? false)
        <div class="mt-4 grid gap-2">
            <button type="button" data-c-share class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">বন্ধুদের দেখান</button>
            <a data-c-wa href="#" target="_blank" rel="noopener" class="flex h-12 items-center justify-center rounded-full border-2 border-navy bg-white px-6 font-bold">WhatsApp-এ পাঠান</a>
        </div>
    @endunless
    <p class="mt-2 min-h-5 text-center text-sm font-semibold text-sky-night" role="status" data-c-share-msg></p>
    <div data-report></div>
</div>
<noscript><p class="mt-5 rounded-xl border-2 border-red bg-peach-light p-4 font-bold">খেলাটি চালাতে ব্রাউজারে জাভাস্ক্রিপ্ট চালু থাকতে হবে।</p></noscript>
