<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1320px] px-5 py-12">
        @if ($kicker)<div class="font-hand text-2xl text-red">{{ $kicker }}</div>@endif
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">{{ $title }}</h1>
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
