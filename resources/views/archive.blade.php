<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1320px] px-5 py-12">
        @if ($kicker)<div class="font-hand text-2xl text-red">{{ $kicker }}</div>@endif
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">{{ $title }}</h1>
        {{-- Phones: one readable list (mobile UX review); wider screens: the card grid. --}}
        <ul class="mt-6 divide-y divide-rule border-y border-rule sm:hidden">
            @foreach ($posts as $p)
                <li>
                    <a href="{{ $p->url() }}" class="flex items-center gap-4 py-3">
                        @if ($p->hasCardImage())
                            <img src="{{ asset($p->featured_image_path) }}" alt="" loading="lazy" width="88" height="88" class="h-[88px] w-[88px] flex-none rounded-xl border-2 border-navy object-cover">
                        @else
                            <span class="flex h-[88px] w-[88px] flex-none items-center justify-center rounded-xl border-2 border-navy bg-peach-light font-display text-3xl font-extrabold text-red" aria-hidden="true">{{ mb_substr($p->title, 0, 1) }}</span>
                        @endif
                        <span class="min-w-0">
                            <span class="block font-serif text-[19px] font-bold leading-snug">{{ $p->title }}</span>
                            <span class="mt-1 block text-sm text-ink2">{{ $p->authors->first()?->publicName() }} · {{ \App\Support\Bn::num((string) $p->published_at?->year) }}</span>
                        </span>
                    </a>
                </li>
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
