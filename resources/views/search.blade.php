{{-- Site search (D64). --}}
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[900px] px-5 pb-16 pt-8">
        <h1 class="font-display text-4xl font-extrabold">খুঁজুন</h1>
        <form method="GET" action="{{ \App\Support\Url::to('/search') }}" role="search" class="mt-4 flex gap-2">
            <label for="q" class="sr-only">কী খুঁজছেন</label>
            <input id="q" name="q" type="search" value="{{ $q }}" maxlength="80" autofocus placeholder="লেখা, লেখক, বিষয় বা কবিতার একটি লাইন"
                class="h-12 min-w-0 flex-1 rounded-full border-2 border-navy bg-white px-5 text-lg">
            <button class="h-12 flex-none rounded-full bg-red px-6 font-bold text-white">খুঁজুন</button>
        </form>

        @if ($q !== '' && mb_strlen($q) < 2)
            <p class="mt-6 text-ink2">অন্তত দুটি অক্ষর লিখুন।</p>
        @elseif ($posts)
            @if ($authors->isNotEmpty())
                <h2 class="mt-8 text-sm font-extrabold uppercase tracking-wide text-ink2">লেখক</h2>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($authors as $a)
                        <li><a href="{{ $a->url() }}" class="flex h-11 items-center gap-2 rounded-full border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">
                            @if ($a->avatar_path)<img src="{{ $a->avatar_path }}" alt="" class="h-7 w-7 rounded-full object-cover">@endif
                            {{ $a->publicName() }} <span class="text-sm font-semibold text-ink2">{{ \App\Support\Bn::num((string) $a->published_count) }}</span></a></li>
                    @endforeach
                </ul>
            @endif
            <h2 class="mt-8 text-sm font-extrabold uppercase tracking-wide text-ink2">লেখা · {{ \App\Support\Bn::num((string) $posts->total()) }}টি</h2>
            {{-- UX review: say so when the spelling was forgiven, so readers trust the results --}}
            @if ($posts->total() > 0 && $authors->isEmpty() && $posts->getCollection()->every(fn ($p) => mb_stripos($p->title.' '.$p->search_snippet, $q) === false))
                <p class="mt-2 text-sm text-ink2">বানান একটু আলাদা হলেও কাছাকাছি বানানের লেখাগুলোও দেখানো হচ্ছে।</p>
            @endif
            @forelse ($posts as $p)
                <a href="{{ $p->url() }}" class="mt-3 flex gap-4 rounded-2xl border-2 border-rule bg-white p-3 hover:border-navy">
                    @if ($p->featured_image_path)
                        @php($v = \App\Support\Images::variants($p->featured_image_path))
                        <img src="{{ $v ? reset($v) : $p->featured_image_path }}" alt="" loading="lazy" width="112" height="80" class="h-20 w-28 flex-none rounded-xl object-cover">
                    @endif
                    <span class="min-w-0">
                        <span class="block font-serif text-lg font-bold leading-snug">{{ $p->title }}</span>
                        <span class="text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ $p->section?->name_bn }} · {{ \App\Support\Bn::date($p->published_at) }}</span>
                        @if ($p->search_snippet)<span class="mt-1 block border-l-2 border-red pl-2 font-serif text-ink2">“{{ $p->search_snippet }}”</span>@endif
                    </span>
                </a>
            @empty
                <div class="mt-4 rounded-2xl border-2 border-dashed border-navy p-6 text-center">
                    <p>"{{ $q }}" দিয়ে কিছু পাওয়া গেল না।</p>
                    @if ($suggest)
                        <p class="mt-3 text-lg">আপনি কি খুঁজছিলেন: <a href="{{ \App\Support\Url::to('/search') }}?q={{ rawurlencode($suggest) }}" class="font-bold text-sky-deep underline">{{ $suggest }}</a>?</p>
                    @else
                        <p class="mt-2 text-ink2">কম শব্দে খুঁজে দেখুন, যেমন শুধু লেখকের নাম বা কবিতার একটি লাইনের দুটি শব্দ।</p>
                    @endif
                </div>
            @endforelse
            @if ($posts->count() >= 5)<x-ad-slot spot="list" :height="250" />@endif
            <div class="mt-8">{{ $posts->links('pagination.hatpakha') }}</div>
        @else
            {{-- Not a dead end (mobile UX review): ideas to search, and every section. --}}
            <h2 class="mt-8 text-sm font-extrabold text-ink2">খুঁজে দেখুন</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach (['বর্ষা', 'পুজো', 'মা', 'বৃষ্টি', 'শীত', 'ভ্রমণ', 'প্রেম', 'রবীন্দ্রনাথ'] as $idea)
                    <li><a href="{{ \App\Support\Url::to('/search') }}?q={{ rawurlencode($idea) }}" class="flex h-11 items-center rounded-full border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">{{ $idea }}</a></li>
                @endforeach
            </ul>
            <h2 class="mt-8 text-sm font-extrabold text-ink2">বিভাগ</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach (\App\Models\Section::allLinks() as [$name, $href])
                    <li><a href="{{ $href }}" class="flex h-11 items-center rounded-full bg-peach-light px-4 font-bold hover:bg-peach">{{ $name }}</a></li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
