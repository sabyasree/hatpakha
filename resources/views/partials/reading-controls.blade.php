{{-- C2/C3 (owner-approved research, 2026-11): the reader's text size (three steps) and the night page, kept in this
     browser (resources/js/app.js; applied before the page draws by the layout's first script).
     $where: 'top' (the "অ অ" button beside share, every screen) or 'rail' (the quiet side rail on wide screens). --}}
@php
    $sizes = ['0' => ['স্বাভাবিক', 'text-base'], '1' => ['বড়', 'text-xl'], '2' => ['আরও বড়', 'text-2xl']];
    $moon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>';
@endphp
@if ($where === 'top')
    <div class="relative flex-none" data-read-controls>
        <button type="button" data-read-open aria-expanded="false" aria-controls="read-panel" title="লেখার মাপ ও রাতের পাতা"
            class="flex h-11 w-11 items-baseline justify-center gap-px rounded-full border-2 border-navy pt-2 font-serif font-bold hover:bg-peach-light">
            <span class="sr-only">লেখার মাপ ও রাতের পাতা</span><span class="text-sm leading-none" aria-hidden="true">অ</span><span class="text-xl leading-none" aria-hidden="true">অ</span>
        </button>
        <div id="read-panel" hidden class="absolute right-0 top-full z-40 mt-2 w-[min(18rem,calc(100vw-2.5rem))] rounded-2xl border-2 border-navy bg-white p-3 text-navy shadow-[4px_4px_0_#13212C]">
            <p id="read-size-top" class="text-sm font-bold text-ink2">লেখার মাপ</p>
            <div class="mt-1 grid grid-cols-3 gap-1.5" role="group" aria-labelledby="read-size-top">
                @foreach ($sizes as $v => [$label, $sz])
                    <button type="button" data-read-size-set="{{ $v }}" aria-pressed="{{ (string) $v === '0' ? 'true' : 'false' }}"
                        class="flex min-h-14 flex-col items-center justify-center gap-0.5 rounded-xl border-2 border-navy px-1 font-bold aria-pressed:bg-navy aria-pressed:text-white">
                        <span class="{{ $sz }} font-serif leading-none" aria-hidden="true">অ</span><span class="text-xs">{{ $label }}</span></button>
                @endforeach
            </div>
            <button type="button" data-read-night-set="toggle" aria-pressed="false"
                class="mt-2 flex min-h-12 w-full items-center gap-2 rounded-xl border-2 border-navy px-3 font-bold aria-pressed:bg-navy aria-pressed:text-white">
                {!! $moon !!}<span>রাতের পাতা</span><span class="ml-auto text-xs font-semibold">নেভি পাতা, হালকা লেখা</span></button>
        </div>
    </div>
@else
    <div class="flex flex-col items-center gap-1.5" role="group" aria-label="লেখার মাপ">
        <span class="text-xs font-bold" aria-hidden="true">মাপ</span>
        @foreach ($sizes as $v => [$label, $sz])
            <button type="button" data-read-size-set="{{ $v }}" aria-pressed="{{ (string) $v === '0' ? 'true' : 'false' }}" aria-label="লেখার মাপ: {{ $label }}" title="{{ $label }}"
                class="flex h-11 w-11 items-center justify-center rounded-full border-2 border-navy font-serif font-bold aria-pressed:bg-navy aria-pressed:text-white"><span class="{{ $sz }} leading-none" aria-hidden="true">অ</span></button>
        @endforeach
    </div>
    <button type="button" data-read-night-set="toggle" aria-pressed="false" aria-label="রাতের পাতা" title="রাতের পাতা"
        class="flex w-14 flex-col items-center gap-0.5 rounded-2xl border-2 border-navy py-1.5 text-xs font-bold aria-pressed:bg-navy aria-pressed:text-white">{!! $moon !!}<span>রাত</span></button>
@endif
