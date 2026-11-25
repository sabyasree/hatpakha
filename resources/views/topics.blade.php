<x-layouts.app :seo="$seo">
    {{-- D293: every topic page with enough pieces, by group --}}
    <section class="mx-auto max-w-[1000px] px-5 py-12">
        <div class="font-hand text-2xl text-red">বিষয়</div>
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">বিষয় ধরে পড়ুন</h1>
        <p class="mt-4 max-w-[720px] font-serif text-lg leading-relaxed text-ink2">ভূতের গল্প খুঁজছেন, না প্রেমের কবিতা? হাতপাখার পুরনো আর নতুন লেখা, বিষয় ধরে সাজানো।</p>
        @if ($live === [])
            <p class="mt-8 font-serif text-lg">বিষয়ের পাতা তৈরি হচ্ছে। ততক্ষণ <a href="{{ \App\Support\Url::to('/archive') }}" class="font-bold text-sky-night underline">আর্কাইভ</a> থেকে পড়ুন।</p>
        @endif
        @foreach (\App\Support\Topics::GROUPS as $group => $label)
            @php($inGroup = collect($live)->filter(fn ($n, $slug) => \App\Support\Topics::LIST[$slug][1] === $group))
            @if ($inGroup->isNotEmpty())
                <h2 class="mt-10 font-display text-3xl font-extrabold">{{ $label }}</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($inGroup as $slug => $n)
                        <li><a href="{{ \App\Support\Topics::url($slug) }}" class="block rounded-2xl border-2 border-navy bg-paper p-4 hover:bg-peach-light">
                            <span class="block font-display text-xl font-extrabold">{{ \App\Support\Topics::name($slug) }}</span>
                            <span class="mt-1 block font-serif text-base text-ink2">{{ \App\Support\Topics::LIST[$slug][3] }}</span>
                            <span class="mt-2 block text-sm font-bold text-sky-night">{{ \App\Support\Bn::num((string) $n) }}টি লেখা →</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </section>
</x-layouts.app>
