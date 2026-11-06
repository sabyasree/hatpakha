{{--
    The certificate (D71, D73): A4 landscape at 1600×1131 px, inline styles only, so the same markup prints,
    shows on the check page and the desk, and turns into a PNG in the browser (public/js/hp-card.js).
    Four designs from the logo palette (white text only on red or navy). Real data only, drawn fresh each time:
    nothing is stored but the record and its code.
--}}
@props(['certificate', 'preview' => false])
@php
    $c = $certificate;
    $theme = $c->theme();
    $date = $c->post?->published_at ?? $c->issued_at ?? now();
    $signer = \App\Models\Certificate::signer();
    [$contestTitle, $place] = $theme === 'contest' ? $c->contestParts() : ['', ''];
    $navy = '#13212C'; $red = '#CB1B32'; $redDark = '#7A1022'; $peach = '#EFC08D'; $cream = '#FBEBD8'; $sky = '#2DAAE2'; $skyDeep = '#1A86C2'; $skyNight = '#12304A'; $skyTint = '#DDF1FA';
    $t = [
        'publication' => ['page' => $cream, 'card' => '#FFFFFF', 'frame' => $navy, 'rule' => $red, 'head' => $red, 'fan' => [$red, $sky]],
        'issue' => ['page' => $navy, 'card' => $cream, 'frame' => $peach, 'rule' => $red, 'head' => $navy, 'fan' => [$peach, $sky]],
        'contest' => ['page' => $red, 'card' => '#FFFFFF', 'frame' => $redDark, 'rule' => $peach, 'head' => $red, 'fan' => [$peach, $cream]],
        'honour' => ['page' => $skyTint, 'card' => '#FFFFFF', 'frame' => $skyNight, 'rule' => $sky, 'head' => $skyDeep, 'fan' => [$sky, $peach]],
    ][$theme];
    $heading = \App\Models\Certificate::THEMES[$theme][0];
    $shortUrl = $c->code ? preg_replace('~^https?://~', '', $c->verifyUrl()) : '';
    $logo = asset('images/logo-wordmark.png');
