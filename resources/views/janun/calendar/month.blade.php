{{-- D303: one Bengali month --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $B = \App\Support\BanglaDate::class;
    $prev = $month === 1 ? [$year - 1, 12] : [$year, $month - 1];
    $next = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
    $C = \App\Http\Controllers\BanglaCalendarController::class;
@endphp
<x-layouts.app :seo="$seo">
    <article class="mx-auto max-w-[760px] px-5 py-8">
        @include('janun.partials.head', ['crumbs' => [['হাতপাখা', $U('/')], ['জেনে রাখুন', $U('/janun')], ['বাংলা ক্যালেন্ডার', $B::url()], ['বঙ্গাব্দ '.\App\Support\Bn::num((string) $year), $B::url($year)], [$B::MONTHS[$month - 1], '']],
            'kicker' => 'বাংলা ক্যালেন্ডার', 'title' => $B::MONTHS[$month - 1].' '.\App\Support\Bn::num((string) $year).' ('.$span.')'])
        <p lang="en" class="mt-1 text-sm text-ink2">{{ $latin }}</p>
        @include('janun.calendar.grids', ['grids' => $grids, 'year' => $year, 'month' => $month])
        @include('janun.calendar.next', ['festival' => $nextFestival])
        <x-ad-slot spot="list" :height="250" />
        <a href="{{ $B::url() }}" class="mt-4 inline-flex min-h-11 items-center font-bold text-sky-night underline">আজকের বাংলা তারিখ →</a>
        <p class="mt-6 font-serif text-lg leading-relaxed">{{ $B::MONTHS[$month - 1] }} মাস {{ $B::SEASONS[$month - 1] }} ঋতুর মাস। পশ্চিমবঙ্গে মাস শুরু হয় সংক্রান্তির পরের দিন, পঞ্জিকা মেনে; বাংলাদেশে মাসের দিনসংখ্যা বাঁধা। তাই দুই বাংলার তারিখ এক-আধ দিন আলাদা হতে পারে।</p>
        <x-ad-slot spot="list" :height="250" />   {{-- a second ad below the page's text, away from the calendar (owner, 2026-11-27) --}}
    </article>
</x-layouts.app>
