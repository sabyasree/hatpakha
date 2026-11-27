{{-- D311 (owner approved the mock-up, 2026-11-27): a quiet strip near the end of the home page, after the literature --}}
@php
    $U = fn ($p) => \App\Support\Url::to($p);
    $today = rescue(fn () => \App\Http\Controllers\BanglaCalendarController::todayLine(), null, false);
    $next = rescue(fn () => \App\Http\Controllers\BanglaCalendarController::nextFestival(), null, false);
@endphp
<section class="mx-auto max-w-[1320px] px-5 pb-12" aria-labelledby="janun-h">
    <h2 id="janun-h" class="font-display text-4xl font-extrabold leading-none" style="font-stretch: 78%">জেনে রাখুন</h2>
    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <a href="{{ \App\Support\BanglaDate::url() }}" class="flex min-h-24 flex-col justify-center rounded-2xl border-2 border-navy bg-white px-4 py-3 hover:bg-peach-light">
            <span class="text-sm font-bold text-ink2">বাংলা ক্যালেন্ডার</span>
            <span class="mt-1 font-display text-xl font-extrabold">{{ $today[0] ?? 'আজ বাংলা কত তারিখ?' }}</span>
        </a>
        <a href="{{ $next[2] ?? $U('/janun/puja/pujor-somoy') }}" class="flex min-h-24 flex-col justify-center rounded-2xl border-2 border-navy bg-peach-light px-4 py-3 hover:bg-peach">
            <span class="text-sm font-bold text-ink2">পুজোর তিথি ও সময়</span>
            <span class="mt-1 font-display text-xl font-extrabold">@if ($next){{ $next[0] }}: {{ \App\Http\Controllers\BanglaCalendarController::short($next[1]) }}@else আপনার দেশের ঘড়িতে @endif</span>
        </a>
        <a href="{{ $U('/janun') }}" class="flex min-h-24 flex-col justify-center rounded-2xl border-2 border-navy bg-white px-4 py-3 hover:bg-peach-light">
            <span class="text-sm font-bold text-ink2">ভাষার ভাণ্ডার</span>
            <span class="mt-1 font-display text-xl font-extrabold">প্রবাদ, বানান, মন্ত্রের মানে</span>
        </a>
    </div>
</section>
