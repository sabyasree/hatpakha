{{-- D313: a year's পূর্ণিমা / অমাবস্যা / একাদশী, from checked tithi days only --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $F = \App\Support\FestivalPlan::class;
    $L = \App\Support\LunarDays::class;
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['পুজো-পার্বণ', $U('/janun/puja')], [$nameY, '']],
            'kicker' => 'জেনে রাখুন · পুজো-পার্বণ', 'title' => $nameY.': তারিখ ও তিথির সময়'])
        <p lang="en" class="mt-1 text-sm text-ink2">{{ $en }} {{ $year }}: dates and tithi times</p>
        @if ($next)
            <p class="mt-4 font-serif text-lg leading-relaxed">পরের {{ $L::label($next['tithi']) }}: <b>{{ $F::show($next['day'], false) }}</b>@if ($wb = $B::wb($next['day'])) ({{ $B::text($wb) }})@endif। তিথি শুরু {{ $F::show($next['start']) }}, শেষ {{ $F::show($next['end']) }} (ভারতের সময়)।</p>
        @endif
        <div class="mt-5 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b-2 border-navy"><th class="py-2 pr-2">দিন</th><th class="py-2 pr-2">তিথি শুরু</th><th class="py-2">তিথি শেষ</th></tr></thead>
                <tbody>
                    @foreach ($items as $e)
                        <tr class="border-b border-rule {{ $next && $e['start']->equalTo($next['start']) ? 'bg-peach-light' : '' }}">
                            <th scope="row" class="py-2 pr-2 align-top"><span class="font-bold">{{ $F::show($e['day'], false) }}</span>
                                <span class="block text-xs font-normal text-ink2">@if ($wb = $B::wb($e['day'])){{ $B::text($wb) }} · @endif{{ $L::label($e['tithi']) }}</span></th>
                            <td class="py-2 pr-2 align-top">{{ $F::show($e['start']) }}</td>
                            <td class="py-2 align-top">{{ $F::show($e['end']) }}</td>
                        </tr>
                        @if ($loop->iteration === 6 && $loop->remaining > 3)<tr><td colspan="3"><x-ad-slot spot="list" :height="250" /></td></tr>@endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-ink2">সময় ভারতের (কলকাতা)। দিন: যে দিন সূর্যোদয়ে তিথি থাকে। তিথি: বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা, আমাদের নিজেদের হিসেবের সঙ্গে মিলিয়ে।@if ($type === 'ekadashi') বৈষ্ণব মতে উপবাসের দিন কখনও এক দিন পরে পড়ে; নিজের পঞ্জিকা বা গুরুর মত মেনে চলুন।@endif</p>
        <a href="{{ $U('/janun/puja/pujor-somoy') }}" class="mt-4 flex min-h-12 items-center rounded-2xl border-2 border-sky-deep bg-sky-tint px-4 font-bold">পুজোর তিথি, আপনার দেশের ঘড়িতে →</a>
        @if ($others->isNotEmpty())
            <div class="mt-6 flex flex-wrap gap-2">@foreach ($others as $t)<a href="{{ $L::url($t, $year) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $L::TYPES[$t][0] }} {{ $N($year) }}</a>@endforeach
                <a href="{{ $B::url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">বাংলা ক্যালেন্ডার</a></div>
        @endif
        <x-ad-slot spot="list" :height="250" />
    </article>
</x-layouts.app>
