{{-- A2 (research 2026-11): every writer with a published piece, অ to হ, with a letter bar and a search box. --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $page = $paginator->currentPage();
    $prevLetter = null;
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1120px] px-5 pb-16 pt-6">
        <nav aria-label="অবস্থান" class="flex items-center text-base text-ink2"><a href="{{ url('/') }}/" class="inline-flex min-h-11 items-center hover:text-red">হাতপাখা</a><span class="px-1" aria-hidden="true">›</span><a href="{{ Url::to('/archive') }}" class="inline-flex min-h-11 items-center hover:text-red">আর্কাইভ</a></nav>
        <h1 class="font-display text-5xl font-extrabold md:text-6xl" style="font-stretch: 80%">লেখকেরা</h1>
        <p class="mt-2 font-serif text-lg text-ink2">২০১২ থেকে হাতপাখায় লিখেছেন {{ Bn::num((string) $count) }} জন। নামের প্রথম অক্ষর ছুঁয়ে বা নাম লিখে খুঁজুন।</p>

        <form action="{{ Url::to('/lekhok') }}" method="get" role="search" class="mt-5 flex w-full gap-2 md:w-[30rem]">
            <label for="writer-q" class="sr-only">লেখকের নাম</label>
            <input id="writer-q" type="search" name="q" value="{{ $q }}" maxlength="60" placeholder="লেখকের নাম লিখুন" class="h-12 min-w-0 flex-1 rounded-full border-2 border-navy bg-white px-5 text-lg">
            <button type="submit" class="h-12 flex-none rounded-full bg-navy px-6 font-bold text-white">খুঁজুন</button>
        </form>

        @if ($letters)
            <nav data-letter-bar aria-label="নামের প্রথম অক্ষর" class="sticky top-0 z-10 -mx-5 mt-5 border-y-2 border-navy bg-paper px-5 py-2">
                <ul class="flex gap-1 overflow-x-auto pb-1">
                    @foreach ($letters as $letter => $onPage)
                        <li class="flex-none"><a href="{{ Url::to('/lekhok') }}{{ $onPage > 1 ? '?page='.$onPage : '' }}#harf-{{ $loop->index }}"
                            class="flex h-11 min-w-11 items-center justify-center rounded-full px-2 text-lg font-bold hover:bg-peach-light">{{ $letter }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($q !== '')
            <p class="mt-6 font-bold">"{{ $q }}": {{ Bn::num((string) $paginator->total()) }} জন লেখক <a href="{{ Url::to('/lekhok') }}" class="ml-2 inline-flex min-h-11 items-center text-sky-night underline">সব লেখক দেখুন</a></p>
        @endif

        @if ($writers->isEmpty())
            <p class="mt-6 font-serif text-lg">এই নামে কোনো লেখক পাওয়া গেল না। <a href="{{ Url::to('/search') }}?q={{ rawurlencode($q) }}" class="font-bold text-sky-night underline">সব লেখায় খুঁজে দেখুন</a></p>
        @else
            <ul class="mt-6 grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($writers as $w)
                    @if ($w['letter'] !== $prevLetter)
                        <li class="col-span-full mt-6 scroll-mt-20 border-b-2 border-navy pb-1" @if ($letters) id="harf-{{ array_search($w['letter'], array_keys($letters), true) }}" @endif><h2 class="font-display text-3xl font-extrabold text-red">{{ $w['letter'] }}</h2></li>
                        @php $prevLetter = $w['letter']; @endphp
                    @endif
                    <li class="border-b border-rule">
                        <a href="{{ $w['author']->url() }}" class="flex min-h-16 items-center gap-3 py-2 hover:bg-peach-light/60">
                            @if ($w['author']->avatar_path)
                                <img src="{{ $w['author']->avatar_path }}" alt="" loading="lazy" width="48" height="48" class="h-12 w-12 flex-none rounded-full border-2 border-navy object-cover">
                            @else
                                <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full border-2 border-navy bg-peach-light text-xl font-extrabold text-red" aria-hidden="true">{{ mb_substr($w['name'], 0, 1) }}</span>
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-lg font-bold">{{ $w['name'] }}</span>
                                <span class="block text-base text-ink2">{{ Bn::num((string) $w['n']) }}টি লেখা</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="mt-10">{{ $paginator->links('pagination.hatpakha') }}</div>
    </section>
</x-layouts.app>
