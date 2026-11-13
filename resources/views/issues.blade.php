{{-- /sonkha/: every issue, newest first, grouped by year (owner, 2026-11-12). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1120px] px-5 pb-4 pt-6">
        <nav aria-label="অবস্থান" class="flex items-center text-sm text-ink2"><a href="{{ url('/') }}/" class="inline-flex min-h-11 items-center hover:text-red">হাতপাখা</a><span class="px-1" aria-hidden="true">›</span><a href="{{ Url::to('/archive') }}" class="inline-flex min-h-11 items-center hover:text-red">আর্কাইভ</a></nav>
        <h1 class="font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">সব সংখ্যা</h1>
        <p class="mt-2 font-serif text-lg text-ink2">
            @if ($issues->isNotEmpty())
                হাতপাখার {{ Bn::num($issues->count()) }}টি সংখ্যা, নতুন থেকে পুরনো। প্রতিটি সংখ্যা পত্রিকার মতো পাতা উল্টে পড়া যায়।
            @else
                এখনও কোনও সংখ্যা প্রকাশিত হয়নি।
            @endif
        </p>
    </section>

    <div class="mx-auto max-w-[1120px] px-5 pb-12">
        @foreach ($byYear as $y => $list)
            <section class="mt-8" aria-labelledby="y{{ $y }}">
                <h2 id="y{{ $y }}" class="border-b-2 border-navy pb-1 font-display text-2xl font-extrabold text-red">{{ Bn::year($y) }}</h2>
                <ul class="mt-4 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-5">
                    @foreach ($list as $i)
                        <li>
                            <a href="{{ $i->url() }}" class="flex flex-col gap-1 hover:text-red">
                                <x-issue-cover :issue="$i" size="md" />
                                <span class="mt-1 font-bold leading-snug">{{ $i->title }}</span>
                                <span class="text-sm text-ink2">@if ($i->published_at){{ Bn::MONTHS[(int) $i->published_at->format('n') - 1] }} · @endif{{ Bn::num($i->posts_count) }}টি লেখা</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
            @if ($adAfter === $y)<x-ad-slot spot="list" :height="250" class="!my-10" />@endif {{-- D167 --}}
        @endforeach
    </div>
</x-layouts.app>