@endphp
<div class="hp-card hp-cert-sheet" style="width:1600px;height:1131px;box-sizing:border-box;background:{{ $t['page'] }};color:{{ $navy }};position:relative;overflow:hidden;font-family:'Anek Bangla',sans-serif">
    <div style="position:absolute;top:32px;left:32px;right:32px;bottom:32px;border:6px solid {{ $t['frame'] }};border-radius:36px;background:{{ $t['card'] }}"></div>
    <div style="position:absolute;top:48px;left:48px;right:48px;bottom:48px;border:2px solid {{ $t['rule'] }};border-radius:26px"></div>
    @foreach ([['left:74px;top:74px', 'none'], ['right:74px;top:74px', 'scaleX(-1)']] as [$pos, $flip])
        <svg width="140" height="140" viewBox="0 0 100 100" style="position:absolute;{{ $pos }};transform:{{ $flip }}" aria-hidden="true">
            @foreach (range(0, 6) as $i)
                <path d="M10 90 L{{ round(10 + 80 * cos(deg2rad(90 - $i * 15)), 2) }} {{ round(90 - 80 * sin(deg2rad(90 - $i * 15)), 2) }}" stroke="{{ $t['fan'][$i % 2] }}" stroke-width="5" stroke-linecap="round"/>
            @endforeach
        </svg>
    @endforeach

    @if ($theme === 'contest' && $place)
        {{-- the rosette: place in white on red --}}
        <div style="position:absolute;right:120px;top:250px;width:190px;height:190px;border-radius:50%;background:{{ $red }};border:8px solid {{ $peach }};box-sizing:border-box;display:flex;align-items:center;justify-content:center;text-align:center;color:#FFFFFF;font-weight:800;font-size:{{ mb_strlen($place) > 6 ? 30 : 40 }}px;line-height:1.1;padding:12px">{{ $place }}@if ($c->place && $c->place < 9)<br>স্থান@endif</div>
    @endif

    <div style="position:absolute;top:56px;left:150px;right:150px;bottom:56px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;text-align:center;padding-top:22px">
        <img src="{{ $logo }}" alt="হাতপাখা" width="367" height="90" style="height:86px;width:auto">
        <div style="margin-top:6px;font-size:21px;letter-spacing:.06em;opacity:.75">বাংলা সাহিত্যের ওয়েব পত্রিকা · ২০১২ থেকে · ISSN 2321-3035</div>

        <div style="margin-top:28px;font-family:'Galada',cursive;font-size:84px;line-height:1.1;color:{{ $t['head'] }}">{{ $heading }}</div>
        @if ($theme === 'issue')
            <div style="margin-top:8px;display:inline-block;padding:6px 26px;border-radius:999px;background:{{ $navy }};color:#FFFFFF;font-size:30px;font-weight:800">{{ $c->issueTitle() }}</div>
        @elseif ($theme === 'contest' && $contestTitle)
            <div style="margin-top:8px;display:inline-block;padding:6px 26px;border-radius:999px;background:{{ $red }};color:#FFFFFF;font-size:30px;font-weight:800">{{ $contestTitle }}</div>
        @endif

        @if ($theme === 'honour')
            <div style="margin-top:30px;font-size:30px">হাতপাখা সানন্দে সম্মান জানাচ্ছে</div>
            <div style="margin-top:12px;font-size:76px;font-weight:800;line-height:1.15;max-width:1150px">{{ $c->author_name_snapshot }}</div>
            <div style="margin-top:12px;font-size:30px">-কে</div>
            <div style="margin-top:10px;font-family:'Noto Serif Bengali',serif;font-size:50px;font-weight:700;line-height:1.3;color:{{ $skyNight }}">{{ $c->title_snapshot }}</div>
            <div style="margin-top:14px;font-size:30px">হিসেবে, {{ \App\Support\Bn::date($c->issued_at ?? now()) }}।</div>
        @else
            <div style="margin-top:{{ $theme === 'publication' ? 30 : 22 }}px;font-size:30px">এই মর্মে জানানো হচ্ছে যে</div>
            <div style="margin-top:10px;font-size:76px;font-weight:800;line-height:1.15;max-width:1150px">{{ $c->author_name_snapshot }}</div>
            <div style="margin-top:12px;font-size:30px">-এর লেখা</div>
            <div style="margin-top:8px;font-family:'Noto Serif Bengali',serif;font-size:48px;font-weight:700;line-height:1.3;max-width:1100px">“{{ \Illuminate\Support\Str::limit($c->title_snapshot, 90) }}”</div>
            <div style="margin-top:16px;font-size:30px;line-height:1.5;max-width:1100px">
                @if ($theme === 'contest')
                    @if ($c->place === 9 || $place === 'বিশেষ উল্লেখ') এই প্রতিযোগিতায় বিশেষ উল্লেখ পেয়েছে।
                    @elseif ($place) এই প্রতিযোগিতায় <strong>{{ $place }} স্থান</strong> অধিকার করেছে।
                    @else এই প্রতিযোগিতায় নির্বাচিত হয়েছে। @endif
                @elseif ($theme === 'issue')
                    হাতপাখার <strong>{{ $c->issueTitle() }}</strong>-এ প্রকাশিত হয়েছে, {{ \App\Support\Bn::date($date) }}।
                @else
                    হাতপাখায় প্রকাশিত হয়েছে, {{ \App\Support\Bn::date($date) }}।
                @endif
            </div>
        @endif

        <div style="margin-top:auto;width:100%;display:flex;justify-content:space-between;align-items:flex-end;gap:40px;padding-bottom:6px">
            <div style="display:flex;gap:18px;align-items:flex-end;text-align:left;font-size:20px;line-height:1.5">
                @if ($c->code)
                    <div style="background:#FFFFFF;padding:8px;border-radius:10px;border:2px solid {{ $navy }};line-height:0">{!! $c->qrSvg(118) !!}</div>
                @else
                    <div style="width:136px;height:136px;border:2px dashed {{ $navy }};border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;opacity:.6">QR</div>
                @endif
                <div>
                    <div style="opacity:.7">শংসাপত্র নম্বর</div>
                    <div style="font-size:30px;font-weight:800;letter-spacing:.12em">{{ $c->code ?: ($preview ? 'নমুনা' : '') }}</div>
                    <div style="opacity:.8">{{ $shortUrl ? 'যাচাই: '.$shortUrl : 'কোড স্ক্যান করে যাচাই করুন' }}</div>
                </div>
            </div>
            <div style="text-align:center;font-size:22px;min-width:360px">
                <div style="height:78px;display:flex;align-items:flex-end;justify-content:center">
                    @if ($signer['image'])<img src="{{ asset($signer['image']) }}" alt="" style="max-height:78px;max-width:320px">@endif
                </div>
                <div style="border-top:3px solid {{ $navy }};padding-top:8px;font-weight:700">{{ $signer['name'] ?? $signer['title'] }}</div>
                @if ($signer['name'])<div>{{ $signer['title'] }}</div>@endif
                <div style="opacity:.75">{{ \App\Support\Bn::date($c->issued_at ?? now()) }}</div>
            </div>
        </div>
    </div>
</div>
