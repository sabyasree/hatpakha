{{-- The archive (owner-approved redesign, 2026-11-12, mockup with the UX review): issues first (this month's large,
     the five before it small), then collections, then a timeline of the years (bar length = pieces that year; only
     years with published pieces), then sections. Search on top. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $max = max($years ?: [1]);
    $issueWhen = fn ($i, string $count) => implode(' · ', array_filter([$i->published_at ? Bn::MONTHS[(int) $i->published_at->format('n') - 1].' '.Bn::num($i->published_at->format('Y')) : null, $count]));
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto flex max-w-[1120px] flex-wrap items-end justify-between gap-6 px-5 pb-4 pt-10">
        <div>
            <div class="font-hand text-2xl text-red">২০১২ থেকে আজ</div>
            <h1 class="font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">আর্কাইভ</h1>
            <p class="mt-2 font-serif text-lg text-ink2">হাতপাখার {{ Bn::num($total) }}টি প্রকাশিত লেখা। সংখ্যা, সংকলন, বছর বা বিভাগ ধরে খুঁজুন।</p>
        </div>
        <form action="{{ Url::to('/search') }}" method="get" role="search" class="flex w-full gap-2 md:w-[26rem]">
            <label for="archive-q" class="sr-only">আর্কাইভে খুঁজুন</label>
            <input id="archive-q" type="search" name="q" placeholder="লেখা, লেখক বা শব্দ দিয়ে খুঁজুন" class="h-12 min-w-0 flex-1 rounded-full border-2 border-navy bg-white px-5 text-base">
            <button type="submit" class="h-12 rounded-full bg-navy px-6 font-bold text-white">খুঁজুন</button>
        </form>
    </section>

    @if ($latestIssue)
        <section class="mx-auto max-w-[1120px] px-5 py-6" aria-labelledby="issues">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="issues" class="font-display text-2xl font-extrabold">সংখ্যা</h2>
                <a href="{{ Url::to('/sonkha') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">সব সংখ্যা →</a>
            </div>
            <div class="mt-4 grid gap-6 lg:grid-cols-[5fr_7fr]">
                <a href="{{ $latestIssue->url() }}" class="flex items-center gap-5 rounded-2xl border-2 border-navy bg-peach-light p-4 shadow-[4px_4px_0_#13212C] hover:bg-peach">
                    <x-issue-cover :issue="$latestIssue" size="lg" class="!h-36 !w-28 sm:!h-44 sm:!w-32" />
                    <span class="flex flex-col gap-1">
                        @if ($latestIssue->published_at && $latestIssue->published_at->gt(now()->subDays(45)))
                            <span class="self-start rounded-full bg-red px-3 text-sm font-bold text-white">নতুন সংখ্যা</span>
                        @endif
                        <span class="font-display text-2xl font-extrabold leading-tight md:text-3xl">{{ $latestIssue->title }}</span>
                        <span class="text-ink2">{{ $issueWhen($latestIssue, Bn::num($latestIssue->posts_count).'টি লেখা') }}</span>
                        <span class="font-bold text-red">পড়ুন →</span>
                    </span>
                </a>
                @if ($olderIssues->isNotEmpty())
                    <div>
                        <p class="text-sm font-bold text-ink2">আগের সংখ্যা</p>
                        <ul class="-mx-5 mt-2 flex gap-4 overflow-x-auto px-5 pb-2 lg:mx-0 lg:grid lg:grid-cols-5 lg:overflow-visible lg:px-0">
                            @foreach ($olderIssues as $i)
                                <li class="w-24 flex-none lg:w-auto">
                                    <a href="{{ $i->url() }}" class="flex flex-col gap-1 hover:text-red">
                                        <x-issue-cover :issue="$i" size="md" />
                                        <span class="mt-1 font-bold leading-snug">{{ $i->title }}</span>
                                        <span class="text-sm text-ink2">{{ $issueWhen($i, Bn::num($i->posts_count).'টি') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($collections->isNotEmpty())
        {{-- D99 + owner (2026-11-12): collections before the years; compact cards; side by side on a phone --}}
        <section class="mx-auto max-w-[1120px] px-5 py-6" aria-labelledby="collections-h">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="collections-h" class="font-display text-2xl font-extrabold">সংকলন</h2>
                <a href="{{ Url::to('/sonkolon') }}" class="inline-flex min-h-11 items-center font-bold text-sky-night underline">সব সংকলন →</a>
            </div>
            <ul class="-mx-5 mt-4 flex snap-x gap-4 overflow-x-auto px-5 pb-2 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-4">
                @foreach ($collections as $collection)
                    <li class="w-64 flex-none snap-start md:w-auto"><x-collection-card :collection="$collection" compact /></li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="mx-auto grid max-w-[1120px] gap-10 px-5 py-6 lg:grid-cols-[7fr_5fr]">
        <section aria-labelledby="years">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="years" class="font-display text-2xl font-extrabold">বছর ধরে</h2>
                <span class="text-sm text-ink2">দাগ যত লম্বা, সে বছর তত লেখা</span>
            </div>
            <ol class="mt-3 border-t border-rule">
                @foreach ($years as $y => $n)
                    <li class="border-b border-rule">
                        <a href="{{ Url::to('/archive/'.$y) }}" class="grid min-h-12 grid-cols-[4rem_minmax(0,1fr)_4rem] items-center gap-3 px-1 hover:bg-peach-light/60">
                            <span class="font-display text-xl font-extrabold">{{ Bn::year($y) }}</span>
                            <span class="h-3.5 overflow-hidden rounded-full bg-peach-light" aria-hidden="true"><span class="block h-full rounded-full border-r-[3px] border-red bg-peach" style="width: {{ max(2, (int) round($n / $max * 100)) }}%"></span></span>
                            <span class="text-right font-semibold">{{ Bn::num($n) }}টি</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>

        <section aria-labelledby="sections">
            <h2 id="sections" class="font-display text-2xl font-extrabold">বিভাগ ধরে</h2>
            <div class="mt-3 flex flex-wrap gap-2.5">
                @foreach ($sections as $s)
                    <a href="{{ $s->url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy bg-white px-4 font-semibold hover:bg-navy hover:text-white">{{ $s->name_bn }}</a>
                @endforeach
            </div>
            <div class="tilt-b mt-6 rounded-2xl bg-sky-tint px-5 py-4 text-sky-night">
                <p class="font-bold">কোনও লেখকের সব লেখা?</p>
                <p class="text-sm">ওপরের খোঁজার ঘরে লেখকের নাম লিখুন।</p>
            </div>
        </section>
    </div>
</x-layouts.app>
