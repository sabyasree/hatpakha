{{-- D97: a themed collection (সংকলন). Title and intro are plain text, escaped. --}}
<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <article class="mx-auto max-w-[820px] px-5 pb-16 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2">
            <a href="{{ \App\Support\Url::to('/sonkolon') }}" class="inline-flex min-h-11 items-center hover:text-red">← সব সংকলন</a>
        </nav>
        @if ($preview)
            <p class="mt-3 rounded-xl border-2 border-red bg-peach-light p-3 font-bold" role="status">প্রস্তাব: এখনও প্রকাশিত নয়। শুধু সম্পাদকেরা দেখতে পাচ্ছেন।</p>
        @endif
        <div class="mt-4 font-hand text-2xl text-red">সংকলন</div>
        <h1 class="font-display text-5xl font-extrabold leading-tight md:text-6xl" style="font-stretch: 85%">{{ $c->title }}</h1>
        @if ($c->intro)<p class="mt-5 font-serif text-lg leading-relaxed md:text-xl">{{ $c->intro }}</p>@endif
        <p class="mt-4 text-sm font-bold text-ink2">{{ \App\Support\Bn::num((string) $posts->count()) }}টি লেখা · হাতপাখার আর্কাইভ থেকে বাছাই</p>
        @if ($posts->isNotEmpty())
            <div class="mt-5 flex flex-wrap gap-3">
                {{-- বর্ষসেরা and every collection read as a magazine too (owner, 2026-11-12) --}}
                @if (! $preview && $c->slug)
                    <a href="{{ \App\Support\Url::to('/sonkolon/'.$c->slug.'/boi') }}" class="inline-flex h-12 items-center rounded-full bg-red px-6 font-bold text-white">পত্রিকা খুলুন</a>
                @endif
                <a href="{{ $posts->first()->url() }}" class="inline-flex h-12 items-center rounded-full border-2 border-navy px-6 font-bold">প্রথম লেখা থেকে পড়ুন →</a>
            </div>
        @endif

        <ol class="mt-8 divide-y divide-rule border-y-2 border-navy">
            @foreach ($posts as $i => $p)
                <li class="flex gap-3">
                    <span class="w-8 flex-none pt-4 text-right font-display text-xl font-extrabold text-red" aria-hidden="true">{{ \App\Support\Bn::num((string) ($i + 1)) }}</span>
                    <div class="min-w-0 flex-1"><x-post-row :post="$p" :tile="false" :meta="($p->authors->first()?->publicName() ?? '').($p->section ? ' · '.$p->section->name_bn : '')" /></div>
                </li>
            @endforeach
        </ol>
        <x-ad-slot spot="list" :height="250" />

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="https://wa.me/?text={{ rawurlencode($c->title.' — হাতপাখার সংকলন '.$c->url()) }}" target="_blank" rel="noopener" class="inline-flex h-12 items-center rounded-full bg-navy px-6 font-bold text-white">হোয়াটসঅ্যাপে পাঠান</a>
            <a href="{{ \App\Support\Url::to('/submit') }}" class="inline-flex h-12 items-center rounded-full border-2 border-navy px-6 font-bold">এমন লেখা আপনারও আছে? পাঠান</a>
        </div>

        @if ($others->isNotEmpty())
            <section class="mt-14" aria-labelledby="more-h">
                <h2 id="more-h" class="font-display text-3xl font-extrabold">আরও সংকলন</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($others as $o)
                        <li><a href="{{ $o->url() }}" class="flex min-h-14 items-center justify-between gap-3 rounded-2xl border-2 border-navy bg-white px-4 py-3 font-bold hover:bg-peach-light">{{ $o->title }} <span aria-hidden="true">→</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-layouts.app>
