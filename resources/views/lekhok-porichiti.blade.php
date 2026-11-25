{{-- লেখক-পরিচিতি (owner, 2026-11-24; research §B7): a writer's one-page portfolio, to print or keep as PDF (the browser's
     "Save as PDF", like the issue book, D68). Real data only: published pieces, certificates with their check links,
     honours, and a QR code to the writer's page. Public, noindex. --}}
@php
    use App\Support\Bn;
    use App\Models\Certificate;
@endphp
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo :seo="$seo" />
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    <style>
        @page { size: A4; margin: 14mm 14mm 16mm; }
        html { background: #FFFBF4; color: #13212C; }
        body { margin: 0; font-family: 'Anek Bangla', 'Noto Sans Bengali', sans-serif; font-size: 11pt; line-height: 1.6; }
        .sheet { max-width: 182mm; margin: 0 auto; padding: 10mm 8mm 12mm; background: #fff; }
        .mast { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; border-bottom: 3px solid #CB1B32; padding-bottom: 6px; }
        .mast b { font-family: 'Galada', cursive; color: #CB1B32; font-size: 24pt; font-weight: 400; line-height: 1; }
        .mast span { font-size: 9pt; color: #4D5A63; text-align: right; }
        .who { display: flex; gap: 16px; align-items: center; margin-top: 14px; }
        .who img, .who .ini { width: 26mm; height: 26mm; border-radius: 50%; border: 2px solid #13212C; object-fit: cover; flex: none; }
        .who .ini { display: flex; align-items: center; justify-content: center; background: #FBEBD8; font-size: 28pt; font-weight: 800; }
        h1 { font-size: 22pt; line-height: 1.2; margin: 0; }
        .kicker { font-family: 'Galada', cursive; color: #CB1B32; font-size: 13pt; margin: 0; }
        .muted { color: #4D5A63; font-size: 9.5pt; }
        .bio { font-family: 'Noto Serif Bengali', serif; margin: 10px 0 0; white-space: pre-line; }
        h2 { font-size: 13pt; margin: 16px 0 6px; padding-bottom: 2px; border-bottom: 1.5px solid #13212C; break-after: avoid; }
        ul.honours { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 6px; }
        ul.honours li { background: #FBEBD8; border-radius: 999px; padding: 2px 10px; font-weight: 700; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { text-align: left; padding: 3px 6px 3px 0; border-bottom: 1px solid #E8DCCB; vertical-align: top; }
        th { font-size: 9pt; color: #4D5A63; font-weight: 700; }
        tr { break-inside: avoid; }
        td.t { font-family: 'Noto Serif Bengali', serif; font-weight: 700; }
        .foot { display: flex; gap: 14px; align-items: center; margin-top: 18px; padding: 10px 12px; border: 1.5px solid #13212C; border-radius: 10px; break-inside: avoid; }
        .foot svg { flex: none; }
        .foot p { margin: 0; }
        .help { background: #DDF1FA; border: 2px solid #13212C; border-radius: 14px; padding: 12px 16px; margin: 16px auto; max-width: 182mm; }
        .help button { font: inherit; font-weight: 700; background: #CB1B32; color: #fff; border: 0; border-radius: 999px; padding: 10px 22px; cursor: pointer; min-height: 44px; }
        .help a { color: #13212C; font-weight: 700; }
        @media print { html { background: #fff; } .help { display: none; } .sheet { padding: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="help">
        <p style="margin:0 0 .5em"><strong>PDF বানাতে:</strong> নিচের বোতাম টিপুন, তারপর গন্তব্য “Save as PDF” বেছে সেভ করুন। কাগজে ছাপতেও একই বোতাম।</p>
        <button type="button" data-print>PDF হিসেবে রাখুন</button>
        <a href="{{ $author->url() }}" style="margin-left:12px">← লেখক-পাতায় ফিরুন</a>
    </div>
    <main class="sheet">
        <div class="mast"><b>হাতপাখা</b><span>বাংলা সাহিত্যের ওয়েব পত্রিকা, ২০১২ থেকে<br>ISSN 2321-3035 · hatpakha.com</span></div>
        <div class="who">
            @if ($author->avatar_path)
                <img src="{{ $author->avatar_path }}" alt="{{ $author->publicName() }}">
            @else
                <span class="ini" aria-hidden="true">{{ $author->initial() }}</span>
            @endif
            <div>
                <p class="kicker">লেখক-পরিচিতি</p>
                <h1>{{ $author->publicName() }}</h1>
                <p class="muted">হাতপাখায় {{ Bn::num((string) $posts->count()) }}টি প্রকাশিত লেখা · {{ Bn::date($posts->min('published_at')) }} থেকে @if ($author->city)· {{ $author->city }}@endif</p>
            </div>
        </div>
        @if ($author->bio)<p class="bio">{{ $author->bio }}</p>@endif

        @if ($author->honour_label || $choices->isNotEmpty() || $certificates->whereIn('kind', ['honour', 'contest'])->isNotEmpty())
            <h2>সম্মান</h2>
            <ul class="honours">
                @if ($author->honour_label)<li>★ {{ $author->honour_label }}@if ($author->honoured_at) · {{ Bn::date($author->honoured_at) }}@endif</li>@endif
                @foreach ($choices as $won)<li>♥ পাঠকের পছন্দ · {{ \App\Support\Feelings::monthLabel($won->readers_choice) }}: {{ $won->title }}</li>@endforeach
                @foreach ($certificates->whereIn('kind', ['honour', 'contest']) as $c)
                    <li>{{ Certificate::THEMES[$c->theme()][0] }}@if ($c->kind === 'contest' && ($parts = $c->contestParts())[0]): {{ $parts[0] }}@if ($parts[1]) · {{ $parts[1] }}@endif @elseif ($c->issue_label): {{ $c->issue_label }}@endif</li>
                @endforeach
            </ul>
        @endif

        <h2>হাতপাখায় প্রকাশিত লেখা</h2>
        <table>
            <thead><tr><th>লেখা</th><th>বিভাগ</th><th>প্রকাশ</th></tr></thead>
            <tbody>
                @foreach ($posts as $p)
                    <tr><td class="t">{{ $p->title }}@if ($p->issue && $p->issue->isLive())<span class="muted" style="font-weight:400"> · {{ $p->issue->title }}</span>@endif</td><td>{{ $p->section?->name_bn }}</td><td style="white-space:nowrap">{{ Bn::date($p->published_at) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="muted" style="margin-top:4px">সব লেখা হাতপাখা ওয়েব পত্রিকায় (ISSN 2321-3035) প্রকাশিত; প্রতিটি লেখার স্বত্ব লেখকের।</p>

        @if ($certificates->isNotEmpty())
            <h2>শংসাপত্র</h2>
            <table>
                <thead><tr><th>শংসাপত্র</th><th>লেখা</th><th>যাচাই</th></tr></thead>
                <tbody>
                    @foreach ($certificates as $c)
                        <tr><td>{{ Certificate::THEMES[$c->theme()][0] }}<span class="muted"> · {{ Bn::date($c->issued_at) }}</span></td><td class="t">{{ $c->post?->title ?? ($c->issue_label ?: '') }}</td><td style="word-break:break-all">{{ $c->verifyUrl() }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="foot">
            @if ($qr){!! $qr !!}@endif
            <p><strong>লেখক-পাতা:</strong> {{ $author->url() }}<br><span class="muted">QR কোডটি ফোনে স্ক্যান করলে হাতপাখায় {{ $author->publicName() }}-এর সব লেখা খুলবে; প্রতিটি শংসাপত্র তার লিংকে যাচাই করা যায়। এই পরিচিতি তৈরি হয়েছে {{ Bn::date(now()) }}।</span></p>
        </div>
    </main>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.querySelector('[data-print]')?.addEventListener('click', function () { window.print(); });
    </script>
</body>
</html>
