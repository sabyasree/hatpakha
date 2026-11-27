{{-- D314: one government's holiday list, from the official notice; every date checked on two pages --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $F = \App\Support\FestivalPlan::class;
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $C = \App\Http\Controllers\BanglaCalendarController::class;
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['সরকারি ছুটি '.$N($l->year), $U('/janun/holidays/'.$l->year)], [$l->name(), '']],
            'kicker' => 'জেনে রাখুন · সরকারি ছুটি', 'title' => $title, 'trust' => 'শেষ যাচাই: '.\App\Support\Bn::date($l->checked_at)])
        <p lang="en" class="mt-1 text-sm text-ink2">{{ \App\Models\HolidayList::COUNTRIES[$l->country][1] }} government holiday list {{ $l->year }}</p>
        <p class="mt-4 font-serif text-lg leading-relaxed">{{ $N($l->year) }} সালে {{ $l->name() }} সরকারের ছুটির দিন: <b>{{ $N($days->count()) }}টি</b>।@if ($next) পরের ছুটি: <b>{{ $next->name }}</b>, {{ $F::show(\Carbon\CarbonImmutable::parse($next->day->toDateString()), false) }}।@endif</p>
        @if ($l->notice)<p class="mt-2 text-sm text-ink2">সূত্র: সরকারি প্রজ্ঞাপন {{ $l->notice }}@if ($l->notice_url) · <a href="{{ $l->notice_url }}" rel="nofollow noopener" target="_blank" class="underline">প্রজ্ঞাপন দেখুন</a>@endif</p>@endif
        <div class="mt-5 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr class="border-b-2 border-navy"><th class="py-2 pr-2">তারিখ</th><th class="py-2">ছুটি</th></tr></thead>
                <tbody>
                    @foreach ($days as $h)
                        @php($d = \Carbon\CarbonImmutable::parse($h->day->toDateString()))
                        @php($bn = $l->country === 'bangladesh' ? $B::bd($d) : ($B::wb($d) ?? null))
                        <tr class="border-b border-rule {{ $next && $h->is($next) ? 'bg-peach-light' : '' }}">
                            <th scope="row" class="py-2 pr-2 align-top"><span class="font-bold">{{ $C::short($d) }}</span><span class="block text-xs font-normal text-ink2">{{ $B::WEEKDAYS[$d->dayOfWeek] }}@if ($bn) · {{ $B::text($bn) }}@endif</span></th>
                            <td class="py-2 align-top"><span class="font-bold">{{ $h->name }}</span>
                                <span class="block text-xs text-ink2">@if ($h->kind){{ $h->kind }}@endif @if ($h->moon) · চাঁদ দেখা সাপেক্ষে@endif @if (\App\Http\Controllers\HolidayController::longWeekend($l->country, $d)) · <b class="text-red">লম্বা ছুটি</b>@endif</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-ink2">প্রতিটি তারিখ অন্তত দুটি সূত্রে মিলিয়ে দেখানো।@if ($held) আরও {{ $N($held) }}টি দিন মিলিয়ে দেখা হচ্ছে।@endif "লম্বা ছুটি": সপ্তাহান্তের আগে বা পরে, তাই টানা ছুটি।</p>
        <x-ad-slot spot="list" :height="250" />
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($other)<a href="{{ $other->url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">{{ $other->name() }}: ছুটির তালিকা {{ $N($other->year) }}</a>@endif
            <a href="{{ $B::url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">বাংলা ক্যালেন্ডার</a>
            <a href="{{ $U('/janun/puja/pujor-somoy') }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">পুজোর তিথি ও সময়</a>
        </div>
        <x-ad-slot spot="list" :height="250" />
        @include('janun.partials.read-magazine')
    </article>
</x-layouts.app>
