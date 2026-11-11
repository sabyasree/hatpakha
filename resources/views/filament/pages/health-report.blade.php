{{-- Site health (owner, 2026-11-12: instead of an email). One verdict, what is waiting for a person, then each part. Logo palette only. --}}
@php
    use App\Support\Bn;
    use App\Support\HealthReport as H;
    $n = fn ($v) => Bn::num((string) $v);
    $verdict = [H::OK => ['✓', __('ঠিক আছে')], H::LOOK => ['!', __('একবার দেখুন')], H::URGENT => ['!!', __('এখনই দেখুন')]];
    $pct = fn ($a, $b) => $b > 0 ? (int) round(100 * $a / $b) : 0;
    $isWeek = $r['period'] === 'week';
    $label = $isWeek ? ($r['back'] === 0 ? __('এই সপ্তাহ') : ($r['back'] === 1 ? __('গত সপ্তাহ') : null)) : ($r['back'] === 0 ? __('এই মাস') : ($r['back'] === 1 ? __('গত মাস') : null));
@endphp
<x-filament-panels::page>
    <style>
        .hp-h-bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .hp-h-tab { min-height: 2.6rem; padding: 0 1rem; border-radius: 999px; border: 2px solid #13212C; font-weight: 700; background: transparent; cursor: pointer; }
        .hp-h-tab[aria-pressed="true"] { background: #13212C; color: #FFFBF4; }
        .dark .hp-h-tab { border-color: #EFC08D; } .dark .hp-h-tab[aria-pressed="true"] { background: #EFC08D; color: #13212C; }
        .hp-h-nav { min-height: 2.6rem; min-width: 2.6rem; border-radius: 999px; border: 1px solid rgba(127,127,127,.4); font-weight: 700; background: transparent; cursor: pointer; }
        .hp-h-nav:disabled { opacity: .35; cursor: default; }
        .hp-h-top { border-radius: 1rem; padding: 1.1rem 1.25rem; display: flex; gap: 1rem; align-items: center; }
        .hp-h-top b { font-size: 1.35rem; }
        .hp-ok { background: #13212C; color: #FFFBF4; } .hp-look { background: #EFC08D; color: #13212C; } .hp-urgent { background: #CB1B32; color: #fff; }
        .hp-h-mark { flex: none; width: 2.6rem; height: 2.6rem; border-radius: 999px; display: grid; place-items: center; font-weight: 800; background: rgba(255,255,255,.18); }
        .hp-h-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(19rem, 1fr)); }
        .hp-h-card { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; padding: 1rem 1.1rem; }
        .hp-h-card h3 { font-size: 1.1rem; font-weight: 800; display: flex; gap: .5rem; align-items: center; justify-content: space-between; }
        .hp-h-chip { font-size: .78rem; font-weight: 700; padding: .12rem .6rem; border-radius: 999px; white-space: nowrap; }
        .hp-h-big { font-size: 2rem; font-weight: 800; line-height: 1.1; margin-top: .4rem; font-variant-numeric: tabular-nums; }
        .hp-h-line { margin-top: .35rem; }
        .hp-muted { font-size: .85rem; opacity: .75; }
        .hp-up { font-weight: 800; } .hp-down { color: #CB1B32; font-weight: 800; }
        .hp-h-why { margin-top: .4rem; padding: .35rem .6rem; border-left: 4px solid #CB1B32; background: rgba(203,27,50,.07); font-weight: 700; font-size: .9rem; }
        .hp-h-split { display: flex; height: .6rem; border-radius: 999px; overflow: hidden; margin-top: .6rem; background: rgba(127,127,127,.2); }
        .hp-h-legend { display: flex; flex-wrap: wrap; gap: .3rem .9rem; font-size: .82rem; margin-top: .4rem; }
        .hp-h-legend i { display: inline-block; width: .7rem; height: .7rem; border-radius: 2px; margin-right: .25rem; vertical-align: -1px; }
        .hp-h-todo { display: grid; gap: .5rem; }
        .hp-h-todo a { display: flex; gap: .75rem; align-items: center; min-height: 3rem; padding: .5rem .9rem; border-radius: .8rem; border: 2px solid #CB1B32; font-weight: 700; }
        .hp-h-todo a:hover { background: rgba(203,27,50,.08); }
        .hp-h-todo span.n { min-width: 2.2rem; height: 2.2rem; border-radius: 999px; background: #CB1B32; color: #fff; display: grid; place-items: center; font-variant-numeric: tabular-nums; }
        .hp-h-card ol { margin-top: .4rem; padding-left: 1.2rem; list-style: decimal; }
        .hp-h-card ol li { margin-top: .2rem; }
        .hp-h-card a.link { color: #1A86C2; text-decoration: underline; font-weight: 700; }
    </style>

    {{-- Week / month, and which one --}}
    <div class="hp-h-bar" role="group" aria-label="{{ __('কোন সময়ের হিসাব') }}">
        <button type="button" class="hp-h-tab" aria-pressed="{{ $isWeek ? 'true' : 'false' }}" wire:click="show('week', 0)">{{ __('সাপ্তাহিক') }}</button>
        <button type="button" class="hp-h-tab" aria-pressed="{{ $isWeek ? 'false' : 'true' }}" wire:click="show('month', 0)">{{ __('মাসিক') }}</button>
        <span style="flex:1"></span>
        <button type="button" class="hp-h-nav" wire:click="show('{{ $r['period'] }}', {{ $r['back'] + 1 }})" @disabled($r['back'] >= 24) aria-label="{{ __('আগের') }}">‹</button>
        <span style="font-weight:700;min-width:10rem;text-align:center">
            @if ($label){{ $label }}<br>@endif
            <span class="hp-muted">{{ Bn::date($r['from']) }} – {{ Bn::date($r['to']) }}</span>
        </span>
        <button type="button" class="hp-h-nav" wire:click="show('{{ $r['period'] }}', {{ $r['back'] - 1 }})" @disabled($r['back'] <= 0) aria-label="{{ __('পরের') }}">›</button>
    </div>

    {{-- The verdict --}}
    <div class="hp-h-top hp-{{ $r['overall'] }}" role="status">
        <span class="hp-h-mark" aria-hidden="true">{{ $verdict[$r['overall']][0] }}</span>
        <div>
            <b>{{ $r['overall'] === H::OK ? __('সব ঠিক চলছে') : ($r['overall'] === H::LOOK ? __('মোটের ওপর ঠিক, কয়েকটি জিনিস একবার দেখুন') : __('কিছু জিনিস এখনই দেখা দরকার')) }}</b>
            <div>{{ __('পড়া হয়েছে') }} {{ $n($r['readers']['reads']) }} {{ __('বার') }}@if ($r['readers']['change'] !== null) ({{ $r['readers']['change'] >= 0 ? '+' : '−' }}{{ $n(abs($r['readers']['change'])) }}% {{ __('আগের তুলনায়') }})@endif · {{ __('প্রকাশ') }} {{ $n($r['publishing']['published']) }} · {{ __('নতুন জমা') }} {{ $n($r['publishing']['received']) }}</div>
        </div>
    </div>

    {{-- Waiting for a person --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('আপনার জন্য অপেক্ষায়') }}</x-slot>
        @if ($r['todo'])
            <div class="hp-h-todo">
                @foreach ($r['todo'] as $t)
                    <a href="{{ url($t['url']) }}"><span class="n">{{ $n($t['n']) }}</span><span style="flex:1">{{ __($t['label']) }}</span><span aria-hidden="true">→</span></a>
                @endforeach
            </div>
        @else
            <p>✓ {{ __('এখন কিছুই আপনার অপেক্ষায় নেই।') }}</p>
        @endif
    </x-filament::section>

    <div class="hp-h-grid">
        {{-- Readers --}}
        @php($rd = $r['readers'])
        <section class="hp-h-card">
            <h3>{{ __('পাঠক') }} <span class="hp-h-chip hp-{{ $rd['status'] }}">{{ $verdict[$rd['status']][1] }}</span></h3>
            @foreach ($rd['why'] as $why)<p class="hp-h-why">⚠ {{ $why }}</p>@endforeach
            <div class="hp-h-big">{{ $n($rd['reads']) }}</div>
            <div class="hp-muted">{{ __('বার লেখা পড়া হয়েছে') }}
                @if ($rd['change'] !== null)<br><span class="{{ $rd['change'] >= 0 ? 'hp-up' : 'hp-down' }}">{{ $rd['change'] >= 0 ? '▲' : '▼' }} {{ $isWeek ? __('আগের সপ্তাহের চেয়ে') : __('আগের মাসের চেয়ে') }} {{ $n(abs($rd['change'])) }}% {{ $rd['change'] >= 0 ? __('বেশি') : __('কম') }}</span> ({{ __('আগে') }} {{ $n($rd['before']) }})@endif</div>
            @php($tot = max(1, $rd['search'] + $rd['social'] + $rd['internal'] + $rd['other']))
            <div class="hp-h-split" aria-hidden="true">
                <span style="width:{{ $pct($rd['search'], $tot) }}%;background:#1A86C2"></span><span style="width:{{ $pct($rd['social'], $tot) }}%;background:#CB1B32"></span><span style="width:{{ $pct($rd['internal'], $tot) }}%;background:#13212C"></span><span style="width:{{ $pct($rd['other'], $tot) }}%;background:#EFC08D"></span>
            </div>
            <div class="hp-h-legend">
                <span><i style="background:#1A86C2"></i>{{ __('গুগল') }} {{ $n($pct($rd['search'], $tot)) }}%</span>
                <span><i style="background:#CB1B32"></i>{{ __('ফেসবুক ইত্যাদি') }} {{ $n($pct($rd['social'], $tot)) }}%</span>
                <span><i style="background:#13212C"></i>{{ __('সাইটের ভেতর থেকে') }} {{ $n($pct($rd['internal'], $tot)) }}%</span>
                <span><i style="background:#EFC08D"></i>{{ __('সরাসরি') }} {{ $n($pct($rd['other'], $tot)) }}%</span>
            </div>
            @if ($rd['places'])
                <p class="hp-h-line" style="font-weight:700">{{ __('কোথা থেকে পড়ছেন (শুরু থেকে মোট)') }}</p>
                <p class="hp-muted">@foreach ($rd['places'] as $pl){{ $pl['name'] }} {{ $n($pl['n']) }}@if (! $loop->last) · @endif @endforeach</p>
            @endif
            @if ($rd['top'])
                <p class="hp-h-line" style="font-weight:700">{{ __('সবচেয়ে বেশি পড়া') }}</p>
                <ol>@foreach ($rd['top'] as $t)<li><a class="link" href="{{ $t['post']->url() }}" target="_blank">{{ $t['post']->title }}</a> <span class="hp-muted">{{ $n($t['n']) }}</span></li>@endforeach</ol>
            @endif
        </section>

        {{-- Publishing --}}
        @php($pb = $r['publishing'])
        <section class="hp-h-card">
            <h3>{{ __('লেখা ও জমা') }} <span class="hp-h-chip hp-{{ $pb['status'] }}">{{ $verdict[$pb['status']][1] }}</span></h3>
            @foreach ($pb['why'] as $why)<p class="hp-h-why">⚠ {{ $why }}</p>@endforeach
            <div class="hp-h-big">{{ $n($pb['published']) }}</div>
            <div class="hp-muted">{{ __('টি লেখা প্রকাশ হয়েছে') }}</div>
            <p class="hp-h-line">{{ __('নতুন জমা') }}: <b>{{ $n($pb['received']) }}</b> · {{ __('মনোনীত') }} {{ $n($pb['approved']) }} · {{ __('বদল চাওয়া') }} {{ $n($pb['changes']) }} · {{ __('ফেরত') }} {{ $n($pb['rejected']) }}</p>
            <p class="hp-h-line">{{ __('এখন পড়ার অপেক্ষায়') }}: <b>{{ $n($pb['waiting']) }}</b>@if ($pb['waiting']) · {{ __('সবচেয়ে পুরোনোটি') }} {{ $n($pb['oldest_days']) }} {{ __('দিন ধরে') }}@endif</p>
            @if ($pb['oldest_days'] > 10)<p class="hp-muted hp-h-line">{{ __('লেখকেরা দুই সপ্তাহের মধ্যে উত্তর আশা করেন।') }}</p>@endif
        </section>

        {{-- Comments --}}
        @php($cm = $r['comments'])
        <section class="hp-h-card">
            <h3>{{ __('মন্তব্য') }} <span class="hp-h-chip hp-{{ $cm['status'] }}">{{ $verdict[$cm['status']][1] }}</span></h3>
            @foreach ($cm['why'] as $why)<p class="hp-h-why">⚠ {{ $why }}</p>@endforeach
            <div class="hp-h-big">{{ $n($cm['new']) }}</div>
            <div class="hp-muted">{{ __('টি নতুন মন্তব্য') }}</div>
            <p class="hp-h-line">{{ __('অনুমোদনের অপেক্ষায়') }}: <b>{{ $n($cm['pending']) }}</b> @if ($cm['pending'])<a class="link" href="{{ url('/desk/comments') }}">{{ __('দেখুন') }}</a>@endif</p>
        </section>

        {{-- Letters --}}
        @php($ml = $r['mail'])
        <section class="hp-h-card">
            <h3>{{ __('চিঠি') }} <span class="hp-h-chip hp-{{ $ml['status'] }}">{{ $verdict[$ml['status']][1] }}</span></h3>
            @foreach ($ml['why'] as $why)<p class="hp-h-why">⚠ {{ $why }}</p>@endforeach
            <div class="hp-h-big">{{ $n($ml['sent']) }}</div>
            <div class="hp-muted">{{ __('জনের কাছে পৌঁছেছে') }} ({{ $n($ml['letters']) }} {{ __('টি চিঠি') }})</div>
            <p class="hp-h-line">{{ __('যায়নি') }}: <b>{{ $n($ml['failed']) }}</b> · {{ __('ফেরত এসেছে') }} {{ $n($ml['bounced']) }} · {{ __('লিংকে ছুঁয়েছেন') }} {{ $n($ml['clicked']) }}</p>
            @if ($ml['failed'])<p class="hp-h-line"><a class="link" href="{{ url('/desk/newsletters') }}">{{ __('কেন যায়নি, দেখুন') }}</a></p>@endif
        </section>

        {{-- Phones --}}
        @php($ph = $r['phones'])
        <section class="hp-h-card">
            <h3>{{ __('ফোনে খবর') }} <span class="hp-h-chip hp-{{ $ph['status'] }}">{{ $verdict[$ph['status']][1] }}</span></h3>
            @foreach ($ph['why'] as $why)<p class="hp-h-why">⚠ {{ $why }}</p>@endforeach
            <div class="hp-h-big">{{ $n($ph['phones']) }}</div>
            <div class="hp-muted">{{ __('টি ফোন/কম্পিউটারে চালু') }}</div>
            <p class="hp-h-line">{{ __('পাঠানো') }} {{ $n($ph['sent']) }} · {{ __('ছুঁয়ে খুলেছেন') }} {{ $n($ph['opened']) }}@if ($ph['sent']) ({{ $n($pct($ph['opened'], $ph['sent'])) }}%)@endif</p>
        </section>

        {{-- AI team --}}
        @php($ai = $r['ai'])
        <section class="hp-h-card">
            <h3>{{ __('এআই দল') }} <span class="hp-h-chip hp-{{ $ai['status'] }}">{{ $verdict[$ai['status']][1] }}</span></h3>
            @foreach ($ai['why'] as $why)<p class="hp-h-why">⚠ {{ $why }}</p>@endforeach
            <div class="hp-h-big">${{ number_format($ai['cost'], 2) }}</div>
            <div class="hp-muted">{{ __('খরচ') }} ({{ __('আগে') }} ${{ number_format($ai['before'], 2) }})</div>
            <p class="hp-h-line">{{ __('কাজ শেষ') }} {{ $n($ai['done']) }} · {{ __('আটকে গেছে') }} <b>{{ $n($ai['failed']) }}</b> @if ($ai['failed'])<a class="link" href="{{ url('/desk/ai-team') }}">{{ __('দেখুন') }}</a>@endif</p>
        </section>

        {{-- The site --}}
        @php($st = $r['site'])
        <section class="hp-h-card">
            <h3>{{ __('সাইট') }} <span class="hp-h-chip hp-{{ $st['status'] }}">{{ $verdict[$st['status']][1] }}</span></h3>
            @foreach ($st['why'] as $why)<p class="hp-h-why">⚠ {{ $why }}</p>@endforeach
            <p class="hp-h-line">{{ __('ভাঙা লিংক বা হারানো ছবি') }}: <b>{{ $n($st['broken']) }}</b>@if ($st['checked_at']) <span class="hp-muted">({{ __('দেখা হয়েছে') }} {{ Bn::date($st['checked_at']) }})</span>@endif</p>
            <p class="hp-h-line">{{ __('পেছনের কাজে ভুল') }}: <b>{{ $n($st['queue_failed']) }}</b></p>
            <p class="hp-h-line">{{ __('শেষ ব্যাকআপ') }}: <b>{{ $st['backup_hours'] === null ? __('পাওয়া যায়নি') : ($st['backup_hours'] < 24 ? $n($st['backup_hours']).' '.__('ঘণ্টা আগে') : $n(intdiv($st['backup_hours'], 24)).' '.__('দিন আগে')) }}</b></p>
            <p class="hp-h-line">{{ __('মাসের পত্রিকা') }}: <b>{{ $st['issue_needs'] ? __('কাজ বাকি') : __('সব ঠিক') }}</b> @if ($st['issue_needs'])<a class="link" href="{{ url('/desk/ebooks') }}">{{ __('দেখুন') }}</a>@endif</p>

        </section>
    </div>
    <p class="hp-muted">{{ __('সব সংখ্যা সাইটের নিজের হিসাব থেকে; কোনো পাঠকের পরিচয় রাখা হয় না। এই পাতা কোথাও পাঠানো হয় না, শুধু এখানে দেখা যায়।') }}</p>
</x-filament-panels::page>
