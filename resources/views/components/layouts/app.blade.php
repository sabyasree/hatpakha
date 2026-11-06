@props(['seo'])
<!doctype html>
<html lang="{{ $seo->lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo :seo="$seo" />
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- TODO(Phase 4): self-host subset fonts (docs/SEO.md §5). --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anek+Bangla:wdth,wght@75..100,400..800&family=Noto+Serif+Bengali:wght@400;500;700&family=Galada&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (config('services.adsense.client'))
        <script async nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('services.adsense.client') }}" crossorigin="anonymous"></script>
    @endif
</head>
<body class="min-h-screen bg-paper text-navy antialiased pb-16 md:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:bg-paper focus:p-2">মূল লেখায় যান</a>

    <header class="border-b-2 border-navy bg-paper">
        <div class="mx-auto flex max-w-[1320px] items-center justify-between gap-5 px-5 py-3">
            <a href="{{ url('/') }}/" class="flex flex-none flex-col items-end" aria-label="হাতপাখা — প্রথম পাতা">
                <img src="{{ asset('images/logo-wordmark.png') }}" alt="Hatpakha" width="367" height="90" class="h-9 w-auto md:h-12">
                <span class="-mt-1 text-[11px] font-bold">ISSN: 2321-3035</span>
            </a>
            <nav aria-label="বিভাগ" class="hidden gap-7 text-lg font-semibold lg:flex">
                @foreach (\App\Models\Section::where('is_active', true)->orderBy('sort')->limit(7)->get() as $s)
                    <a href="{{ $s->url() }}" class="hover:text-red">{{ $s->name_bn }}</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-2">
                <a href="{{ \App\Support\Url::to('/submissions') }}" class="hidden h-11 items-center rounded-full border-2 border-navy bg-red px-5 font-bold text-white shadow-[4px_4px_0_#13212C] md:flex">লেখা পাঠান</a>
            </div>
        </div>
    </header>

    <main id="main">{{ $slot }}</main>

    <footer class="mt-16 bg-navy text-paper">
        <div class="mx-auto flex max-w-[1320px] flex-wrap items-end justify-between gap-8 px-5 py-12">
            <div>
                <div class="font-hand text-6xl leading-none text-sky md:text-8xl">আবার আসবেন</div>
                <p class="mt-3 text-sm text-[#C9D3DA]">ISSN 2321-3035 · ২০১২ থেকে · লেখা প্রকাশ সবসময় বিনামূল্যে</p>
            </div>
            <nav aria-label="ফুটার" class="flex gap-10 text-base">
                @foreach (array_chunk(\App\Models\Page::footerLinks(), 4) as $col)
                    <div class="flex flex-col gap-2">@foreach ($col as [$t, $u])<a href="{{ $u }}" class="hover:text-sky">{{ $t }}</a>@endforeach</div>
                @endforeach
                <div class="flex flex-col gap-2"><a href="{{ \App\Support\Url::to('/feed') }}" class="hover:text-sky">RSS</a></div>
            </nav>
        </div>
    </footer>

    {{-- Mobile bottom tab bar --}}
    <nav aria-label="প্রধান" class="fixed inset-x-0 bottom-0 z-40 grid h-16 grid-cols-4 border-t-2 border-navy bg-paper text-xs font-bold md:hidden">
        <a href="{{ url('/') }}/" class="flex items-center justify-center">হোম</a>
        <a href="{{ \App\Support\Url::to('/category/poetry') }}" class="flex items-center justify-center">কবিতা</a>
        <a href="{{ \App\Support\Url::to('/submissions') }}" class="flex items-center justify-center text-red">লিখুন</a>
        <a href="{{ \App\Support\Url::to('/category/short-stories') }}" class="flex items-center justify-center">গল্প</a>
    </nav>
</body>
</html>
