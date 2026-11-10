{{-- D108: all serials, newest episode first. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1100px] px-5 pb-16 pt-10">
        <div class="font-hand text-2xl text-red">সপ্তাহে একটি করে পর্ব</div>
        <h1 class="font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">ধারাবাহিক</h1>
        <p class="mt-3 max-w-[60ch] font-serif text-lg text-ink2">রহস্য, অলৌকিক আর সম্পর্কের গল্প। প্রতি সপ্তাহে নতুন পর্ব; আগের পর্ব ভুলে গেলে গল্পের পাতাতেই সব পর্ব আছে।</p>
        @if ($serials->isEmpty())
            <p class="mt-8 font-serif text-lg">প্রথম ধারাবাহিক শিগগির শুরু হচ্ছে।</p>
        @else
            <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($serials as $s)
                    <li><a href="{{ $s->url() }}" class="lift block overflow-hidden rounded-2xl border-2 border-navy bg-white hover:bg-peach-light">
                        @if ($s->cover_path)
                            <img src="{{ asset($s->cover_path) }}" alt="" width="{{ $s->cover_width }}" height="{{ $s->cover_height }}" loading="lazy" class="aspect-[3/2] w-full object-cover">
                        @endif
                        <span class="block p-4">
                            <span class="text-sm font-bold text-red">{{ $s->genre }} · {{ $s->status === 'finished' ? 'সমাপ্ত' : 'প্রতি '.$s->dayName() }}</span>
                            <span class="mt-1 block font-display text-2xl font-extrabold leading-tight">{{ $s->title }}</span>
                            @if ($s->logline)<span class="mt-2 line-clamp-3 block font-serif text-ink2">{{ $s->logline }}</span>@endif
                            <span class="mt-2 block text-sm font-bold">{{ \App\Support\Bn::num((string) $s->liveEpisodes()->count()) }}টি পর্ব</span>
                        </span></a></li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
