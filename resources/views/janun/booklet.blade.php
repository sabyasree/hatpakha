{{-- D302: a free printable puja booklet (steps, checked mantras with meaning, Roman reading and English, times, QR).
     The browser's "Save as PDF" shapes Bengali perfectly. noindex: the page itself is the original. --}}
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, follow">
    <title>{{ $p->title }}: ছাপার পুস্তিকা · হাতপাখা</title>
    <link rel="canonical" href="{{ $p->url() }}">
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css'])
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        @page { size: A5; margin: 12mm 11mm 14mm; }
        body { background: #fff; color: #13212C; font-family: 'Noto Serif Bengali', serif; }
        .sheet { max-width: 148mm; margin: 0 auto; padding: 16px; }
        .mantra, .steps li, figure { break-inside: avoid; } h2 { break-after: avoid; }
        @media print { .no-print { display: none !important; } .sheet { padding: 0; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="no-print mx-auto max-w-[148mm] p-4">
        <div class="rounded-2xl border-2 border-sky-deep bg-sky-tint p-4 font-sans">
            <p class="font-bold">বিনামূল্যে ছাপুন, বিলি করুন: বাড়ির পুজোয়, পাড়ার বা বিদেশের পুজো কমিটিতে।</p>
            <p class="mt-1 text-sm">ফোনে বা কম্পিউটারে "ছাপুন / PDF হিসেবে রাখুন" টিপুন। কাগজের মাপ A5 (বা A4-এ দুই পাতা এক শিটে)।</p>
            <button type="button" data-print class="mt-3 min-h-11 rounded-full bg-red px-5 font-bold text-white">ছাপুন / PDF হিসেবে রাখুন</button>
            <a href="{{ $p->url() }}" class="ml-3 underline">← পাতায় ফিরুন</a>
        </div>
    </div>
    <main class="sheet">
        <p class="text-center font-display text-sm font-bold text-red">হাতপাখা · জেনে রাখুন</p>
        <h1 class="mt-1 text-center font-display text-3xl font-extrabold leading-tight">{{ $p->title }}</h1>
        @if (! empty($b['intro']))<p class="mt-3 text-base leading-relaxed">{{ $b['intro'] }}</p>@endif
        @if ($times->isNotEmpty())
            @include('janun.partials.times', ['festivals' => $times, 'heading' => 'এবারের তিথি ও সময়', 'links' => false])
        @endif
        @foreach ($sections as $s)
            <h2 class="mt-6 font-display text-xl font-extrabold">{{ $s['heading'] }}</h2>
            @if (! empty($s['steps']))
                <ol class="steps mt-2 list-decimal space-y-1 pl-6 text-base leading-relaxed">@foreach ($s['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol>
            @endif
            @foreach ((array) ($s['mantras'] ?? []) as $m)
                <div class="mantra mt-3 rounded-xl border-2 border-peach bg-peach-light p-3">
                    <div class="text-sm font-bold text-ink2">{{ $m['name'] }}</div>
                    <p lang="sa" class="mt-1 whitespace-pre-line text-lg font-bold leading-relaxed">{{ $m['text'] }}</p>
                    @if (filled($m['roman'] ?? ''))<p lang="sa-Latn" class="mt-1 text-sm italic">{{ $m['roman'] }}</p>@endif
                    <p class="mt-1 text-sm leading-relaxed"><b>অর্থ:</b> {{ $m['meaning'] }}</p>
                    @if (filled($m['english'] ?? ''))<p lang="en" class="mt-1 text-sm leading-relaxed">{{ $m['english'] }}</p>@endif
                </div>
            @endforeach
        @endforeach
        <figure class="mt-8 flex items-center gap-4 border-t-2 border-rule pt-4">
            @if ($qr)<img src="{{ $qr }}" alt="" width="96" height="96" class="h-24 w-24 flex-none">@endif
            <figcaption class="text-sm leading-relaxed">
                <b class="font-display text-red">হাতপাখা</b>: বাংলা সাহিত্যের ওয়েব পত্রিকা, ২০১২ থেকে।<br>
                পুরো পাতা, আরও পুজোর নিয়ম ও মন্ত্র: <b>{{ preg_replace('~^https?://(www\.)?~', '', rtrim($p->url(), '/')) }}</b><br>
                মন্ত্র দুটি নির্ভরযোগ্য সূত্রে মিলিয়ে দেওয়া। বিনামূল্যে ছাপা ও বিলি করা যায়।
            </figcaption>
        </figure>
    </main>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.querySelector('[data-print]')?.addEventListener('click', () => window.print());</script>
</body>
</html>
