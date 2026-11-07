<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1320px] px-5 py-12">
        @if ($kicker)<div class="font-hand text-2xl text-red">{{ $kicker }}</div>@endif
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">{{ $title }}</h1>
        @if (($section ?? null)?->about)<p class="mt-4 max-w-[720px] font-serif text-lg leading-relaxed text-ink2">{{ $section->about }}</p>@endif
        @if (($section ?? null) && $posts->total() === 0)
            {{-- D96: a new section waiting for its first piece --}}
            <div class="mt-8 max-w-[720px] rounded-3xl border-2 border-dashed border-navy p-6 sm:p-8">
                <p class="font-hand text-3xl text-red">নতুন বিভাগ</p>
                <p class="mt-2 font-serif text-xl">এই বিভাগে প্রথম লেখাটি আপনার হোক।</p>
                <a href="{{ \App\Support\Url::to('/submit') }}?section={{ $section->slug }}" class="mt-5 inline-flex h-12 items-center rounded-full bg-red px-6 font-bold text-white shadow-[3px_3px_0_#13212C]">এই বিভাগে লেখা পাঠান</a>
            </div>
        @endif
        {{-- Phones: one readable list (mobile UX review); wider screens: the card grid. --}}
        <ul class="mt-6 divide-y divide-rule border-y border-rule sm:hidden">
            @foreach ($posts as $p)
                <li><x-post-row :post="$p" /></li>
            @endforeach
        </ul>
        <div class="mt-8 hidden gap-6 sm:grid sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($posts as $p)
                <a href="{{ $p->url() }}" class="block"><x-title-card :post="$p" />@if ($p->hasCardImage())<span class="mt-2 block font-serif text-base font-bold leading-snug sm:text-lg">{{ $p->title }}</span>@endif<span class="mt-1 block text-sm font-semibold text-ink2">{{ $p->authors->first()?->publicName() }} · {{ \App\Support\Bn::date($p->published_at) }}</span></a>
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links('pagination.hatpakha') }}</div>
    </section>
</x-layouts.app>
