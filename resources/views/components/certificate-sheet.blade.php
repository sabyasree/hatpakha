{{--
    The certificate itself (D71): A4 landscape at 1600×1131 px, inline styles only, so the same markup
    prints, shows on the check page and turns into a PNG (public/js/hp-card.js). Logo palette only.
    Real data only: the writer's name and title as they were when issued, the issue, the date, the code.
--}}
@props(['certificate'])
@php
    $c = $certificate;
    $contest = $c->kind === 'contest';
    $date = $c->post?->published_at ?? $c->issued_at;
    $navy = '#13212C'; $red = '#CB1B32'; $peach = '#FBEBD8'; $sky = '#2DAAE2';
    $shortUrl = preg_replace('~^https?://~', '', $c->verifyUrl());
@endphp
<div class="hp-card hp-cert-sheet" style="width:1600px;height:1131px;box-sizing:border-box;background:#FFFFFF;color:{{ $navy }};position:relative;overflow:hidden;font-family:'Anek Bangla',sans-serif;padding:48px">
    <div style="position:absolute;inset:0;background:{{ $peach }}"></div>
    <div style="position:absolute;top:28px;left:28px;right:28px;bottom:28px;border:6px solid {{ $navy }};border-radius:36px;background:#FFFFFF"></div>
    <div style="position:absolute;top:44px;left:44px;right:44px;bottom:44px;border:2px solid {{ $red }};border-radius:26px"></div>
    {{-- the fan, our motif, in two corners --}}
    @foreach ([['left:70px;top:70px', 'none'], ['right:70px;top:70px', 'scaleX(-1)']] as [$pos, $rot])
        <svg width="150" height="150" viewBox="0 0 100 100" style="position:absolute;{{ $pos }};transform:{{ $rot }};opacity:.9" aria-hidden="true">
            @foreach (range(0, 6) as $i)
                <path d="M10 90 L{{ 10 + 80 * cos(deg2rad(90 - $i * 15)) }} {{ 90 - 80 * sin(deg2rad(90 - $i * 15)) }}" stroke="{{ $i % 2 ? $sky : $red }}" stroke-width="5" stroke-linecap="round"/>
            @endforeach
        </svg>
    @endforeach

    <div style="position:relative;box-sizing:border-box;height:100%;display:flex;flex-direction:column;align-items:center;text-align:center;padding:40px 130px 36px">
        <img src="{{ asset('images/logo-wordmark.png') }}" alt="হাতপাখা" width="367" height="90" style="height:90px;width:auto">
        <div style="margin-top:6px;font-size:22px;letter-spacing:.08em;opacity:.75">বাংলা সাহিত্যের ওয়েব পত্রিকা · ২০১২ থেকে · ISSN 2321-3035</div>

        <div style="margin-top:40px;font-family:'Galada',cursive;font-size:92px;line-height:1.1;color:{{ $red }}">{{ $contest ? 'প্রতিযোগিতার সনদ' : 'প্রকাশনা সনদ' }}</div>

        <div style="margin-top:34px;font-size:30px">এই মর্মে জানানো হচ্ছে যে</div>
        <div style="margin-top:14px;font-size:76px;font-weight:800;line-height:1.15;max-width:1200px">{{ $c->author_name_snapshot }}</div>
        <div style="margin-top:18px;font-size:30px">-এর লেখা</div>
        <div style="margin-top:12px;font-family:'Noto Serif Bengali',serif;font-size:50px;font-weight:700;line-height:1.3;max-width:1150px">“{{ \Illuminate\Support\Str::limit($c->title_snapshot, 90) }}”</div>
        <div style="margin-top:22px;font-size:30px;line-height:1.5;max-width:1150px">
            @if ($contest)
                {{ $c->issue_label }} হিসেবে নির্বাচিত হয়েছে।
            @else
                হাতপাখায় @if ($c->issue_label)<strong>{{ $c->issue_label }}</strong>-এ @endif{{ \App\Support\Bn::date($date) }} প্রকাশিত হয়েছে।
            @endif
        </div>

        <div style="margin-top:auto;width:100%;display:flex;justify-content:space-between;align-items:flex-end;gap:40px">
            <div style="text-align:left;font-size:22px;line-height:1.6">
                <div style="opacity:.7">সনদ নম্বর</div>
                <div style="font-size:32px;font-weight:800;letter-spacing:.12em">{{ $c->code }}</div>
                <div style="opacity:.8">যাচাই করুন: {{ $shortUrl }}</div>
            </div>
            <div style="text-align:center;font-size:24px;min-width:380px">
                <div style="height:60px"></div>
                <div style="border-top:3px solid {{ $navy }};padding-top:10px;font-weight:700">সম্পাদক, হাতপাখা</div>
                <div style="opacity:.75">{{ \App\Support\Bn::date($c->issued_at) }}</div>
            </div>
        </div>
    </div>
</div>
