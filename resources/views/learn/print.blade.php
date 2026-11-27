{{-- D321: worksheets to print (or save as PDF; the deploy job saves them as PDF files): one lesson, or a unit's teacher's set.
     A4 or US Letter. Black and white friendly: see it, write over the grey, write alone; match pictures and words. --}}
@php
    $C = \App\Support\LearnCourse::class;
    $N = fn ($n) => \App\Support\Bn::num((string) $n);
    $glyph = fn ($x) => match (\App\Http\Controllers\LearnController::kind($x)) { 'sign' => ($x === 'ঁ' ? 'কাঁ' : 'ক'.$x), 'phola' => ($x === 'র্' ? 'র্ক' : 'ক'.$x), default => $x };
    $order = fn (array $a, string $seed) => collect($a)->sortBy(fn ($w) => crc32($seed.$w[0]))->values()->all();
    $answers = [];
    $strokeMap = \App\Models\LearnStroke::approved(collect($pages)->flatMap(fn ($p) => (array) ($C::lesson($p[0], $p[1])['new'] ?? []))->map(fn ($x) => $glyph($x))->all());   // D325
@endphp
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, follow">
    <title>{{ $title }} · হাতপাখা</title>
    <link rel="canonical" href="{{ $seo->canonical }}">
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css'])
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        @page { size: {{ $size === 'letter' ? 'letter' : 'A4' }}; margin: 11mm 11mm 13mm; }
        html, body { background: #fff !important; color: #13212C; }
        .sheet { max-width: {{ $size === 'letter' ? '194mm' : '188mm' }}; margin: 0 auto; padding: 16px; }
        .page { break-after: page; } .page:last-child { break-after: auto; }
        .row { break-inside: avoid; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 3mm; margin-top: 3mm; }
        .box { height: 21mm; overflow: hidden; border: 1px solid #E39A6A; border-radius: 2mm; background: linear-gradient(#E39A6A, #E39A6A) 0 33% / 100% 1px no-repeat;
               display: flex; align-items: center; justify-content: center; font-family: 'Anek Bangla', sans-serif; font-size: 13mm; font-weight: 600; line-height: 1; }
        .trace { color: #13212C; opacity: .22; }
        .wordrow { break-inside: avoid; display: grid; grid-template-columns: 1fr 1fr 1.4fr; gap: 3mm; margin-top: 3mm; }
        .wordrow > div { height: 17mm; border-bottom: 1px solid #E39A6A; display: flex; align-items: flex-end; font-family: 'Anek Bangla', sans-serif; font-size: 10mm; font-weight: 600; }
        .match { break-inside: avoid; display: grid; grid-template-columns: 1fr 22mm 1fr; row-gap: 4mm; margin-top: 3mm; align-items: center; }
        .dot { width: 3mm; height: 3mm; border-radius: 50%; background: #13212C; display: inline-block; vertical-align: middle; }
        h2 { break-after: avoid; }
        @media screen and (max-width: 600px) { .box { height: 15vw; font-size: 9vw; } .wordrow > div { font-size: 7vw; } }
        @media print { .no-print { display: none !important; } .sheet { padding: 0; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="no-print mx-auto max-w-[194mm] p-4">
        <div class="rounded-2xl border-2 border-sky-deep bg-sky-tint p-4 font-sans">
            <p class="font-bold">ছাপুন বা "PDF হিসেবে রাখুন"। কাগজ: {{ $size === 'letter' ? 'US Letter' : 'A4' }}।
                <a href="?size={{ $size === 'letter' ? 'a4' : 'letter' }}" class="underline">{{ $size === 'letter' ? 'A4 মাপে দেখুন' : 'US Letter মাপে দেখুন' }}</a></p>
            <button type="button" data-print hidden class="mt-3 min-h-11 rounded-full bg-red px-5 font-bold text-white">ছাপুন / PDF হিসেবে রাখুন</button>
            <a href="{{ $back }}" class="ml-3 inline-flex min-h-11 items-center whitespace-nowrap underline">← ফিরে যান</a>
        </div>
    </div>
    <main class="sheet">
        @if (isset($teacher))
            <section class="page">
                <p class="font-display text-sm font-bold text-red">হাতপাখা · বাংলা শিখি · শিক্ষকের সেট</p>
                <h1 class="mt-1 font-display text-4xl font-extrabold">ধাপ {{ $N($teacher['n']) }}: {{ $teacher['title'] }}</h1>
                <p lang="en" class="text-lg">{{ $teacher['en'] }}</p>
                <p class="mt-4 font-serif text-lg">{{ $teacher['intro'] }}</p>
                <h2 class="mt-5 font-display text-xl font-extrabold">ক্লাসে কীভাবে</h2>
                <ol class="mt-2 list-decimal space-y-1 pl-6 font-serif text-lg">
                    <li>বোর্ডে নতুন অক্ষরটা বড় করে লিখুন, সবাই একসঙ্গে বলুক।</li>
                    <li>শব্দগুলো ছবি দেখিয়ে পড়ান: আগে অক্ষর ধরে, তারপর পুরো শব্দ।</li>
                    <li>ওয়ার্কশিট: প্রথম ঘর দেখে, পরের তিন ঘর ধূসরের ওপর, শেষ দুই ঘর নিজে।</li>
                    <li>ছবি আর শব্দ মেলানো@if (collect($teacher['lessons'])->contains(fn ($l) => count(array_filter((array) ($l['words'] ?? []), fn ($w) => $w[3] !== '')) >= 3 || isset($l['story']))। উত্তর শেষ পাতায়@endif।</li>
                </ol>
                <p class="mt-3 font-serif text-lg">{{ $teacher['tip']['bn'] }}</p>
                <p lang="en" class="mt-1 text-base">{{ $teacher['tip']['en'] }}</p>
                <h2 class="mt-5 font-display text-xl font-extrabold">এই সেটে</h2>
                <ol class="mt-2 list-decimal pl-6 text-lg">@foreach ($teacher['lessons'] as $l)<li>{{ $l['title'] }}</li>@endforeach</ol>
                <p class="mt-6 text-sm">পাঠগুলো ফোনে খেলা যায়: hatpakha.com/janun/learn-bengali/{{ $slug }}/</p>
            </section>
        @endif
        @foreach ($pages as [$unit, $n])
            @php
                $u = $C::unit($unit); $l = $C::lesson($unit, $n);
                $pics = array_values(array_filter((array) ($l['words'] ?? []), fn ($w) => $w[3] !== ''));
                $pics = array_slice($order($pics, 'p'.$unit.$n), 0, 4);
                if (count($pics) >= 3) { $answers[] = [$l['title'], collect($pics)->map(fn ($w) => $w[3].' → '.$w[0])->implode(', ')]; }
                if (isset($l['story'])) { $answers[] = [$l['title'], $l['story']['q'][0].' '.$l['story']['q'][1][$l['story']['q'][2]]]; }
            @endphp
            <section class="page">
                <header class="flex items-start justify-between gap-3 border-b-2 border-navy pb-2">
                    <div><p class="font-display text-sm font-bold text-red">হাতপাখা · বাংলা শিখি · ধাপ {{ $N($u['n']) }}, পাঠ {{ $N($n) }}</p>
                        <h1 class="font-display text-3xl font-extrabold">{{ $l['title'] }}</h1></div>
                    <p class="pt-1 text-right text-base">নাম: ________________<br>তারিখ: ____________</p>
                </header>
                @if (! empty($l['new']))
                    <h2 class="mt-4 font-display text-xl font-extrabold">লিখে শিখি <span class="text-base font-normal">(প্রথম ঘর দেখো, পরের তিন ঘর ধূসরের ওপর, শেষ দুই ঘর নিজে)</span></h2>
                    <p lang="en" class="text-xs">Write it: look at the first box, write over the grey in the next three, then write it alone.</p>
                    @foreach ($l['new'] as $x)
                        <div class="row" aria-label="{{ $glyph($x) }}">@for ($i = 0; $i < 6; $i++)<div class="box {{ $i > 0 && $i < 4 ? 'trace' : '' }}">@if ($i === 0 && isset($strokeMap[\App\Support\LearnCourse::nfc($glyph($x))]))<canvas width="240" height="240" style="width:21mm;height:21mm" data-stroke-glyph="{{ $glyph($x) }}" data-glyph-color="#13212C" data-strokes="{{ json_encode($strokeMap[\App\Support\LearnCourse::nfc($glyph($x))]) }}" aria-label="{{ $glyph($x) }}: লেখার ক্রম"></canvas>@else{{ $i < 4 ? $glyph($x) : '' }}@endif</div>@endfor</div>
                        @if (count($pics) < 3 && count($l['new']) <= 2)<div class="row" aria-label="{{ $glyph($x) }}: নিজে">@for ($i = 0; $i < 6; $i++)<div class="box {{ $i < 2 ? 'trace' : '' }}">{{ $i < 2 ? $glyph($x) : '' }}</div>@endfor</div>@endif
                    @endforeach
                @endif
                @if (! empty($l['words']))
                    <h2 class="mt-5 font-display text-xl font-extrabold">পড়ো আর লেখো <span lang="en" class="text-xs font-normal">Read it, write over it, write it alone</span></h2>
                    @foreach (array_slice($l['words'], 0, empty($l['new']) ? 5 : 3) as $w)
                        <div class="wordrow"><div>{{ $w[3] }} {{ $w[0] }}</div><div class="trace">{{ $w[0] }}</div><div></div></div>
                        <p lang="en" class="text-xs">{{ $w[1] }} · {{ $w[2] }}</p>
                    @endforeach
                @endif
                @if (count($pics) >= 3)
                    <h2 class="mt-5 font-display text-xl font-extrabold">ছবির সঙ্গে শব্দ মেলাও <span class="text-base font-normal">(দাগ টেনে)</span> <span lang="en" class="text-xs font-normal">Draw a line from each picture to its word</span></h2>
                    @php($right = $order($pics, 'w'.$unit.$n))
                    <div class="match">
                        @foreach ($pics as $k => $w)
                            <div class="text-[11mm] leading-none">{{ $w[3] }} <span class="dot"></span></div><div></div><div class="font-display text-[9mm] font-extrabold"><span class="dot"></span> {{ $right[$k][0] }}</div>
                        @endforeach
                    </div>
                @endif
                @if (! empty($l['sentences']))
                    <h2 class="mt-5 font-display text-xl font-extrabold">পড়ো আর দেখে লেখো <span lang="en" class="text-xs font-normal">Read and copy</span></h2>
                    @foreach ($l['sentences'] as $s)
                        <p class="mt-3 font-display text-[8mm] font-extrabold">{{ $s[0] }} <span lang="en" class="font-sans text-xs font-normal">{{ $s[2] }}</span></p>
                        <div class="h-[14mm] border-b border-peach-deep"></div>
                    @endforeach
                @endif
                @if (isset($l['story']))
                    <h2 class="mt-4 font-display text-xl font-extrabold">গল্পটা পড়ো <span lang="en" class="text-xs font-normal">Read the story, then tick the answer</span></h2>
                    <div class="mt-2 space-y-2 font-serif text-[6mm] leading-relaxed">@foreach (explode("\n", $l['story']['text']) as $p)<p>{{ $p }}</p>@endforeach</div>
                    <p class="mt-4 font-display text-xl font-extrabold">{{ $l['story']['q'][0] }}</p>
                    <ul class="mt-2 space-y-2 text-[6mm]">@foreach ($l['story']['q'][1] as $o)<li>☐ {{ $o }}</li>@endforeach</ul>
                @endif
                <p class="mt-6 border-t border-rule pt-2 text-xs">hatpakha.com/janun/learn-bengali/ · ISSN 2321-3035 · বিনা পয়সায় ছাপুন, বিলি করুন</p>
            </section>
        @endforeach
        @if (isset($teacher) && $answers)
            <section class="page">
                <h2 class="font-display text-2xl font-extrabold">উত্তর</h2>
                <ul class="mt-3 space-y-2 text-lg">@foreach ($answers as [$t, $a])<li><b>{{ $t }}:</b> {{ $a }}</li>@endforeach</ul>
            </section>
        @endif
    </main>
    @vite(['resources/js/learn.js'])
</body>
</html>
