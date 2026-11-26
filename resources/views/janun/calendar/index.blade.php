{{-- D303: today's Bengali date in both Bengals, this month, and the years --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $C = \App\Http\Controllers\BanglaCalendarController::class;
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা ক্যালেন্ডার', '']], 'kicker' => 'জেনে রাখুন', 'title' => 'বাংলা ক্যালেন্ডার'])
        <section class="mt-5 rounded-2xl bg-navy p-5 text-white" aria-label="আজকের তারিখ">
            <p class="text-sm font-bold">আজ, {{ $B::WEEKDAYS[$d->dayOfWeek] }}</p>
            @if ($wb)
                <p class="mt-1 font-display text-4xl font-extrabold leading-tight">{{ $B::text($wb) }}</p>
                <p class="mt-1">পশ্চিমবঙ্গ@if ($wb !== $bd) · বাংলাদেশে {{ $B::text($bd) }}@else ও বাংলাদেশ@endif</p>
            @else
                <p class="mt-1 font-display text-4xl font-extrabold leading-tight">{{ $B::text($bd) }}</p>
                <p class="mt-1">বাংলাদেশ (পশ্চিমবঙ্গের তারিখ মিলিয়ে দেখা হচ্ছে)</p>
            @endif
            <p class="mt-2 text-sm">{{ $C::short($d) }} {{ \App\Support\Bn::num((string) $d->year) }} · ঋতু: {{ $B::SEASONS[($wb ?? $bd)[1] - 1] }}</p>
        </section>
        @include('janun.calendar.grids', ['grids' => $grids, 'year' => $year, 'month' => $month])
        <x-ad-slot spot="list" :height="250" />
        <h2 class="mt-8 font-display text-2xl font-extrabold">বছরের ক্যালেন্ডার</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (range($C::FIRST_YEAR, $B::bd($d)[0] + 1) as $y)<a href="{{ $B::url($y) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold hover:bg-peach-light">বঙ্গাব্দ {{ \App\Support\Bn::num((string) $y) }}</a>@endforeach
            @if (\App\Support\FestivalPlan::pageLive())<a href="{{ $U('/janun/puja/pujor-somoy') }}" class="flex min-h-11 items-center rounded-full bg-sky-tint px-4 font-bold">পুজোর তিথি ও সময় →</a>@endif
        </div>
        <h2 class="mt-8 font-display text-2xl font-extrabold">দুই বাংলার তারিখ আলাদা কেন?</h2>
        <p class="mt-3 font-serif text-lg leading-relaxed">বাংলাদেশে ১৯৮৭ সাল থেকে সংশোধিত বাংলা ক্যালেন্ডার চলে: পয়লা বৈশাখ প্রতি বছর ১৪ এপ্রিল, আর প্রতিটি মাসের দিনসংখ্যা বাঁধা (সর্বশেষ সংশোধন ২০১৯)। পশ্চিমবঙ্গে মাস শুরু হয় পঞ্জিকা মেনে, সূর্য যখন এক রাশি থেকে পরের রাশিতে যায় (সংক্রান্তি), তার পরের দিন; তাই মাসের দৈর্ঘ্য ২৯ থেকে ৩২ দিন, আর তারিখ বাংলাদেশের চেয়ে এক-আধ দিন আলাদা হয়।</p>
        <p class="mt-3 text-sm text-ink2">পশ্চিমবঙ্গের মাস: সংক্রান্তির হিসেব কষে, তারপর অন্তত দুটি প্রকাশিত ক্যালেন্ডারের সঙ্গে মিলিয়ে দেখানো। পুজোর দিন: বিশুদ্ধ সিদ্ধান্ত পঞ্জিকা অনুযায়ী, দুটি সূত্রে মিলিয়ে।</p>
    </article>
</x-layouts.app>
