{{-- D303: one Bengali month in both calendars, with a switch (West Bengal / Bangladesh). Without JavaScript both show. --}}
@php
    $C = \App\Http\Controllers\BanglaCalendarController::class;
    $B = \App\Support\BanglaDate::class;
    $name = $B::MONTHS[$month - 1].' '.\App\Support\Bn::num((string) $year);
    $tabs = array_filter(['wb' => $grids['wb'] ? 'পশ্চিমবঙ্গ' : null, 'bd' => 'বাংলাদেশ']);
@endphp
<div data-cal class="mt-6">
    @if (count($tabs) > 1)
        <div role="tablist" aria-label="কোন বাংলার ক্যালেন্ডার" class="flex gap-2" data-cal-tabs hidden>
            @foreach ($tabs as $k => $label)<button type="button" role="tab" data-cal-tab="{{ $k }}" class="min-h-11 rounded-full border-2 border-navy px-4 font-bold aria-selected:bg-navy aria-selected:text-white">{{ $label }}</button>@endforeach
        </div>
    @else
        <p class="rounded-xl bg-peach-light p-3 text-sm">পশ্চিমবঙ্গের এই মাসের তারিখ প্রকাশিত পঞ্জিকার সঙ্গে মিলিয়ে দেখা হচ্ছে; তাই আপাতত বাংলাদেশের ক্যালেন্ডার দেখানো হল।</p>
    @endif
    @foreach ($tabs as $k => $label)
        @php($g = $grids[$k])
        <section data-cal-panel="{{ $k }}" class="mt-4">
            <h2 class="font-display text-2xl font-extrabold">{{ $name }} <span class="text-base font-bold text-ink2">· {{ $label }}</span></h2>
            <p class="text-sm text-ink2">{{ $C::short($g['days'][0]['g']) }} {{ \App\Support\Bn::num((string) $g['days'][0]['g']->year) }} থেকে {{ $C::short(end($g['days'])['g']) }} {{ \App\Support\Bn::num((string) end($g['days'])['g']->year) }} · {{ \App\Support\Bn::num((string) count($g['days'])) }} দিন · ঋতু: {{ $B::SEASONS[$month - 1] }}</p>
            <div class="mt-3 grid grid-cols-7 gap-1 text-center" role="table" aria-label="{{ $name }}, {{ $label }}">
                @foreach (['র', 'সো', 'ম', 'বু', 'বৃ', 'শু', 'শ'] as $i => $w)<div role="columnheader" aria-label="{{ $B::WEEKDAYS[$i] }}" class="py-1 text-sm font-bold {{ $i === 0 ? 'text-red' : 'text-ink2' }}">{{ $w }}</div>@endforeach
                @for ($i = 0; $i < $g['lead']; $i++)<div aria-hidden="true"></div>@endfor
                @foreach ($g['days'] as $day)
                    <div role="cell" aria-label="{{ \App\Support\Bn::num((string) $day['n']) }} {{ $B::MONTHS[$month - 1] }}, {{ $C::short($day['g']) }}{{ $day['events'] ? ': '.collect($day['events'])->pluck(0)->implode(', ') : '' }}"
                        class="relative min-h-14 rounded-lg border p-1 {{ $day['today'] ? 'border-2 border-red bg-peach-light' : 'border-rule bg-white' }} {{ $day['g']->dayOfWeek === 0 ? 'text-red' : '' }}">
                        <span class="block font-display text-lg font-extrabold leading-tight">{{ \App\Support\Bn::num((string) $day['n']) }}</span>
                        <span class="block text-[11px] leading-tight text-ink2">{{ \App\Support\Bn::num((string) $day['g']->day) }}/{{ \App\Support\Bn::num((string) $day['g']->month) }}</span>
                        @if ($day['events'])<span aria-hidden="true" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red"></span>@endif
                    </div>
                @endforeach
            </div>
            @php($ev = collect($g['days'])->filter(fn ($d) => $d['events']))
            @if ($ev->isNotEmpty())
                <h3 class="mt-5 font-display text-lg font-extrabold">এ মাসের পার্বণ ও বিশেষ দিন</h3>
                <ul class="mt-2 space-y-2">
                    @foreach ($ev as $day)
                        @foreach ($day['events'] as [$label, $link])
                            <li class="flex gap-3 border-b border-rule pb-2"><span class="w-28 flex-none font-bold">{{ \App\Support\Bn::num((string) $day['n']) }} {{ $B::MONTHS[$month - 1] }}<span class="block text-sm font-normal text-ink2">{{ $C::short($day['g']) }}, {{ $B::WEEKDAYS[$day['g']->dayOfWeek] }}</span></span>
                                <span>@if ($link)<a href="{{ $link }}" class="underline">{{ $label }}</a>@else{{ $label }}@endif</span></li>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</div>
@once
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (() => {
        document.querySelectorAll('[data-cal]').forEach((box) => {
            const tabs = [...box.querySelectorAll('[data-cal-tab]')], panels = [...box.querySelectorAll('[data-cal-panel]')];
            if (tabs.length < 2) return;
            box.querySelector('[data-cal-tabs]').hidden = false;
            let pick = 'wb';
            try { pick = localStorage.getItem('hp-cal') || ((Intl.DateTimeFormat().resolvedOptions().timeZone || '') === 'Asia/Dhaka' ? 'bd' : 'wb'); } catch (e) {}
            const show = (k) => {
                tabs.forEach((t) => t.setAttribute('aria-selected', String(t.dataset.calTab === k)));
                panels.forEach((p) => { p.hidden = p.dataset.calPanel !== k; });
            };
            tabs.forEach((t) => t.addEventListener('click', () => { show(t.dataset.calTab); try { localStorage.setItem('hp-cal', t.dataset.calTab); } catch (e) {} }));
            show(tabs.some((t) => t.dataset.calTab === pick) ? pick : tabs[0].dataset.calTab);
        });
    })();
</script>
@endonce
