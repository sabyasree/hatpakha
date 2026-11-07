@props(['seo'])
<!doctype html>
<html lang="{{ $seo->lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFBF4">
    <x-seo :seo="$seo" />
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    {{-- Fonts are self-hosted (public/fonts/fonts.css, shared with the admin panel); preload the two Bengali faces used above the fold. --}}
    <link rel="preload" href="{{ asset('fonts/anek-bangla-bengali.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/noto-serif-bengali-bengali.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (config('services.adsense.client'))
        <script async nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('services.adsense.client') }}" crossorigin="anonymous"></script>
    @endif
</head>
<body class="min-h-screen bg-paper text-navy antialiased {{ request()->is('sonkha/*/boi*') ? '' : 'pb-[calc(5.5rem+env(safe-area-inset-bottom))]' }} md:pb-0">
    @if ($va = request()->attributes->get('view_as'))
        {{-- D85: the admin is looking through a writer's eyes --}}
        <div data-view-as class="sticky top-0 z-[60] flex flex-wrap items-center gap-x-3 gap-y-1 bg-red px-4 pb-2 pt-[max(0.5rem,env(safe-area-inset-top))] text-sm font-bold text-white" role="status">
            <span class="min-w-0 flex-1 leading-snug"><span class="block truncate">{{ $va['name'] }}-এর চোখে দেখছেন</span><span class="block text-xs font-semibold">শুধু দেখা, কিছু বদলাবে না · ৩০ মিনিটে নিজে বন্ধ হবে</span></span>
            <form method="POST" action="{{ route('view-as.stop') }}">@csrf<button class="min-h-11 rounded-full bg-white px-4 text-red">বন্ধ করুন</button></form>
        </div>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            // Look only: every save/send button is shown as off, so nothing invites a press that would be refused.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('form[method="POST"] button, form[method="post"] button').forEach(function (b) {
                    if (b.closest('[data-view-as]')) return;
                    b.disabled = true; b.setAttribute('aria-disabled', 'true'); b.title = 'শুধু দেখা'; b.classList.add('opacity-50', 'cursor-not-allowed');
                });
            });
        </script>
    @endif
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:bg-paper focus:p-2">মূল লেখায় যান</a>

    <header class="border-b-2 border-navy bg-paper">
        <div class="mx-auto flex max-w-[1320px] items-center justify-between gap-5 px-5 py-3 max-[359px]:gap-2 max-[359px]:px-4">
            <a href="{{ url('/') }}/" class="flex flex-none flex-col items-end" aria-label="হাতপাখা — প্রথম পাতা">
                <img src="{{ asset('images/logo-wordmark.png') }}" alt="Hatpakha" width="367" height="90" class="h-9 w-auto max-[359px]:h-7 md:h-12">
                <span class="-mt-1 text-[11px] font-bold max-[359px]:text-[9px]">ISSN: 2321-3035</span>
            </a>
            @php $menu = \App\Models\Section::menu(); @endphp
            {{-- D95 (UX review): big screens show the chosen sections, "সব বিভাগ" for the rest (grouped), and English on its own --}}
            <nav aria-label="প্রধান বিভাগ" class="hidden min-w-0 items-center gap-1 text-lg font-semibold lg:flex">
                @foreach ($menu['top'] as [$name, $href])
                    <a href="{{ $href }}" class="whitespace-nowrap rounded-full px-3 py-2 hover:text-red" @if (url()->current().'/' === $href || url()->current() === rtrim($href, '/')) aria-current="page" @endif>{{ $name }}</a>
                @endforeach
                <details class="relative" data-menu>
                    <summary class="flex h-11 cursor-pointer list-none items-center gap-1 whitespace-nowrap rounded-full px-3 hover:text-red [&::-webkit-details-marker]:hidden">সব বিভাগ <span aria-hidden="true">▾</span></summary>
                    <div class="absolute right-0 top-12 z-50 w-[36rem] rounded-2xl border-2 border-navy bg-paper p-4 text-base shadow-[4px_4px_0_#13212C]">
                        <x-menu-panel :menu="$menu" :wide="true" />
                    </div>
                </details>
                @if ($menu['english'])
                    <a href="{{ $menu['english'] }}" lang="en" class="ml-1 rounded-full border-2 border-navy px-3 py-1 text-base font-bold hover:bg-sky-tint">English</a>
                @endif
            </nav>
            <div class="flex min-w-0 items-center gap-1 max-[359px]:gap-0 sm:gap-3">
                {{-- Phones and tablets: every section, grouped, with English and the archive --}}
                <details class="relative lg:hidden" data-menu>
                    <summary class="flex h-11 cursor-pointer list-none items-center gap-1 rounded-full px-3 font-bold max-[359px]:px-2 hover:bg-peach-light [&::-webkit-details-marker]:hidden">বিভাগ <span aria-hidden="true">▾</span></summary>
                    <div class="fixed inset-x-3 top-[4.75rem] z-50 max-h-[calc(100dvh-10.5rem)] overflow-y-auto rounded-2xl border-2 border-navy bg-paper p-3 shadow-[4px_4px_0_#13212C] sm:absolute sm:inset-x-auto sm:right-0 sm:top-12 sm:w-[22rem]">
                        <x-menu-panel :menu="$menu" />
                    </div>
                </details>
                <a href="{{ \App\Support\Url::to('/search') }}" class="flex h-11 w-11 flex-none items-center justify-center rounded-full hover:bg-peach-light max-[359px]:w-10" aria-label="খুঁজুন" title="খুঁজুন">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.5-4.5"/></svg>
                </a>
                @auth
                    <a href="{{ \App\Support\Url::to('/account') }}" class="flex h-11 min-w-11 flex-none items-center justify-center font-bold hover:text-red" aria-label="আমার লেখা">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-navy bg-peach-light sm:hidden" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        <span class="hidden sm:inline">আমার লেখা</span>
                    </a>
                @else
                    <a href="{{ \App\Support\Url::to('/login') }}" class="flex h-11 flex-none items-center whitespace-nowrap px-1 font-bold hover:text-red">লগ-ইন</a>
                @endauth
                <a href="{{ \App\Support\Url::to('/submit') }}" class="hidden h-11 items-center rounded-full border-2 border-navy bg-red px-5 font-bold text-white shadow-[4px_4px_0_#13212C] md:flex">লেখা পাঠান</a>
            </div>
        </div>
    </header>

    <main id="main">{{ $slot }}</main>

    <footer class="mt-16 bg-navy text-paper">
        <div class="mx-auto flex max-w-[1320px] flex-wrap items-end justify-between gap-8 px-5 py-12">
            <div>
                <div class="font-hand text-6xl leading-none text-sky md:text-8xl">আবার আসবেন</div>
                <p class="mt-3 text-sm text-peach-light">ISSN 2321-3035 · ২০১২ থেকে · লেখা প্রকাশ সবসময় বিনামূল্যে</p>
            </div>
            @if (! auth()->check() && ! request()->attributes->get('hp.newsletter.card'))<x-newsletter-signup variant="footer" />@endif
            <nav aria-label="ফুটার" class="grid w-full grid-cols-2 gap-x-6 text-base sm:w-auto sm:grid-cols-3">
                @foreach (\App\Models\Page::footerLinks() as [$t, $u])
                    <a href="{{ $u }}" class="flex min-h-11 items-center hover:text-sky">{{ $t }}</a>
                @endforeach
                <a href="{{ \App\Support\Url::to('/archive') }}" class="flex min-h-11 items-center hover:text-sky">আর্কাইভ</a>
                <a href="{{ \App\Support\Url::to('/feed') }}" class="flex min-h-11 items-center hover:text-sky">RSS</a>
            </nav>
        </div>
    </footer>

    {{-- Mobile bottom tab bar (mobile UX review): icons, the current tab in red, "লিখুন" as a raised sticker, safe-area padding. --}}
    @php
        $tabs = [
            ['হোম', url('/').'/', request()->is('/'), 'home'],
            ['কবিতা', \App\Support\Url::to('/category/poetry'), request()->is('category/poetry*'), 'poem'],
            ['লিখুন', \App\Support\Url::to('/submit'), request()->is('submit*', 'submissions*'), 'write'],
            ['গল্প', \App\Support\Url::to('/category/short-stories'), request()->is('category/short-stories*'), 'story'],
            ['আর্কাইভ', \App\Support\Url::to('/archive'), request()->is('archive*'), 'archive'],
        ];
        $icons = [
            'home' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/>',
            'poem' => '<path d="M20 4c-7 1-12 6-13 13l-3 3"/><path d="M7 17c4 0 8-3 9-8"/>',
            'write' => '<path d="M12 5v14M5 12h14"/>',
            'story' => '<path d="M4 5h6a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H4z"/><path d="M20 5h-6a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h6z"/>',
            'archive' => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10h14V9M10 13h4"/>',
        ];
    @endphp
    @unless (request()->is('sonkha/*/boi*') || request()->attributes->get('hp.no-bottom-nav'))
    <nav aria-label="প্রধান" class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t-2 border-navy bg-paper pb-[env(safe-area-inset-bottom)] text-[13px] font-bold md:hidden">
        @foreach ($tabs as [$label, $href, $on, $ic])
            @if ($ic === 'write')
                <a href="{{ $href }}" class="flex h-16 flex-col items-center justify-center" @if ($on) aria-current="page" @endif>
                    <span class="-mt-5 flex h-11 w-11 items-center justify-center rounded-full border-2 border-navy bg-red text-white shadow-[2px_2px_0_#13212C]"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true">{!! $icons[$ic] !!}</svg></span>
                    <span class="mt-0.5 text-red">{{ $label }}</span>
                </a>
            @else
                <a href="{{ $href }}" class="flex h-16 flex-col items-center justify-center gap-0.5 {{ $on ? 'text-red' : '' }}" @if ($on) aria-current="page" @endif>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$ic] !!}</svg>
                    <span>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </nav>
    @endunless
</body>
</html>
