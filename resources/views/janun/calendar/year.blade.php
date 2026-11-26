{{-- D303: one Bengali year: when each month starts in both Bengals, and the year's festivals --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $C = \App\Http\Controllers\BanglaCalendarController::class;
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা ক্যালেন্ডার', $B::url()], ['বঙ্গাব্দ '.$N($year), '']],
            'kicker' => 'বাংলা ক্যালেন্ডার', 'title' => 'বঙ্গাব্দ '.$N($year).' ('.$N($year + 593).'-'.$N($year + 594).')'])
        <p lang="en" class="mt-1 text-sm text-ink2">{{ $latin }}</p>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr class="border-b-2 border-navy text-sm"><th class="py-2 pr-2">মাস</th><th class="py-2 pr-2">পশ্চিমবঙ্গে ১ তারিখ</th><th class="py-2">বাংলাদেশে ১ তারিখ</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr class="border-b border-rule">
                            <th scope="row" class="py-2 pr-2"><a href="{{ $B::url($year, $r['month']) }}" class="inline-flex min-h-11 items-center font-bold underline">{{ $B::MONTHS[$r['month'] - 1] }}</a><span class="block text-xs font-normal text-ink2">{{ $B::SEASONS[$r['month'] - 1] }}</span></th>
                            <td class="py-2 pr-2">@if ($r['wb']){{ $C::short($r['wb']) }} {{ $N($r['wb']->year) }}<span class="block text-xs text-ink2">{{ $N($r['wbLen']) }} দিন</span>@else<span class="text-sm text-ink2">মিলিয়ে দেখা হচ্ছে</span>@endif</td>
                            <td class="py-2">{{ $C::short($r['bd']) }} {{ $N($r['bd']->year) }}<span class="block text-xs text-ink2">{{ $N($r['bdLen']) }} দিন</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-ad-slot spot="list" :height="250" />
        @if ($days)
            <h2 class="mt-8 font-display text-2xl font-extrabold">এ বছরের পার্বণ ও বিশেষ দিন</h2>
            <ul class="mt-3 space-y-2">
                @foreach (collect($days)->sortKeys() as $date => $list)
                    @php($g = \Carbon\CarbonImmutable::parse($date))
                    @foreach ($list as [$label, $link, $where])
                        <li class="flex gap-3 border-b border-rule pb-2"><span class="w-32 flex-none font-bold">{{ $C::short($g) }} {{ $N($g->year) }}<span class="block text-sm font-normal text-ink2">{{ $B::WEEKDAYS[$g->dayOfWeek] }}</span></span>
                            <span>@if ($link)<a href="{{ $link }}" class="underline">{{ $label }}</a>@else{{ $label }}@endif{{ $B::whereLabel($where) }}</span></li>
                    @endforeach
                @endforeach
            </ul>
            @if (count($days) >= 6)<x-ad-slot spot="list" :height="250" />@endif   {{-- after the year's festival list, when it is long enough (owner, 2026-11-27) --}}
        @endif
        <a href="{{ $B::url() }}" class="mt-6 inline-flex min-h-11 items-center font-bold text-sky-night underline">আজকের বাংলা তারিখ →</a>
    </article>
</x-layouts.app>
