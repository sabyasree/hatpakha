{{-- লেখার ডাক (owner, 2026-11-24; research §B4): what to write for now. Only real data: coming issues the desk has
     planned or approved (a last date only where one was set), this week's পাঠকের কলম, open contests, and the editor's
     wish list (written in /desk; hidden when empty). --}}
@php
    use App\Support\Bn;
    use App\Support\Url;
    $box = 'rounded-2xl border-2 border-navy bg-white p-5';
@endphp
<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[760px] px-5 pb-16 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2"><a href="{{ Url::to('/') }}" class="inline-flex min-h-11 items-center hover:text-red">← প্রথম পাতা</a></nav>
        <div class="font-hand text-2xl text-red">লেখকদের জন্য</div>
        <h1 class="font-display text-5xl font-extrabold leading-tight" style="font-stretch: 80%">লেখার ডাক</h1>
        <p class="mt-3 font-serif text-lg leading-relaxed">এখন হাতপাখা কী লেখা খুঁজছে, এক পাতায়। যে কোনও বিষয়ে লেখা সব সময়ই পাঠানো যায়; নিচের ডাকগুলো তার বাইরে।</p>

        @if ($wishlist)
            <section class="mt-8 rounded-2xl border-2 border-red bg-peach-light p-5" aria-labelledby="wish-h" data-wishlist>
                <h2 id="wish-h" class="font-display text-2xl font-extrabold">সম্পাদক যা খুঁজছেন</h2>
                <ul class="mt-3 list-disc space-y-1 ps-6 font-serif text-lg">
                    @foreach ($wishlist as $w)<li>{{ $w }}</li>@endforeach
                </ul>
            </section>
        @endif

        @if ($issues->isNotEmpty())
            <h2 class="mt-10 font-display text-3xl font-extrabold">সামনের সংখ্যা</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($issues as $row)
                    <li class="{{ $box }}" data-issue="{{ $row['issue']->slug }}">
                        <p class="font-display text-2xl font-extrabold leading-snug">{{ $row['issue']->title }}</p>
                        @if ($row['theme'])<p class="mt-1 font-serif text-lg">বিষয়: {{ $row['theme'] }}</p>@endif
                        <p class="mt-2 flex flex-wrap gap-2 text-sm font-bold">
                            @if ($row['last'])<span class="rounded-full bg-navy px-3 py-1 text-white">লেখা পাঠানোর শেষ দিন: {{ Bn::date($row['last']) }}</span>@endif
                            @if ($row['at'])<span class="rounded-full bg-sky-tint px-3 py-1 text-sky-night">প্রকাশ: {{ Bn::date($row['at']) }}</span>@endif
                        </p>
                        @if ($row['last'])
                            <p class="mt-2 text-sm text-ink2">লেখা পাঠানোর ফর্মে “এই লেখাটি “{{ $row['issue']->title }}”-র জন্য” টিক দিন।</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($kolom)
            <h2 class="mt-10 font-display text-3xl font-extrabold">এ সপ্তাহের পাঠকের কলম</h2>
            <a href="{{ Url::to('/kolom') }}" class="mt-4 block {{ $box }} hover:bg-peach-light" data-kolom>
                <span class="block font-serif text-2xl font-extrabold">{{ $kolom->title }}</span>
                <span class="mt-1 block font-serif text-lg">{{ $kolom->ask }}</span>
                @if ($kolom->closes_at)<span class="mt-2 inline-block rounded-full bg-navy px-3 py-1 text-sm font-bold text-white">জমার শেষ সময়: {{ Bn::date($kolom->closes_at) }}, সন্ধ্যা ৬টা</span>@endif
                <span class="mt-2 block font-bold text-sky-night underline">পাঠকের কলমে লিখুন</span>
            </a>
        @endif

        @if ($contests->isNotEmpty())
            <h2 class="mt-10 font-display text-3xl font-extrabold">চলতি প্রতিযোগিতা</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($contests as $c)
                    <li class="{{ $box }}" data-contest>
                        <p class="font-display text-2xl font-extrabold leading-snug">{{ $c->title }}</p>
                        @if ($c->theme)<p class="mt-1 font-serif text-lg">বিষয়: {{ $c->theme }}</p>@endif
                        <p class="mt-2 flex flex-wrap gap-2 text-sm font-bold">
                            @if ($c->section)<span class="rounded-full bg-sky-tint px-3 py-1 text-sky-night">{{ $c->section->name_bn }}</span>@endif
                            @if ($c->ends_at)<span class="rounded-full bg-navy px-3 py-1 text-white">শেষ দিন: {{ Bn::date($c->ends_at) }}</span>@endif
                        </p>
                        @if ($c->rules)<p class="mt-2 whitespace-pre-line text-ink2">{{ $c->rules }}</p>@endif
                        <p class="mt-2 text-sm text-ink2">লেখা পাঠানোর ফর্মে “প্রতিযোগিতায় পাঠাবেন?” খুলে এটি বেছে নিন।</p>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-10 rounded-2xl border-2 border-navy bg-sky-tint p-5">
            <p class="text-lg font-bold">উত্তর {{ \App\Support\Review::REPLY_TEXT }}</p>
            @if ($replyLine)<p class="mt-1" data-reply-time>{{ $replyLine }}।</p>@endif
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <a href="{{ Url::to('/submit') }}" class="flex h-14 items-center justify-center rounded-full bg-red px-8 text-lg font-bold text-white shadow-[3px_3px_0_#13212C]">লেখা পাঠান</a>
                <a href="{{ Url::to('/submissions') }}" class="flex h-14 items-center justify-center rounded-full border-2 border-navy bg-white px-8 text-lg font-bold">লেখা পাঠানোর নিয়ম</a>
            </div>
        </div>
    </section>
</x-layouts.app>
