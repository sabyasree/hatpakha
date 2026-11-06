<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1100px] px-5 pb-6 pt-10">
        <div class="font-hand text-2xl text-red">২০১২ থেকে আজ</div>
        <h1 class="font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">আর্কাইভ</h1>
        <p class="mt-3 max-w-[60ch] font-serif text-lg text-ink2">হাতপাখার {{ \App\Support\Bn::num($total) }}টি প্রকাশিত লেখা। বছর ধরে, সংখ্যা ধরে, বা বিভাগ ধরে খুঁজুন।</p>
    </section>

    <section class="mx-auto max-w-[1100px] px-5 py-6" aria-labelledby="years">
        <h2 id="years" class="mb-4 font-display text-2xl font-extrabold">বছর ধরে</h2>
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
            @foreach ($years as $y => $n)
                <a href="{{ \App\Support\Url::to('/archive/'.$y) }}" class="lift flex min-h-24 flex-col justify-between rounded-xl border-2 border-navy bg-peach-light p-3 hover:bg-peach {{ $loop->odd ? 'tilt-a' : 'tilt-b' }}">
                    <span class="font-display text-3xl font-extrabold">{{ \App\Support\Bn::year($y) }}</span>
                    <span class="text-sm font-semibold text-ink2">{{ \App\Support\Bn::num($n) }}টি লেখা</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($issues->isNotEmpty())
        <section class="mx-auto max-w-[1100px] px-5 py-6" aria-labelledby="issues">
            <h2 id="issues" class="mb-4 font-display text-2xl font-extrabold">সংখ্যা</h2>
            <div class="flex flex-wrap gap-3">
                @foreach ($issues as $issue)
                    <a href="{{ $issue->url() }}" class="flex min-h-11 items-center rounded-full bg-red px-5 font-bold text-white">{{ $issue->title }}</a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-[1100px] px-5 py-6" aria-labelledby="sections">
        <h2 id="sections" class="mb-4 font-display text-2xl font-extrabold">বিভাগ ধরে</h2>
        <div class="flex flex-wrap gap-3">
            @foreach ($sections as $s)
                <a href="{{ $s->url() }}" class="flex min-h-11 items-center rounded-full border-2 border-navy px-5 font-bold hover:bg-navy hover:text-paper">{{ $s->name_bn }}</a>
            @endforeach
        </div>
    </section>
</x-layouts.app>
