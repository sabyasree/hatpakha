@props(['seo'])
<!doctype html>
<html lang="{{ $seo->lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FFFBF4">
    <x-seo :seo="$seo" />
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    {{-- D127: installable as an app (home screen, full screen, offline reading of pieces already opened) --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="{{ asset('images/app/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-title" content="হাতপাখা">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @if ($pushKey = \App\Support\WebPush::publicKey())<meta name="hp-push-key" content="{{ $pushKey }}">@endif
    {{-- Fonts are self-hosted (public/fonts/fonts.css, shared with the admin panel); preload the two Bengali faces used above the fold. --}}
    <link rel="preload" href="{{ asset('fonts/anek-bangla-bengali.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/noto-serif-bengali-bengali.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($gaId = \App\Support\Analytics::id())
        {{-- D119: Google Analytics 4. Only the page's path (plus utm_ campaign labels) is sent, never the rest of the query: signed links stay private; no Google signals or ad
             personalisation from analytics; visitors in Europe are asked first (the consent message updates Google's consent mode). --}}
        <script async nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied', region: @json(\App\Support\Analytics::CONSENT_REGIONS)});
            gtag('js', new Date());
            const utm = new URLSearchParams([...new URLSearchParams(location.search)].filter(([k]) => k.startsWith('utm_'))).toString();
            gtag('config', @json($gaId), {page_location: location.origin + location.pathname + (utm ? '?' + utm : ''), page_referrer: document.referrer.split('?')[0], allow_google_signals: false, allow_ad_personalization_signals: false});
        </script>
    @endif
    @if (\App\Support\Ads::on())
        {{-- D111: only on page types that allow ads, on the live site --}}
        <script async nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ \App\Support\Ads::client() }}" crossorigin="anonymous"></script>
    @endif
</head>
<body class="min-h-screen bg-paper text-navy antialiased {{ request()->is('sonkha/*/boi*') ? '' : 'pb-[calc(5.5rem+env(safe-area-inset-bottom))]' }} md:pb-0">
    @if ($va = request()->attributes->get('view_as'))
        {{-- D85: the admin is looking through a writer's eyes --}}
        <div data-view-as class="sticky top-0 z-[60] flex flex-wrap items-center gap-x-3 gap-y-1 bg-red px-4 pb-2 pt-[max(0.5rem,env(safe-area-inset-top))] text-sm font-bold text-white" role="status">
            <span class="min-w-0 flex-1 leading-snug"><span class="block truncate">{{ __(':name-এর চোখে দেখছেন', ['name' => $va['name']]) }}</span><span class="block text-xs font-semibold">{{ __('শুধু দেখা, কিছু বদলাবে না · :min মিনিটে নিজে বন্ধ হবে', ['min' => \App\Support\Bn::num(30)]) }}</span></span>
            <form method="POST" action="{{ route('view-as.stop') }}">@csrf<button class="min-h-11 rounded-full bg-white px-4 text-red">{{ __('দেখা বন্ধ করুন') }}</button></form>
        </div>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            // Look only: every save/send button is shown as off, so nothing invites a press that would be refused.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('form[method="POST"] button, form[method="post"] button').forEach(function (b) {
                    if (b.closest('[data-view-as]')) return;
                    b.disabled = true; b.setAttribute('aria-disabled', 'true'); b.title = @json(__('শুধু দেখা')); b.classList.add('opacity-50', 'cursor-not-allowed');
                });
            });
        </script>
    @endif
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:bg-paper focus:p-2">{{ __('মূল লেখায় যান') }}</a>

    <header class="border-b-2 border-navy bg-paper">
        <div class="mx-auto flex max-w-[1320px] items-center justify-between gap-5 px-5 py-3 max-[359px]:gap-2 max-[359px]:px-4">
            <a href="{{ url('/') }}/" class="flex flex-none flex-col items-end" aria-label="{{ __('হাতপাখা — প্রথম পাতা') }}">
                <img src="{{ asset('images/logo-wordmark.png') }}" alt="Hatpakha" width="367" height="90" class="h-9 w-auto max-[359px]:h-7 md:h-12">
                <span class="-mt-1 text-[11px] font-bold max-[359px]:text-[9px]">ISSN: 2321-3035</span>
            </a>
            @php $menu = \App\Models\Section::menu(); @endphp
            {{-- D95 (UX review): big screens show the chosen sections and "সব বিভাগ" for every section, grouped --}}
            <nav aria-label="{{ __('প্রধান বিভাগ') }}" class="hidden min-w-0 items-center gap-1 text-lg font-semibold lg:flex">
                @foreach ($menu['top'] as [$name, $href, $lang])
                    <a href="{{ $href }}" @if ($lang === 'en') lang="en" @endif class="whitespace-nowrap rounded-full px-3 py-2 hover:text-red" @if (url()->current().'/' === $href || url()->current() === rtrim($href, '/')) aria-current="page" @endif>{{ $name }}</a>
                @endforeach
                {{-- Owner, 2026-11-14: games and পাঠকের কলম in the top bar on wide screens (always in "সব বিভাগ" too) --}}
                <a href="{{ \App\Support\Url::to('/khela') }}" class="hidden whitespace-nowrap rounded-full px-3 py-2 hover:text-red xl:inline-block" @if (request()->is('khela*')) aria-current="page" @endif>{{ __('খেলা') }}</a>
                <a href="{{ \App\Support\Url::to('/kolom') }}" class="hidden whitespace-nowrap rounded-full px-3 py-2 hover:text-red xl:inline-block" @if (request()->is('kolom*')) aria-current="page" @endif>{{ __('পাঠকের কলম') }}</a>
                <details class="relative" data-menu>
                    <summary class="flex h-11 cursor-pointer list-none items-center gap-1 whitespace-nowrap rounded-full px-3 hover:text-red [&::-webkit-details-marker]:hidden">{{ __('সব বিভাগ') }} <span aria-hidden="true">▾</span></summary>
                    <div class="absolute right-0 top-12 z-50 w-[36rem] rounded-2xl border-2 border-navy bg-paper p-4 text-base shadow-[4px_4px_0_#13212C]">
                        <x-menu-panel :menu="$menu" :wide="true" />
                    </div>
                </details>
            </nav>
            <div class="flex min-w-0 items-center gap-1 max-[359px]:gap-0 sm:gap-3">
                {{-- Tablets: every section, grouped. Phones use "আরও" in the bottom bar instead (D166). --}}
                <details class="relative hidden md:block lg:hidden" data-menu>
                    <summary class="flex h-11 cursor-pointer list-none items-center gap-1 rounded-full px-3 font-bold max-[359px]:px-2 hover:bg-peach-light [&::-webkit-details-marker]:hidden">{{ __('বিভাগ') }} <span aria-hidden="true">▾</span></summary>
                    <div class="fixed inset-x-3 top-[4.75rem] z-50 max-h-[calc(100dvh-10.5rem)] overflow-y-auto rounded-2xl border-2 border-navy bg-paper p-3 shadow-[4px_4px_0_#13212C] sm:absolute sm:inset-x-auto sm:right-0 sm:top-12 sm:w-[22rem]">
                        <x-menu-panel :menu="$menu" />
                    </div>
                </details>
                <a href="{{ \App\Support\Url::to('/search') }}" class="flex h-11 w-11 flex-none items-center justify-center rounded-full hover:bg-peach-light max-[359px]:w-10" aria-label="{{ __('সার্চ') }}" title="{{ __('সার্চ') }}">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.5-4.5"/></svg>
                </a>
                {{-- D166: a labelled way to one's own page ("আমার"), or "ঢুকুন" before signing in --}}
                @auth
                    @php $hdrAcct = \App\Support\AccountMenu::for(auth()->user()); @endphp
                    <a href="{{ \App\Support\Url::to('/account') }}" class="flex h-11 flex-none items-center gap-2 rounded-full border-2 border-navy bg-white py-0 pl-1 pr-4 font-bold hover:bg-peach-light {{ request()->is('account*') ? '!bg-navy text-white' : '' }}" aria-label="{{ __('আমার অ্যাকাউন্ট') }}">
                        @if ($hdrAcct['avatar'])<img src="{{ $hdrAcct['avatar'] }}" alt="" class="h-8 w-8 rounded-full border-2 border-navy object-cover">
                        @else<span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-navy bg-peach text-sm font-extrabold text-navy" aria-hidden="true">{{ $hdrAcct['initial'] }}</span>@endif
                        <span aria-hidden="true">{{ __('অ্যাকাউন্ট') }}</span>
                    </a>
                @else
                    <a href="{{ \App\Support\Url::to('/login') }}" class="flex h-11 flex-none items-center whitespace-nowrap rounded-full border-2 border-navy bg-white px-4 font-bold hover:bg-peach-light">{{ __('সাইন ইন') }}</a>
                @endauth
                <a href="{{ \App\Support\Url::to('/submit') }}" class="hidden h-11 items-center rounded-full border-2 border-navy bg-red px-5 font-bold text-white shadow-[4px_4px_0_#13212C] md:flex">{{ __('লেখা পাঠান') }}</a>
            </div>
        </div>
    </header>

    <main id="main">{{ $slot }}</main>

    @if (request()->attributes->get('hp.compact-footer'))
    {{-- D166: account pages end with one quiet line, not the whole footer --}}
    <footer class="mt-10 border-t-2 border-navy px-5 py-4 text-sm text-ink2">
        <div class="mx-auto flex max-w-[1000px] items-center justify-between gap-4"><span>{{ __('হাতপাখা') }} · ISSN 2321-3035</span><a href="{{ \App\Support\Url::to('/contact-us') }}" class="flex min-h-11 items-center font-bold text-sky-night underline">{{ __('সাহায্য') }}</a></div>
    </footer>
    @else
    <footer class="mt-16 bg-navy text-paper">
        <div class="mx-auto flex max-w-[1320px] flex-wrap items-end justify-between gap-8 px-5 py-12">
            <div>
                <div class="font-hand text-6xl leading-none text-sky md:text-8xl">আবার আসবেন</div>
                <p class="mt-3 text-sm text-peach-light">ISSN 2321-3035 · ২০১২ থেকে · লেখা প্রকাশ সবসময় বিনামূল্যে</p>
            </div>
            @if (! auth()->check() && ! request()->attributes->get('hp.newsletter.card'))<x-newsletter-signup variant="footer" />@endif
            <nav aria-label="{{ __('ফুটার') }}" class="grid w-full grid-cols-2 gap-x-6 text-base sm:w-auto sm:grid-cols-3">
                @foreach (\App\Models\Page::footerLinks() as [$t, $u])
                    <a href="{{ $u }}" class="flex min-h-11 items-center hover:text-sky">{{ $t }}</a>
                @endforeach
                <a href="{{ \App\Support\Url::to('/archive') }}" class="flex min-h-11 items-center hover:text-sky">আর্কাইভ</a>
                <a href="{{ \App\Support\Url::to('/feed') }}" class="flex min-h-11 items-center hover:text-sky">RSS</a>
                @if (filled($fb = config('services.social.facebook')))<a href="{{ $fb }}" rel="noopener me" target="_blank" class="flex min-h-11 items-center hover:text-sky">ফেসবুক</a>@endif
                @if (filled($ig = config('services.social.instagram')))<a href="{{ $ig }}" rel="noopener me" target="_blank" class="flex min-h-11 items-center hover:text-sky">ইনস্টাগ্রাম</a>@endif
            </nav>
        </div>
        {{-- D128: always a way to keep Hatpakha on a phone, like the store buttons other sites show (hidden inside the app itself) --}}
        <div data-app-badges class="mx-auto max-w-[1320px] border-t border-white/20 px-5 pb-12 pt-8">
            <p class="font-bold text-peach-light">হাতপাখা ফোনে রাখুন, অ্যাপের মতো · বিনামূল্যে</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach ([['android', 'Android ফোনে', '<rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/>'], ['iphone', 'iPhone-এ', '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 4.5h4"/>'], ['computer', 'কম্পিউটারে', '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/>']] as [$id, $name, $icon])
                    <a href="{{ \App\Support\Url::to('/app') }}#{{ $id }}" @if ($id === 'android') data-install-now @endif class="inline-flex h-14 items-center gap-3 rounded-2xl bg-paper px-4 text-navy hover:bg-peach-light">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">{!! $icon !!}</svg>
                        <span class="leading-tight"><span class="block text-xs font-semibold">যোগ করুন</span><span class="block font-extrabold">{{ $name }}</span></span>
                    </a>
                @endforeach
            </div>
        </div>
    </footer>
    @endif

    {{-- Mobile bottom tab bar (mobile UX review): icons, the current tab in red, "লিখুন" as a raised sticker, safe-area padding. --}}
    @php
        $tabs = [
            [__('হোম'), url('/').'/', request()->is('/'), 'home'],
            [__('কবিতা'), \App\Support\Url::to('/category/poetry'), request()->is('category/poetry*'), 'poem'],
            [__('লিখুন'), \App\Support\Url::to('/submit'), request()->is('submit*', 'submissions*'), 'write'],
            [__('গল্প'), \App\Support\Url::to('/category/short-stories'), request()->is('category/short-stories*'), 'story'],
            [__('আরও'), '#more-sheet', false, 'more'],   // D166 (owner): "আরও" opens one sheet with everything, the account included
        ];
        $icons = [
            'home' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/>',
            'poem' => '<path d="M20 4c-7 1-12 6-13 13l-3 3"/><path d="M7 17c4 0 8-3 9-8"/>',
            'write' => '<path d="M12 5v14M5 12h14"/>',
            'story' => '<path d="M4 5h6a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H4z"/><path d="M20 5h-6a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h6z"/>',
            'more' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        ];
    @endphp
    @unless (request()->is('sonkha/*/boi*') || request()->attributes->get('hp.no-bottom-nav'))
    @include('partials.more-sheet', ['menu' => $menu])
    <nav aria-label="{{ __('প্রধান') }}" class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t-2 border-navy bg-paper pb-[env(safe-area-inset-bottom)] text-[13px] font-bold md:hidden">
        @foreach ($tabs as [$label, $href, $on, $ic])
            @if ($ic === 'write')
                <a href="{{ $href }}" class="flex h-16 flex-col items-center justify-center" @if ($on) aria-current="page" @endif>
                    <span class="-mt-5 flex h-11 w-11 items-center justify-center rounded-full border-2 border-navy bg-red text-white shadow-[2px_2px_0_#13212C]"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true">{!! $icons[$ic] !!}</svg></span>
                    <span class="mt-0.5 text-red">{{ $label }}</span>
                </a>
            @elseif ($ic === 'more')
                <button type="button" data-more-open aria-controls="more-sheet" aria-expanded="false" class="flex h-16 flex-col items-center justify-center gap-0.5 font-bold aria-expanded:text-red">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">{!! $icons[$ic] !!}</svg>
                    <span>{{ $label }}</span>
                </button>
            @else
                <a href="{{ $href }}" class="flex h-16 flex-col items-center justify-center gap-0.5 {{ $on ? 'text-red' : '' }}" @if ($on) aria-current="page" @endif>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$ic] !!}</svg>
                    <span>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </nav>
    @endunless
    {{-- D127: "keep Hatpakha on your phone" hint (phones only, from the third page, never again for 30 days after "পরে") --}}
    <div data-install hidden role="dialog" aria-labelledby="hp-install-h" class="fixed inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-50 mx-auto max-w-md rounded-2xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#CB1B32] md:hidden">
        <div class="flex items-start gap-3">
            <img src="{{ asset('images/app/icon-192.png') }}" alt="" width="48" height="48" class="h-12 w-12 flex-none rounded-xl">
            <div class="min-w-0 flex-1">
                <strong id="hp-install-h" class="block text-lg leading-snug">হাতপাখা রাখুন ফোনের হোম স্ক্রিনে</strong>
                <p class="text-sm text-ink2" data-install-android>অ্যাপের মতো এক ছোঁয়ায় খুলবে। আগে পড়া লেখা ইন্টারনেট ছাড়াও পড়া যাবে। কোনো ডাউনলোড বা জায়গা লাগে না। পরে চাইলে পাতার একেবারে নিচে "যোগ করুন" বোতাম আছে।</p>
                <p class="text-sm text-ink2" data-install-ios hidden>Safari-র <strong>শেয়ার</strong> বোতাম (□↑) ছুঁয়ে <strong>Add to Home Screen</strong> বাছুন, তারপর <strong>Add</strong>। এরপর হোম স্ক্রিনের হাতপাখা আইকন থেকে খুললে অ্যাপের মতো দেখাবে। <a href="{{ \App\Support\Url::to('/app') }}#iphone" class="underline">ছবিসহ দেখুন</a></p>
            </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
            <button type="button" class="h-11 rounded-full px-4 font-bold text-ink2" data-install-later>পরে</button>
            <button type="button" class="h-11 rounded-full bg-red px-5 font-bold text-white" data-install-go>যোগ করুন</button>
            <button type="button" class="h-11 rounded-full bg-navy px-5 font-bold text-white" data-install-ok hidden>বুঝেছি</button>
        </div>
    </div>
    @if (\App\Support\WebPush::publicKey())
    {{-- D128: right after adding Hatpakha to the phone (or on its first opening from the home screen), ask once about notifications --}}
    <div data-push-ask @auth data-account @endauth hidden role="dialog" aria-labelledby="hp-push-ask-h" class="fixed inset-x-3 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-50 mx-auto max-w-md rounded-2xl border-2 border-navy bg-white p-4 shadow-[4px_4px_0_#CB1B32] md:bottom-6">
        <strong id="hp-push-ask-h" class="block text-lg leading-snug">🔔 নতুন লেখা এলে ফোনে জানাব?</strong>
        <p class="mt-1 text-sm text-ink2">দিনে একবার, সন্ধ্যায়: সেদিন যা নতুন লেখা বেরোল।@auth আর যাঁদের অনুসরণ করেন, তাঁদের নতুন লেখা।@endauth ছুঁয়ে দিলেই লেখা খুলবে। যে-কোনো সময় বন্ধ করা যায়।</p>
        <p class="mt-1 text-sm font-bold" data-push-ask-msg role="status"></p>
        <div class="mt-3 flex justify-end gap-2">
            <button type="button" class="h-11 rounded-full px-4 font-bold text-ink2" data-push-ask-no>এখন না</button>
            <button type="button" class="h-11 rounded-full bg-red px-5 font-bold text-white" data-push-ask-yes>হ্যাঁ, জানান</button>
        </div>
    </div>
    @endif
    @if (\App\Support\Ads::on())
        {{-- D111: request each ad only when the reader scrolls near it; hidden spots (e.g. the side ad on smaller screens) are never requested --}}
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (() => {
                const fill = (el) => { el.className = 'adsbygoogle'; try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {} };
                const spots = document.querySelectorAll('ins.hp-ad-pending');
                if (!('IntersectionObserver' in window)) { spots.forEach(fill); return; }
                const io = new IntersectionObserver((entries) => entries.forEach((e) => { if (e.isIntersecting) { io.unobserve(e.target); fill(e.target); } }), { rootMargin: '400px 0px' });
                spots.forEach((el) => io.observe(el));
            })();
        </script>
    @endif
    {{-- Owner (2026-11-08): remember pieces this reader opened (in their own browser only) and swap them out of the home shelves. --}}
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            let read = [];
            try { read = JSON.parse(localStorage.getItem('hp.read') || '[]'); } catch (e) {}
            const art = document.querySelector('article[data-view]');
            if (art) {
                const id = art.dataset.view;
                read = [id, ...read.filter((x) => x !== id)].slice(0, 400);
                try { localStorage.setItem('hp.read', JSON.stringify(read)); } catch (e) {}
                return;
            }
            const seen = new Set(read);
            document.querySelectorAll('[data-rotate]').forEach((box) => {
                const max = parseInt(box.dataset.rotate, 10) || 4;
                const items = [...box.querySelectorAll(':scope > [data-pid]')];
                const order = [...items.filter((el) => !seen.has(el.dataset.pid)), ...items.filter((el) => seen.has(el.dataset.pid))];
                const skip = box.hasAttribute('data-skip-first') ? 1 : 0;
                items.forEach((el) => { el.hidden = true; el.removeAttribute('data-first'); });
                order.slice(skip, skip + max).forEach((el, i) => { el.hidden = false; if (i === 0) el.setAttribute('data-first', ''); });
                order.forEach((el) => box.appendChild(el));
            });
        })();
    </script>
</body>
</html>
