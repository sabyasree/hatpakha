{{-- D97: all themed collections. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1100px] px-5 py-12">
        <div class="font-hand text-2xl text-red">হাতপাখার আর্কাইভ থেকে</div>
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">সংকলন</h1>
        <p class="mt-4 max-w-[65ch] font-serif text-lg text-ink2">চোদ্দো বছরের লেখা থেকে বিষয় ধরে বাছাই: ঋতু, উৎসব, মা-বাবা, প্রেম, ছেলেবেলা।</p>
        @forelse ($collections as $c)
            @if ($loop->first)<ul class="mt-8 grid gap-4 sm:grid-cols-2">@endif
                <li><a href="{{ $c->url() }}" class="block h-full rounded-3xl border-2 border-navy bg-white p-5 shadow-[4px_4px_0_#13212C] hover:bg-peach-light">
                    <span class="block font-display text-2xl font-extrabold">{{ $c->title }}</span>
                    @if ($c->intro)<span class="mt-2 block font-serif text-ink2">{{ \Illuminate\Support\Str::limit($c->intro, 160) }}</span>@endif
                    <span class="mt-3 block text-sm font-bold text-red">{{ \App\Support\Bn::num((string) count((array) $c->post_ids)) }}টি লেখা →</span>
                </a></li>
            @if ($loop->last)</ul>@endif
        @empty
            <p class="mt-8 rounded-2xl border-2 border-dashed border-navy p-6 font-serif text-lg">প্রথম সংকলন তৈরি হচ্ছে। শিগগির এখানে পড়তে পাবেন।</p>
        @endforelse
    </section>
</x-layouts.app>
