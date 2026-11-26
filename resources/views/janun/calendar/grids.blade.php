{{-- D303: one month in both calendars (each grid carries its own year and month), with a switch. Without JavaScript both show.
     Weeks are ARIA rows; today is aria-current (UX review). The switch also changes which date the today card puts first. --}}
@php
    $C = \App\Http\Controllers\BanglaCalendarController::class;
    $B = \App\Support\BanglaDate::class;
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $tabs = array_filter(['wb' => $grids['wb'] ? 'পশ্চিমবঙ্গ' : null, 'bd' => 'বাংলাদেশ']);
    $uid = 'cal'.substr(md5(json_encode(array_map(fn ($g) => $g ? [$g['year'], $g['month']] : null, $grids))), 0, 6);
@endphp
<div data-cal class="mt-6">
    @if (count($tabs) > 1)
        <div role="tablist" aria-label="কোন বাংলার ক্যালেন্ডার" class="flex gap-2" data-cal-tabs hidden>
            @foreach ($tabs as $k => $label)<button type="button" role="tab" id="{{ $uid }}-t-{{ $k }}" aria-controls="{{ $uid }}-p-{{ $k }}" data-cal-tab="{{ $k }}" class="min-h-11 rounded-full border-2 border-navy px-4 font-bold aria-selected:bg-navy aria-selected:text-white">{{ $label }}</button>@endforeach
        </div>
    @else
        <p class="rounded-xl bg-peach-light p-3 text-sm">পশ্চিমবঙ্গের এই মাসের তারিখ প্রকাশিত পঞ্জিকার সঙ্গে মিলিয়ে দেখা হচ্ছে; তাই আপাতত বাংলাদেশের ক্যালেন্ডার দেখানো হল।</p>
    @endif
    @foreach ($tabs as $k => $label)
        @php
            $g = $grids[$k];
            $mn = $B::MONTHS[$g['month'] - 1];
            $name = $mn.' '.$N($g['year']);
            $cells = array_merge(array_fill(0, $g['lead'], null), $g['days']);
        @endphp
        <section data-cal-panel="{{ $k }}" id="{{ $uid }}-p-{{ $k }}" @if (count($tabs) > 1) role="tabpanel" aria-labelledby="{{ $uid }}-t-{{ $k }}" @endif class="mt-4">
            <h2 class="font-display text-2xl font-extrabold">{{ $name }} <span class="text-base font-bold text-ink2">· {{ $label }}</span></h2>
            <p class="text-sm text-ink2">{{ $C::short($g['days'][0]['g']) }} {{ $N($g['days'][0]['g']->year) }} থেকে {{ $C::short(end($g['days'])['g']) }} {{ $N(end($g['days'])['g']->year) }} · {{ $N(count($g['days'])) }} দিন · ঋতু: {{ $B::SEASONS[$g['month'] - 1] }}</p>
            <div class="mt-3 grid grid-cols-7 gap-1 text-center" role="table" aria-label="{{ $name }}, {{ $label }}">
                <div role="row" class="contents">
                    @foreach (['র', 'সো', 'ম', 'বু', 'বৃ', 'শু', 'শ'] as $i => $w)<div role="columnheader" aria-label="{{ $B::WEEKDAYS[$i] }}" class="py-1 text-sm font-bold {{ $i === 0 ? 'text-red' : 'text-ink2' }}">{{ $w }}</div>@endforeach
                </div>
                @foreach (array_chunk($cells, 7) as $week)
                    <div role="row" class="contents">
                        @foreach ($week as $day)
                            @if ($day === null)<div role="cell" aria-hidden="true"></div>@else
                            <div role="cell" @if ($day['today']) aria-current="date" @endif
                                aria-label="{{ $B::WEEKDAYS[$day['g']->dayOfWeek] }}, {{ $N($day['n']) }} {{ $mn }}, {{ $C::short($day['g']) }}{{ $day['today'] ? ', আজ' : '' }}{{ $day['events'] ? ': '.collect($day['events'])->pluck(0)->implode(', ') : '' }}"
                                class="relative min-h-14 rounded-lg border p-1 {{ $day['today'] ? 'border-2 border-red bg-peach-light' : 'border-rule bg-white' }} {{ $day['g']->dayOfWeek === 0 ? 'text-red' : '' }}">
                                <span aria-hidden="true" class="block font-display text-lg font-extrabold leading-tight">{{ $N($day['n']) }}</span>
                                <span aria-hidden="true" class="block text-xs leading-tight text-ink2">{{ $N($day['g']->day) }}/{{ $N($day['g']->month) }}</span>
                                @if ($day['events'])<span aria-hidden="true" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red"></span>@endif
                            </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
            @php($ev = collect($g['days'])->filter(fn ($d) => $d['events']))
            @if ($ev->isNotEmpty())
                <h3 class="mt-5 font-display text-lg font-extrabold">এ মাসের পার্বণ ও বিশেষ দিন</h3>
                <ul class="mt-2 space-y-2">
                    @foreach ($ev as $day)
                        @foreach ($day['events'] as [$elabel, $link])
                            <li class="flex gap-3 border-b border-rule pb-2"><span class="w-28 flex-none font-bold">{{ $N($day['n']) }} {{ $mn }}<span class="block text-sm font-normal text-ink2">{{ $C::short($day['g']) }}, {{ $B::WEEKDAYS[$day['g']->dayOfWeek] }}</span></span>
                                <span>@if ($link)<a href="{{ $link }}" class="underline">{{ $elabel }}</a>@else{{ $elabel }}@endif</span></li>
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
                tabs.forEach((t) => { const on = t.dataset.calTab === k; t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; });
                panels.forEach((p) => { p.hidden = p.dataset.calPanel !== k; });
                document.querySelectorAll('[data-today]').forEach((c) => { c.hidden = c.dataset.today !== k; });   // the today card follows the chosen Bengal
            };
            tabs.forEach((t, i) => {
                t.addEventListener('click', () => { show(t.dataset.calTab); try { localStorage.setItem('hp-cal', t.dataset.calTab); } catch (e) {} });
                t.addEventListener('keydown', (e) => {
                    if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                    const n = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                    n.focus(); n.click();
                });
            });
            show(tabs.some((t) => t.dataset.calTab === pick) ? pick : tabs[0].dataset.calTab);
        });
    })();
</script>
@endonce
