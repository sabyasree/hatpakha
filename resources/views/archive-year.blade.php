{{-- One year of the archive (owner-approved redesign, 2026-11-12): a bar with the years, the months and the sections
     (?section=…, noindex with the year as canonical), the year's four most-read pieces with covers, then the pieces month
     by month (day · title · writer · section tag). A month over 40 pieces shows 20 and keeps the rest a tap away (still
     in the page). The years before and after at the bottom. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $tag = fn (?string $slug) => match ($slug) {
        'poetry' => 'bg-sky-tint text-sky-night',
        'short-stories' => 'bg-peach-light text-red-dark',
        'social' => 'bg-peach text-navy',
        'literature' => 'bg-pink text-navy',
        'english' => 'border border-navy bg-white text-navy',
        default => 'bg-rule text-navy',
    };
    $yearUrl = fn (?string $section = null) => Url::to('/archive/'.$year).($section ? '?section='.$section : '');
    $chip = 'flex min-h-10 flex-none items-center gap-1 rounded-full border-2 border-navy px-3 font-semibold';
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1120px] px-5 pb-4 pt-6">
        <nav aria-label="অবস্থান" class="flex items-center text-sm text-ink2"><a href="{{ url('/') }}/" class="inline-flex min-h-11 items-center hover:text-red">হাতপাখা</a><span class="px-1" aria-hidden="true">›</span><a href="{{ Url::to('/archive') }}" class="inline-flex min-h-11 items-center hover:text-red">আর্কাইভ</a></nav>
        <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1">
            <h1 class="font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">{{ Bn::year($year) }}</h1>
            <p class="font-serif text-lg text-ink2">এই বছরে প্রকাশিত {{ Bn::num($all->count()) }}টি লেখা@if ($sectionCounts->count() > 1)<span class="hidden md:inline">: {{ $sectionCounts->map(fn ($c) => $c['section']->name_bn.' '.Bn::num($c['n']))->implode(' · ') }}</span>@endif</p>
        </div>
    </section>

    {{-- Years, months, sections: one bar, kept in view on a laptop; sideways rows on a phone --}}
    <div class="z-20 md:sticky md:top-2 md:mx-auto md:max-w-[1120px] md:px-5">
        <div class="flex flex-col gap-2 border-y-2 border-navy bg-paper py-2 md:rounded-2xl md:border-2 md:px-4">
            <nav aria-label="বছর" class="flex items-center gap-2 overflow-x-auto px-5 md:px-0">
                <span class="hidden w-14 flex-none text-sm font-bold text-ink2 md:block">বছর</span>
                @foreach ($years as $y => $n)
                    <a href="{{ Url::to('/archive/'.$y) }}" @if ($y === $year) aria-current="page" @endif class="{{ $chip }} {{ $y === $year ? 'bg-navy text-white' : 'bg-white hover:bg-peach-light' }}" @if ($y === $year) data-current-year @endif>{{ Bn::year($y) }}</a>
                @endforeach
            </nav>
            <nav aria-label="মাস" class="flex items-center gap-2 overflow-x-auto px-5 md:px-0">
                <span class="hidden w-14 flex-none text-sm font-bold text-ink2 md:block">মাস</span>
                @foreach ($months as $m => $list)
                    <a href="#m{{ $m }}" class="{{ $chip }} bg-white hover:bg-peach-light">{{ Bn::MONTHS[$m - 1] }} <span class="text-ink2">{{ Bn::num($list->count()) }}</span></a>
                @endforeach
            </nav>
            @if ($sectionCounts->count() > 1)
                <nav aria-label="বিভাগ" class="flex items-center gap-2 overflow-x-auto px-5 md:px-0">
                    <span class="hidden w-14 flex-none text-sm font-bold text-ink2 md:block">বিভাগ</span>
                    <a href="{{ $yearUrl() }}" @if (! $filter) aria-current="true" @endif class="{{ $chip }} {{ $filter ? 'bg-white hover:bg-peach-light' : 'bg-navy text-white' }}">সব {{ Bn::num($all->count()) }}</a>
                    @foreach ($sectionCounts as $slug => $c)
                        <a href="{{ $yearUrl($slug) }}" rel="nofollow" @if ($filter === $slug) aria-current="true" @endif class="{{ $chip }} {{ $filter === $slug ? 'bg-navy text-white' : 'bg-white hover:bg-peach-light' }}">{{ $c['section']->name_bn }} {{ Bn::num($c['n']) }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>

    <div class="mx-auto max-w-[1120px] px-5 pb-10">
        @if ($picks->isNotEmpty())
            <section class="mt-8" aria-labelledby="picks">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <h2 id="picks" class="font-display text-2xl font-extrabold">এ বছরের বাছাই</h2>
                    <span class="text-sm text-ink2">পাঠকেরা যে লেখাগুলো সবচেয়ে বেশি পড়েছেন</span>
                </div>
                <ul class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-5">
                    @foreach ($picks as $p)
                        <li>
                            <a href="{{ $p->url() }}" class="flex h-full flex-col overflow-hidden rounded-xl border-2 border-navy bg-white hover:bg-peach-light">
                                @php($v = \App\Support\Images::variants($p->featured_image_path))
                                <img src="{{ $v ? reset($v) : asset(ltrim($p->featured_image_path, '/')) }}" alt="" loading="lazy" class="h-28 w-full border-b-2 border-navy object-cover md:h-36">
                                <span class="flex flex-col gap-1 p-3">
                                    @if ($p->section)<span class="hidden self-start rounded-full px-2 text-xs font-bold md:inline {{ $tag($p->section->slug) }}">{{ $p->section->name_bn }}</span>@endif
                                    <span class="font-serif font-bold leading-snug md:text-lg">{{ $p->title }}</span>
                                    <span class="text-sm text-ink2">{{ $p->authors->first()?->publicName() }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="max-w-[860px]">
            @foreach ($months as $m => $list)
                <section id="m{{ $m }}" class="mt-8 scroll-mt-48">
                    <div class="flex items-baseline gap-2 border-b-2 border-navy pb-1">
                        <h2 class="font-display text-2xl font-extrabold text-red">{{ Bn::MONTHS[$m - 1] }}</h2>
                        <span class="text-sm text-ink2">{{ Bn::num($list->count()) }}টি লেখা</span>
                    </div>
                    @php($long = $list->count() > 40)
                    @foreach ($long ? [$list->take(20), $list->slice(20)] : [$list] as $part)
                        @if ($loop->index === 1)<details class="group"><summary class="mt-3 flex min-h-12 cursor-pointer list-none items-center justify-center rounded-full border-2 border-navy font-bold hover:bg-peach-light group-open:hidden">আরও {{ Bn::num($part->count()) }}টি দেখুন</summary>@endif
                        <ol class="divide-y divide-rule border-b border-rule">
                            @foreach ($part as $p)
                                <li>
                                    <a href="{{ $p->url() }}" class="grid min-h-13 grid-cols-[2rem_minmax(0,1fr)] items-start gap-3 py-2.5 hover:bg-peach-light/60 md:grid-cols-[2.75rem_minmax(0,1fr)_auto] md:items-center md:gap-4">
                                        <span class="pt-0.5 text-center text-lg font-bold text-ink2 md:pt-0">{{ Bn::num($p->published_at->format('j')) }}</span>
                                        <span class="flex min-w-0 flex-col gap-0.5">
                                            <span class="font-serif text-[17px] font-bold leading-snug md:text-lg">{{ $p->title }}</span>
                                            <span class="flex flex-wrap items-center gap-2 text-sm text-ink2">
                                                @if ($p->section)<span class="rounded-full px-2 text-xs font-bold md:hidden {{ $tag($p->section->slug) }}">{{ $p->section->name_bn }}</span>@endif
                                                {{ $p->authors->first()?->publicName() }}
                                            </span>
                                        </span>
                                        @if ($p->section)<span class="hidden whitespace-nowrap rounded-full px-2.5 py-0.5 text-[13px] font-bold md:inline {{ $tag($p->section->slug) }}">{{ $p->section->name_bn }}</span>@endif
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                        @if ($loop->index === 1)</details>@endif
                    @endforeach
                </section>
            @endforeach

            <nav aria-label="আগের ও পরের বছর" class="mt-10 grid grid-cols-2 gap-3">
                @if ($older)<a href="{{ Url::to('/archive/'.$older) }}" class="flex min-h-12 items-center justify-center rounded-full border-2 border-navy font-bold hover:bg-peach-light">← {{ Bn::year($older) }} ({{ Bn::num($years[$older]) }}টি)</a>@else<span></span>@endif
                @if ($newer)<a href="{{ Url::to('/archive/'.$newer) }}" class="flex min-h-12 items-center justify-center rounded-full border-2 border-navy font-bold hover:bg-peach-light">{{ Bn::year($newer) }} ({{ Bn::num($years[$newer]) }}টি) →</a>@endif
            </nav>
        </div>
    </div>
    {{-- On a phone, bring the current year into view in the sideways year row --}}
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.querySelector('[data-current-year]')?.scrollIntoView({ inline: 'center', block: 'nearest' });</script>
</x-layouts.app>
