<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <x-desk-edit :url="\App\Filament\Resources\Pages\PageResource::getUrl('edit', ['record' => $page], panel: 'desk')" />
    <article class="mx-auto max-w-[760px] px-5 pb-14 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2">
            <a href="{{ $crumbs[0][1] }}" class="inline-flex min-h-11 items-center hover:text-red">← প্রথম পাতা</a>
        </nav>
        <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight md:text-5xl" style="font-stretch: 85%">{{ $page->title }}</h1>
        {{-- body_html is sanitised on save (App\Support\HtmlSanitizer); Embeds only adds validated YouTube players. --}}
        <div class="prose-bn mt-6">{!! \App\Support\Images::renderBody(\App\Support\Embeds::render($page->body_html ?? '')) !!}</div>
        @if ($page->slug === 'contact-us')
            <a href="mailto:editor@hatpakha.com" class="mt-10 flex h-14 w-full items-center justify-center rounded-full bg-navy text-lg font-bold text-white shadow-[4px_4px_0_#CB1B32] sm:w-fit sm:px-10">ই-মেল লিখুন</a>
        @endif
        @if ($page->slug === 'submissions')
            {{-- After the rules, the next step (mobile UX review) --}}
            <a href="{{ \App\Support\Url::to('/submit') }}" class="mt-10 flex h-14 w-full items-center justify-center rounded-full bg-red text-lg font-bold text-white shadow-[4px_4px_0_#13212C] sm:w-fit sm:px-10">লেখা পাঠান</a>
        @endif
    </article>
    @if ($page->slug === 'submissions')
        @include('partials.chat')
    @endif
</x-layouts.app>
