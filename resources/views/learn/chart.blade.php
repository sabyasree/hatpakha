{{-- D321: wall charts to print: the alphabet (one word per letter, from the বর্ণমালা, D101) and the vowel signs --}}
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, follow">
    <title>{{ ['kar' => 'কারের চার্ট', 'juktakkhor' => 'যুক্তাক্ষরের তালিকা', 'alphabet-tracing' => 'বর্ণমালা লেখা অনুশীলন'][$chart] ?? 'বর্ণমালার চার্ট' }} · হাতপাখা</title>
    <link rel="canonical" href="{{ $seo->canonical }}">
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v=1">
    @vite(['resources/css/app.css'])
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        @page { size: {{ $size === 'letter' ? 'letter' : 'A4' }}; margin: 9mm; }
        html, body { background: #fff !important; color: #13212C; }
        .trow { break-inside: avoid; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 2mm; margin-top: 2mm; }
        .tbox { height: 19mm; border: 1px solid #E39A6A; border-radius: 2mm; display: flex; align-items: center; justify-content: center; overflow: hidden; font-family: 'Anek Bangla', sans-serif; font-size: 12mm; font-weight: 600; line-height: 1; background: linear-gradient(#E39A6A, #E39A6A) 0 33% / 100% 1px no-repeat; }
        .tbox.trace { color: #13212C; opacity: .22; }
        .cell { break-inside: avoid; border: 1px solid #E39A6A; border-radius: 2mm; padding: 2mm; text-align: center; }
        @media print { .no-print { display: none !important; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="no-print mx-auto max-w-[194mm] p-4"><div class="rounded-2xl border-2 border-sky-deep bg-sky-tint p-4">
        <button type="button" data-print hidden class="min-h-11 rounded-full bg-red px-5 font-bold text-white">ছাপুন / PDF হিসেবে রাখুন</button>
        <a href="{{ \App\Support\LearnCourse::url('worksheets') }}" class="ml-3 inline-flex min-h-11 items-center underline">← ওয়ার্কশিট</a></div></div>
    <main class="mx-auto max-w-[194mm] p-2">
        <p class="text-center font-display text-sm font-bold text-red">হাতপাখা · বাংলা শিখি</p>
        @if ($chart === 'alphabet-tracing')
            <h1 class="text-center font-display text-3xl font-extrabold">বর্ণমালা: লিখে শিখি</h1>
            <p class="text-center text-sm">প্রথম ঘর দেখো, পরের তিন ঘর ধূসরের ওপর, শেষ তিন ঘর নিজে। <span lang="en">Bengali alphabet tracing: look, trace the grey, then write alone.</span></p>
            @foreach (collect($letters)->whereIn(3, ['vowel', 'consonant'])->reject(fn ($l) => in_array($l[0], ['ং', 'ঃ', 'ঁ'], true)) as [$l, $w])
                <div class="trow" aria-label="{{ $l }}">@for ($i = 0; $i < 7; $i++)<div class="tbox {{ $i > 0 && $i < 4 ? 'trace' : '' }}">{{ $i < 4 ? $l : '' }}</div>@endfor</div>
            @endforeach
        @elseif ($chart === 'juktakkhor')
            <h1 class="text-center font-display text-4xl font-extrabold">যুক্তাক্ষরের তালিকা</h1>
            <p lang="en" class="text-center text-base">Bengali conjunct letters ({{ count($jukta['rows']) }}), with what they are made of and an example</p>
            <div class="mt-3 grid grid-cols-4 gap-2">
                @foreach ($jukta['rows'] as [$c, $parts, $w])
                    <div class="cell"><p class="font-display text-[11mm] font-extrabold leading-none text-red">{{ $c }}</p><p class="text-xs">{{ $parts }}</p><p class="font-display text-base font-extrabold">{{ $w[0] }}</p><p lang="en" class="text-[9px] leading-tight">{{ $w[1] }} · {{ $w[2] }}</p></div>
                @endforeach
            </div>
        @elseif ($chart === 'kar')
            <h1 class="text-center font-display text-4xl font-extrabold">কার: স্বরের চিহ্ন</h1>
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach ($kar['rows'] as [$v, $sign, $name, $roman, $where, $words])
                    <div class="cell"><p class="font-display text-[16mm] font-extrabold leading-none">ক{{ $sign }}</p><p class="font-display text-lg font-extrabold">{{ $v }} → {{ $name }}</p>
                        <p class="text-base">{{ $words[0][0] }} · {{ $words[1][0] }}</p></div>
                @endforeach
            </div>
            <p class="mt-4 text-center font-display text-2xl font-extrabold">ক কা কি কী কু কূ কৃ কে কৈ কো কৌ</p>
        @else
            <h1 class="text-center font-display text-4xl font-extrabold">বর্ণমালা</h1>
            <h2 class="mt-3 font-display text-xl font-extrabold">স্বরবর্ণ</h2>
            <div class="mt-2 grid grid-cols-6 gap-2">@foreach (collect($letters)->where(3, 'vowel') as [$l, $w])<div class="cell"><p class="font-display text-[12mm] font-extrabold leading-none">{{ $l }}</p><p class="text-sm">{{ $w }}</p></div>@endforeach</div>
            <h2 class="mt-3 font-display text-xl font-extrabold">ব্যঞ্জনবর্ণ</h2>
            <div class="mt-2 grid grid-cols-7 gap-2">@foreach (collect($letters)->where(3, 'consonant') as [$l, $w])<div class="cell"><p class="font-display text-[11mm] font-extrabold leading-none">{{ $l }}</p><p class="text-sm">{{ $w }}</p></div>@endforeach</div>
        @endif
        <p class="mt-4 text-center text-xs">hatpakha.com/janun/learn-bengali/ · ISSN 2321-3035</p>
    </main>
    @vite(['resources/js/learn.js'])
</body>
</html>
