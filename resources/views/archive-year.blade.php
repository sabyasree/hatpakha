<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[900px] px-5 pb-4 pt-8">
        <nav aria-label="অবস্থান" class="flex items-center text-sm text-ink2"><a href="{{ url('/') }}/" class="inline-flex min-h-11 items-center hover:text-red">হাতপাখা</a><span class="px-1" aria-hidden="true">›</span><a href="{{ \App\Support\Url::to('/archive') }}" class="inline-flex min-h-11 items-center hover:text-red">আর্কাইভ</a></nav>
        <h1 class="mt-3 font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">{{ \App\Support\Bn::year($year) }}</h1>
        <p class="mt-2 font-serif text-lg text-ink2">এই বছরে প্রকাশিত {{ \App\Support\Bn::num($posts->count()) }}টি লেখা</p>

        {{-- Year switcher: scrolls sideways on phones --}}
        <nav aria-label="অন্য বছর" class="-mx-5 mt-5 flex gap-2 overflow-x-auto px-5 pb-2">
            @foreach ($years as $y => $n)
                <a href="{{ \App\Support\Url::to('/archive/'.$y) }}" @if ($y === $year) aria-current="page" @endif
                   class="flex h-11 flex-none items-center rounded-full border-2 border-navy px-4 font-bold {{ $y === $year ? 'bg-navy text-paper' : 'hover:bg-peach-light' }}">{{ \App\Support\Bn::year($y) }}</a>
            @endforeach
        </nav>

        {{-- Month jump links --}}
        <nav aria-label="মাস" class="sticky top-0 z-20 -mx-5 mt-4 flex gap-2 overflow-x-auto border-b border-rule bg-paper/95 px-5 py-2 text-sm font-bold backdrop-blur sm:mx-0 sm:flex-wrap sm:px-0">
            @foreach ($months as $m => $list)
                <a href="#m{{ $m }}" class="inline-flex h-11 flex-none items-center gap-1 rounded-full border-2 border-navy bg-white px-3 hover:bg-peach-light">{{ \App\Support\Bn::MONTHS[$m - 1] }} <span class="text-ink2">{{ \App\Support\Bn::num($list->count()) }}</span></a>
            @endforeach
        </nav>
    </section>

    <div class="mx-auto max-w-[900px] px-5 pb-10">
        @foreach ($months as $m => $list)
            <section id="m{{ $m }}" class="scroll-mt-20 border-t-2 border-navy pt-5 mt-6">
                <h2 class="font-display text-2xl font-extrabold text-red">{{ \App\Support\Bn::MONTHS[$m - 1] }}</h2>
                <ol class="mt-2 divide-y divide-rule">
                    @foreach ($list as $p)
                        <li>
                            <a href="{{ $p->url() }}" class="block py-3 hover:bg-peach-light/60">
                                <span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                                <span class="mt-0.5 block text-sm text-ink2">{{ $p->authors->first()?->publicName() }}@if ($p->section) · {{ $p->section->name_bn }}@endif · {{ \App\Support\Bn::date($p->published_at) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
</x-layouts.app>
