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
            'kicker' => 'বাংলা ক্যালেন্ডার', 'title' => $B::MONTHS[$month - 1].' '.\App\Support\Bn::num((string) $year)])
        @include('janun.calendar.grids', ['grids' => $grids, 'year' => $year, 'month' => $month])
        <nav class="mt-6 flex justify-between gap-2" aria-label="আগের ও পরের মাস">
            @if ($C::offered($prev[0]))<a href="{{ $B::url(...$prev) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">← {{ $B::MONTHS[$prev[1] - 1] }}</a>@else<span></span>@endif
            @if ($C::offered($next[0]))<a href="{{ $B::url(...$next) }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-4 font-bold">{{ $B::MONTHS[$next[1] - 1] }} →</a>@endif
        </nav>
        <x-ad-slot spot="list" :height="250" />
        <a href="{{ $B::url() }}" class="mt-4 inline-flex min-h-11 items-center font-bold text-sky-night underline">আজকের বাংলা তারিখ →</a>
    </article>
</x-layouts.app>
