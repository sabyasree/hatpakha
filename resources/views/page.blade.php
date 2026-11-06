<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <article class="mx-auto max-w-[760px] px-5 pb-14 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2">
            <a href="{{ $crumbs[0][1] }}" class="hover:text-red">{{ $crumbs[0][0] }}</a> ›
        </nav>
        <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight md:text-5xl" style="font-stretch: 85%">{{ $page->title }}</h1>
        {{-- body_html is sanitised on save (App\Support\HtmlSanitizer); Embeds only adds validated YouTube players. --}}
        <div class="prose-bn mt-6">{!! \App\Support\Images::renderBody(\App\Support\Embeds::render($page->body_html ?? '')) !!}</div>
    </article>
    @if ($page->slug === 'submissions')
        @include('partials.chat')
    @endif
</x-layouts.app>
