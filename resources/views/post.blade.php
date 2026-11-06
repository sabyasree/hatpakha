<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <article class="mx-auto max-w-[760px] px-5 pb-10 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2">
            @foreach ($crumbs as $c)
                @if (! $loop->last)<a href="{{ $c[1] }}" class="hover:text-red">{{ $c[0] }}</a> › @endif
            @endforeach
        </nav>
        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm font-semibold">
            @if ($post->section)<a href="{{ $post->section->url() }}" class="rounded-full bg-red px-3 py-1 font-bold text-white">{{ $post->section->name_bn }}</a>@endif
            <span class="text-ink2">{{ \App\Support\Bn::num($post->reading_minutes) }} মিনিট পড়া</span>
        </div>
        <h1 class="mt-4 font-display text-5xl font-extrabold leading-[1.05] md:text-6xl" style="font-stretch: 80%">{{ $post->title }}</h1>
        <div class="mt-5 flex items-center gap-3">
            @foreach ($post->authors->take(1) as $a)
                <a href="{{ $a->url() }}" class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-gradient-to-br from-red to-red-dark text-xl font-extrabold text-white" aria-hidden="true">{{ $a->initial() }}</a>
            @endforeach
            <div>
                <div class="text-lg"><x-byline :post="$post" /></div>
                <div class="text-sm text-ink2">{{ \App\Support\Bn::date($post->published_at) }}@if ($post->issue) · <a href="{{ $post->issue->url() }}" class="underline">{{ $post->issue->title }}</a>@endif</div>
            </div>
        </div>

        @if ($post->featured_image_path && ($post->featured_image_width ?? 0) >= 1000)
            <picture>
                @if ($set = \App\Support\Images::srcset('/'.$post->featured_image_path))<source type="image/webp" srcset="{{ $set }}" sizes="(max-width: 760px) 100vw, 720px">@endif
                <img src="{{ asset($post->featured_image_path) }}" alt="" width="{{ $post->featured_image_width }}" height="{{ $post->featured_image_height }}" fetchpriority="high" class="mt-7 w-full rounded-2xl">
            </picture>
        @elseif (! $post->isPoem())
            <x-title-card :post="$post" class="mt-7 !aspect-[16/7]" />
        @endif

        {{-- body_html is sanitised on save (App\Support\HtmlSanitizer) — the only raw output allowed. --}}
        <div class="{{ $post->isPoem() ? 'poem-bn mt-8 text-center' : 'prose-bn mt-6' }}" lang="{{ $post->language }}">{!! \App\Support\Images::renderBody(\App\Support\Embeds::render($post->body_html)) !!}</div>

        <div class="my-10 text-center font-hand text-3xl text-red">সমাপ্ত</div>

        <div class="flex flex-wrap justify-center gap-3 border-y border-rule py-4">
            <a href="https://wa.me/?text={{ rawurlencode($post->title.' — '.$post->url()) }}" rel="noopener" target="_blank" class="flex h-12 items-center rounded-full bg-red px-5 font-bold text-white">WhatsApp-এ পাঠান</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($post->url()) }}" rel="noopener" target="_blank" class="flex h-12 items-center rounded-full border-2 border-navy px-5 font-bold">Facebook</a>
        </div>

        <x-ad-slot :slot="config('services.adsense.slot_article')" :height="250" />

        @foreach ($post->authors as $a)
            <div class="mt-8 flex items-center gap-4 rounded-3xl bg-peach-light p-5">
                <span class="flex h-16 w-16 flex-none items-center justify-center rounded-full bg-gradient-to-br from-red to-red-dark text-2xl font-extrabold text-white" aria-hidden="true">{{ $a->initial() }}</span>
                <div class="min-w-0 flex-1"><div class="text-lg font-extrabold">{{ $a->publicName() }}</div>@if ($a->is_guest && ! $a->is_desk)<div class="text-sm text-ink2">অতিথি লেখক</div>@endif</div>
                <a href="{{ $a->url() }}" class="flex h-11 items-center rounded-full border-2 border-navy px-4 font-bold">সব লেখা</a>
            </div>
        @endforeach

        @if ($comments->isNotEmpty())
            <section class="mt-10" aria-label="পাঠকের মতামত">
                <h2 class="font-display text-3xl font-extrabold">পাঠকের মতামত</h2>
                @foreach ($comments as $c)
                    <div class="border-b border-rule py-4"><div class="font-bold">{{ $c->author_name }}</div><p class="mt-1 whitespace-pre-line">{{ $c->body }}</p></div>
                @endforeach
            </section>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-[1100px] px-5">
            <h2 class="font-display text-4xl font-extrabold">আরও {{ $post->section?->name_bn }}</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                @foreach ($related as $r)
                    <a href="{{ $r->url() }}" class="block"><x-title-card :post="$r" />@if ($r->hasCardImage())<span class="mt-2 block font-serif text-base font-bold leading-snug sm:text-lg">{{ $r->title }}</span>@endif<span class="mt-1 block text-sm font-semibold text-ink2">{{ $r->authors->first()?->publicName() }}</span></a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
