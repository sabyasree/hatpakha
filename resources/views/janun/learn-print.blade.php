{{-- D317: a বাংলা শিখি worksheet: grey letters to write over, then empty boxes. noindex; the lesson page is the original. --}}
@php
    $items = match ($key) {
        'kar' => collect($l['rows'])->map(fn ($r) => ['ক'.$r[1], $r[5][0][0]]),
        'phola' => collect($l['rows'])->map(fn ($r) => [$r[2], $r[4][0][0]]),
        'juktakkhor' => collect($l['rows'])->map(fn ($r) => [$r[0], $r[2][0]])->take(16),
        default => collect($l['groups'])->flatMap(fn ($g) => collect($g[1])->take(3))->map(fn ($w) => [preg_replace('/\s.*$/u', '', $w[0]), $w[1]]),
    };
@endphp
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, follow">
    <title>{{ $l['title'] }}: ওয়ার্কশিট · হাতপাখা</title>
    <link rel="canonical" href="{{ \App\Support\Url::to('/janun/learn-bengali/'.$key) }}">
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css'])
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        @page { size: A4; margin: 12mm; }
        body { background: #fff; color: #13212C; }
        .sheet { max-width: 186mm; margin: 0 auto; padding: 16px; }
        .row { break-inside: avoid; display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; margin-top: 6px; }
        .box { height: 22mm; border: 1px solid #E8DCCB; display: flex; align-items: center; justify-content: center; font-family: 'Anek Bangla', sans-serif; font-size: 13mm; font-weight: 400; }
        .trace { color: var(--color-ink2); opacity: .28; }
        @media print { .no-print { display: none !important; } .sheet { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print mx-auto max-w-[186mm] p-4">
        <div class="rounded-2xl border-2 border-sky-deep bg-sky-tint p-4 font-sans">
            <p class="font-bold">ছেপে নিন: প্রথম ঘরে দেখে নিন, পরের তিন ঘরে ধূসর অক্ষরের ওপর লিখুন, শেষ দুই ঘরে নিজে। কাগজের মাপ A4।</p>
            <button type="button" data-print hidden class="mt-3 min-h-11 rounded-full bg-red px-5 font-bold text-white">ছাপুন / PDF হিসেবে রাখুন</button>
            <a href="{{ \App\Support\Url::to('/janun/learn-bengali/'.$key) }}" class="ml-3 inline-flex min-h-11 items-center whitespace-nowrap underline">← পাঠে ফিরুন</a>
        </div>
    </div>
    <main class="sheet">
        <p class="font-display text-sm font-bold text-red">হাতপাখা · বাংলা শিখি</p>
        <h1 class="font-display text-2xl font-extrabold">{{ $l['title'] }}: লিখে শিখি</h1>
        <p class="mt-1 text-sm">নাম: ____________________ &nbsp; তারিখ: ____________</p>
        @foreach ($items as [$glyph, $example])
            <div class="row" aria-label="{{ $glyph }}">
                @for ($i = 0; $i < 6; $i++)<div class="box {{ $i > 0 ? 'trace' : '' }}">{{ $i < 4 ? $glyph : '' }}</div>@endfor
            </div>
            <p class="text-sm">{{ $glyph }}: <b>{{ $example }}</b></p>
        @endforeach
        <p class="mt-4 text-xs text-ink2">hatpakha.com/janun/learn-bengali/ · বিনা পয়সায় ছাপুন, বিলি করুন।</p>
    </main>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">const pb = document.querySelector('[data-print]'); if (pb) { pb.hidden = false; pb.addEventListener('click', () => window.print()); }</script>
</body>
</html>
