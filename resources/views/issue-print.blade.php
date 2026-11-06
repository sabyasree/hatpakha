{{-- Print-ready book of an issue (D68): open in the desk, then the browser's "Save as PDF". Browsers shape Bengali perfectly. --}}
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>হাতপাখা · {{ $issue->title }} (ছাপার জন্য)</title>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    <style>
        @page { size: 6in 9in; margin: 0.75in 0.6in 0.8in 0.75in; }
        html { background: #fff; color: #13212C; }
        body { margin: 0; font-family: 'Noto Serif Bengali', serif; font-size: 11.5pt; line-height: 1.75; }
        .sheet { max-width: 6in; margin: 0 auto; padding: 0 .2in; }
        .page { break-before: page; }
        h1, h2 { font-family: 'Anek Bangla', sans-serif; line-height: 1.3; margin: 0 0 .2em; }
        h2 { font-size: 20pt; } .section { color: #CB1B32; font-size: 9.5pt; margin: 0; } .by { color: #4D5A63; margin: 0 0 1.2em; }
        p { margin: 0 0 .75em; orphans: 3; widows: 3; } .poem { text-align: center; } blockquote { margin: 1em 1.5em; font-style: italic; }
        .cover { height: 7.4in; display: flex; flex-direction: column; justify-content: center; text-align: center; }
        .cover img { max-width: 100%; max-height: 5.2in; object-fit: contain; margin: 0 auto 1em; }
        .mast { font-family: 'Galada', cursive; color: #CB1B32; font-size: 40pt; margin: 0; }
        .toc li { margin: .3em 0; } .small { font-size: 9pt; color: #4D5A63; }
        .ta-center { text-align: center; } .ta-right { text-align: right; }
        .help { font-family: 'Anek Bangla', sans-serif; background: #DDF1FA; border: 2px solid #2DAAE2; border-radius: 12px; padding: 12px 16px; margin: 16px auto; max-width: 6in; }
        .help button { font: inherit; font-weight: 700; background: #CB1B32; color: #fff; border: 0; border-radius: 999px; padding: 8px 18px; cursor: pointer; }
        @media print { .help { display: none; } .sheet { padding: 0; } }
    </style>
</head>
<body>
    <div class="help">
        <strong>PDF বানাতে:</strong> নিচের বোতাম (বা Ctrl/Cmd + P) → গন্তব্য "Save as PDF" → পেপার সাইজ: এটি নিজে থেকেই ৬×৯ ইঞ্চি বই-মাপ নেবে (না নিলে "A5") → "Background graphics" চালু → Save।
        ছবিসহ প্রচ্ছদ প্রথম পাতায় থাকবে; প্রতিটি লেখা নতুন পাতায় শুরু হবে।
        <p style="margin:.5em 0 0"><button onclick="window.print()">PDF হিসেবে রাখুন</button></p>
    </div>
    <div class="sheet">
        <section class="cover">
            @if ($issue->cover_path)<img src="{{ asset(ltrim($issue->cover_path, '/')) }}" alt="">@endif
            <p class="mast">হাতপাখা</p>
            <h1>{{ $issue->title }}</h1>
            <p class="small">বাংলা সাহিত্যের ওয়েব পত্রিকা · ISSN 2321-3035</p>
        </section>
        <section class="page">
            <p class="small">হাতপাখা · {{ $issue->title }}<br>প্রকাশক: হাতপাখা (hatpakha.com) · ISSN 2321-3035<br>
                সম্পাদনা: হাতপাখা সম্পাদকমণ্ডলী<br>প্রতিটি লেখার স্বত্ব তার লেখকের। লেখকের অনুমতি ছাড়া কোনো অংশ পুনর্মুদ্রণ করা যাবে না।</p>
            <h2 style="margin-top:1.5em">সূচি</h2>
            <ol class="toc">@foreach ($pieces as $p)<li>{{ $p->title }} <span class="small">— {{ $p->authors->map->publicName()->implode(', ') }}</span></li>@endforeach</ol>
        </section>
        @if ($issue->editor_note)
            <section class="page"><h2>সম্পাদকের কথা</h2>@foreach (preg_split('/\n{2,}/', trim($issue->editor_note)) as $para)<p>{{ trim($para) }}</p>@endforeach</section>
        @endif
        @foreach ($pieces as $p)
            <section class="page {{ $p->isPoem() ? 'poem' : '' }}">
                <p class="section">{{ $p->section?->name_bn }}</p>
                <h2>{{ $p->title }}</h2>
                <p class="by">{{ $p->authors->map->publicName()->implode(', ') }}</p>
                {!! \App\Support\IssueBook::xhtml((string) $p->body_html) !!}
            </section>
        @endforeach
    </div>
</body>
</html>
