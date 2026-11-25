<x-layouts.app :seo="$seo">
    <div class="h-1 bg-gradient-to-r from-pink via-red to-peach" aria-hidden="true"></div>
    <x-desk-edit :url="\App\Filament\Resources\Pages\PageResource::getUrl('edit', ['record' => $page], panel: 'desk')" />
    <article class="mx-auto max-w-[760px] px-5 pb-14 pt-8">
        <nav aria-label="অবস্থান" class="text-sm text-ink2">
            <a href="{{ $crumbs[0][1] }}" class="inline-flex min-h-11 items-center hover:text-red">← প্রথম পাতা</a>
        </nav>
        <h1 class="mt-4 font-display text-4xl font-extrabold leading-tight md:text-5xl" style="font-stretch: 85%">{{ $page->title }}</h1>
        @includeWhen($page->slug === 'submissions', 'partials.submit-howto')
        {{-- body_html is sanitised on save (App\Support\HtmlSanitizer); Embeds only adds validated YouTube players. --}}
        <div class="prose-bn mt-6">{!! \App\Support\Images::renderBody(\App\Support\Embeds::render($page->body_html ?? '')) !!}</div>
        @if (in_array($page->slug, ['contact-us', 'advertise'], true))
            {{-- D89: a form instead of an e-mail address --}}
            @include('partials.contact-form', ['kind' => $page->slug === 'advertise' ? 'advertise' : 'contact'])
        @endif
        @if ($page->slug === 'bornomala')
            @include('partials.bornomala')
        @endif
        @if ($page->slug === 'submissions')
            {{-- Owner, 2026-11-24: the reply promise, proved by real decisions (shown from five decisions in 90 days) --}}
            <div class="mt-8 rounded-2xl border-2 border-navy bg-sky-tint p-4 text-lg" data-reply-promise>
                <p class="font-bold">উত্তর {{ \App\Support\Review::REPLY_TEXT }}</p>
                @if ($replyLine = \App\Support\WriterJourney::replyLine())<p class="mt-1" data-reply-time>{{ $replyLine }}।</p>@endif
            </div>
            <a href="{{ \App\Support\Url::to('/lekhar-dak') }}" class="mt-4 flex min-h-14 items-center justify-between gap-3 rounded-2xl border-2 border-navy bg-white px-4 py-2 font-bold hover:bg-peach-light">
                <span>লেখার ডাক<span class="block text-sm font-medium text-ink2">সামনের সংখ্যা, এ সপ্তাহের পাঠকের কলম, প্রতিযোগিতা আর সম্পাদক যা খুঁজছেন</span></span><span aria-hidden="true">›</span></a>
            {{-- After the rules, the next step (mobile UX review) --}}
            <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ \App\Support\Url::to('/submit') }}" class="flex h-14 w-full items-center justify-center rounded-full bg-red text-lg font-bold text-white shadow-[4px_4px_0_#13212C] sm:w-fit sm:px-10">লেখা পাঠান</a>
                @include('partials.chat-button', ['class' => 'justify-center sm:justify-start'])
            </div>
        @endif
    </article>
    @if ($page->slug === 'submissions')
        @include('partials.chat')
    @endif
</x-layouts.app>
