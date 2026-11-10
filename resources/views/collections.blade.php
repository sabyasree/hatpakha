{{-- D97: all themed collections. --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1100px] px-5 py-12">
        <div class="font-hand text-2xl text-red">হাতপাখার আর্কাইভ থেকে</div>
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">সংকলন</h1>
        <p class="mt-4 max-w-[65ch] font-serif text-lg text-ink2">চোদ্দো বছরের লেখা থেকে বিষয় ধরে বাছাই: ঋতু, উৎসব, মা-বাবা, প্রেম, ছেলেবেলা।</p>
        @forelse ($collections as $c)
            @if ($loop->first)<ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@endif
                <li><x-collection-card :collection="$c" /></li>
            @if ($loop->last)</ul>@endif
        @empty
            <p class="mt-8 rounded-2xl border-2 border-dashed border-navy p-6 font-serif text-lg">প্রথম সংকলন তৈরি হচ্ছে। শিগগির এখানে পড়তে পাবেন।</p>
        @endforelse
    </section>
</x-layouts.app>
