<x-layouts.app :seo="$seo">
    <section class="mx-auto max-w-[1320px] px-5 py-12">
        @if ($kicker)<div class="font-hand text-2xl text-red">{{ $kicker }}</div>@endif
        <h1 class="font-display text-6xl font-extrabold leading-none" style="font-stretch: 78%">{{ $title }}</h1>
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($posts as $p)
                <a href="{{ $p->url() }}" class="block"><x-title-card :post="$p" /><span class="mt-2 block text-sm font-semibold text-ink2">{{ $p->authors->first()?->publicName() }} · {{ \App\Support\Bn::date($p->published_at) }}</span></a>
            @endforeach
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    </section>
</x-layouts.app>
