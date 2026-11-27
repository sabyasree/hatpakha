{{-- D303: today's Bengali date in both Bengals, this month, and the years --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $C = \App\Http\Controllers\BanglaCalendarController::class;
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা ক্যালেন্ডার', '']], 'kicker' => 'বাংলা ক্যালেন্ডার '.\App\Support\Bn::num((string) $bd[0]), 'title' => 'আজ বাংলা কত তারিখ?'])
        {{-- the answer in one plain sentence, in the page as sent (SEO review: what Google shows as the answer) --}}
        <p class="mt-3 font-serif text-lg leading-relaxed">আজ {{ $B::WEEKDAYS[$d->dayOfWeek] }}, {{ $C::short($d) }} {{ \App\Support\Bn::num((string) $d->year) }}: @if ($wb)পশ্চিমবঙ্গে <b>{{ $B::text($wb) }}</b>, বাংলাদেশে <b>{{ $B::text($bd) }}</b>@else বাংলাদেশে <b>{{ $B::text($bd) }}</b>@endif বঙ্গাব্দ।@if ($tithi) আজকের তিথি: {{ $tithi->text() }} (ভারতের সময়)।@endif</p>
        <section class="mt-5 rounded-2xl bg-navy p-5 text-white" aria-label="আজকের তারিখ">
            <p class="text-sm font-bold">আজ, {{ $B::WEEKDAYS[$d->dayOfWeek] }}</p>
            @if ($wb)
                <div data-today="wb">
                    <p class="mt-1 font-display text-4xl font-extrabold leading-tight">{{ $B::text($wb) }}</p>
                    <p class="mt-1">পশ্চিমবঙ্গ@if ($wb !== $bd) · বাংলাদেশে {{ $B::text($bd) }}@else ও বাংলাদেশ@endif</p>
                </div>
                <div data-today="bd" hidden>
                    <p class="mt-1 font-display text-4xl font-extrabold leading-tight">{{ $B::text($bd) }}</p>
                    <p class="mt-1">বাংলাদেশ@if ($wb !== $bd) · পশ্চিমবঙ্গে {{ $B::text($wb) }}@else ও পশ্চিমবঙ্গ@endif</p>
                </div>
            @else
                <p class="mt-1 font-display text-4xl font-extrabold leading-tight">{{ $B::text($bd) }}</p>
                <p class="mt-1">বাংলাদেশ (পশ্চিমবঙ্গের তারিখ মিলিয়ে দেখা হচ্ছে)</p>
            @endif
            @if ($tithi)<p class="mt-2 text-sm"><b>তিথি:</b> {{ $tithi->text() }} <span class="opacity-80">(ভারতের সময়)</span></p>@endif   {{-- D310 --}}
            <p class="mt-2 text-sm">{{ $C::short($d) }} {{ \App\Support\Bn::num((string) $d->year) }} · ঋতু: {{ $B::SEASONS[($wb ?? $bd)[1] - 1] }} · দিন বদলায় ভারতীয় সময়ে</p>
        </section>
        @include('janun.calendar.grids', ['grids' => $grids, 'year' => $year, 'month' => $month])
        @include('janun.calendar.next', ['festival' => $nextFestival])
        <x-ad-slot spot="list" :height="250" />
        @php($lunarY = $d->year + ($d->month === 12 ? 1 : 0))
        @php($lunarLinks = collect(array_keys(\App\Support\LunarDays::TYPES))->filter(fn ($t) => count(\App\Support\LunarDays::year($t, $lunarY)) >= 6))
        @if ($lunarLinks->isNotEmpty())
            {{-- D313: the year's lunar days, one tap from the calendar --}}
            <div class="mt-6 flex flex-wrap gap-2">@foreach ($lunarLinks as $t)<a href="{{ \App\Support\LunarDays::url($t, $lunarY) }}" class="flex min-h-11 items-center rounded-full bg-peach-light px-4 font-bold hover:bg-peach">{{ \App\Support\LunarDays::TYPES[$t][0] }} {{ \App\Support\Bn::num((string) $lunarY) }} →</a>@endforeach</div>
        @endif
        <h2 class="mt-8 font-display text-2xl font-extrabold">বছরের ক্যালেন্ডার</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (range($C::FIRST_YEAR, $B::bd($d)[0] + 1) as $y)<a href="{{ $B::url($y) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">বঙ্গাব্দ {{ \App\Support\Bn::num((string) $y) }}</a>@endforeach
            @if (\App\Support\FestivalPlan::pageLive())<a href="{{ $U('/janun/puja/pujor-somoy') }}" class="flex min-h-11 items-center rounded-full bg-sky-tint px-4 font-bold">পুজোর তিথি ও সময় →</a>@endif
        </div>
        <h2 class="mt-8 font-display text-2xl font-extrabold">দুই বাংলার তারিখ আলাদা কেন?</h2>
        <p class="mt-3 font-serif text-lg leading-relaxed">বাংলাদেশে ১৯৮৭ সাল থেকে সংশোধিত বাংলা ক্যালেন্ডার চলে: পয়লা বৈশাখ প্রতি বছর ১৪ এপ্রিল, আর প্রতিটি মাসের দিনসংখ্যা বাঁধা (সর্বশেষ সংশোধন ২০১৯)। পশ্চিমবঙ্গে মাস শুরু হয় পঞ্জিকা মেনে, সূর্য যখন এক রাশি থেকে পরের রাশিতে যায় (সংক্রান্তি), তার পরের দিন; তাই মাসের দৈর্ঘ্য ২৯ থেকে ৩২ দিন, আর তারিখ বাংলাদেশের চেয়ে এক-আধ দিন আলাদা হয়।</p>
        <p class="mt-3 text-sm text-ink2">পশ্চিমবঙ্গের মাস: সংক্রান্তির হিসেব কষে, তারপর প্রকাশিত ক্যালেন্ডারের সঙ্গে মিলিয়ে দেখানো। পুজোর দিন: বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা অনুযায়ী, প্রকাশিত সূত্রে মিলিয়ে।</p>
        {{-- a second ad after the reading text, far from the calendar and its buttons (owner, 2026-11-27: "can i add some more ads"; D111/D167: at most 3 on a phone page) --}}
        <x-ad-slot spot="list" :height="250" />
    </article>
</x-layouts.app>
