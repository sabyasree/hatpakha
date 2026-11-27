{{-- D308: a year's festivals, each with its date in both Bengals and a link to its page --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $F = \App\Support\FestivalPlan::class;
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['পুজো-পার্বণ', $U('/janun/puja')], ['পুজো-পার্বণ '.$N($year), '']],
            'kicker' => 'জেনে রাখুন · পুজো-পার্বণ', 'title' => 'পুজো-পার্বণ '.$N($year).': তারিখের তালিকা'])
        <p lang="en" class="mt-1 text-sm text-ink2">Bengali festivals {{ $year }}: dates</p>
        <p class="mt-4 font-serif text-lg leading-relaxed">{{ $N($year) }} সালের বাঙালি পুজো-পার্বণ: ইংরেজি তারিখ, পশ্চিমবঙ্গ আর বাংলাদেশের বাংলা তারিখ। প্রতিটির পাতায় তিথির সময় আপনার দেশের ঘড়িতে।</p>
        <ul class="mt-5 space-y-2">
            @foreach ($items as $it)
                <li><a href="{{ $it['url'] }}" class="flex min-h-14 flex-wrap items-center gap-x-3 rounded-2xl border-2 border-navy px-4 py-2 hover:bg-peach-light">
                    <span class="font-display text-lg font-extrabold">{{ $it['name'] }}</span>
                    @if ($it['day'])<span class="text-sm">{{ $F::show($it['day'], false) }}@if ($it['wb']) · {{ $B::text($it['wb']) }}@endif</span>@endif
                </a></li>
                @if ($loop->iteration === 5 && $loop->remaining > 1)<li><x-ad-slot spot="list" :height="250" class="!my-2" /></li>@endif
            @endforeach
        </ul>
        <p class="mt-4 text-sm text-ink2">তারিখ: বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা (কলকাতা), প্রকাশিত সূত্রে মিলিয়ে। যে পার্বণের সময় এখনও মেলানো হয়নি, সেটি তালিকায় পরে আসবে।</p>
        @if ($years->count() > 1)
            <div class="mt-6 flex flex-wrap gap-2">@foreach ($years as $y)@if ($y !== $year)<a href="{{ $U('/janun/puja/'.$y) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">পুজো-পার্বণ {{ $N($y) }}</a>@endif @endforeach</div>
        @endif
        <x-ad-slot spot="list" :height="250" />
        <a href="{{ $U('/janun/puja/pujor-somoy') }}" class="mt-4 inline-flex min-h-11 items-center font-bold text-sky-night underline">তিথি আর সময়, আপনার দেশের ঘড়িতে →</a>
    </article>
</x-layouts.app>
