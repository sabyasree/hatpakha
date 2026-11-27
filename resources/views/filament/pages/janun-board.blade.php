{{-- D309: what the জেনে রাখুন job is doing (read-only). Logo palette only. --}}
@php
    use App\Support\Bn;
    use App\Support\BanglaDate;
    use App\Support\FestivalPlan;
    $n = fn ($v) => Bn::num((string) $v);
    $label = ['planned' => __('পরিকল্পনায়'), 'drafted' => __('লেখা হয়েছে'), 'scheduled' => __('তারিখ ঠিক'), 'published' => __('প্রকাশিত'), 'held' => __('আটকে আছে')];
    $cls = ['planned' => 'hp-j-muted', 'drafted' => 'hp-j-peach', 'scheduled' => 'hp-j-sky', 'published' => 'hp-j-navy', 'held' => 'hp-j-red'];
    $sec = collect(\App\Support\JanunPlan::SECTIONS)->map(fn ($s) => $s[0]);
@endphp
<x-filament-panels::page>
    <style>
        .hp-j-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
        .hp-j-chip { font-weight: 700; padding: .35rem .8rem; border-radius: 999px; font-size: .9rem; }
        .hp-j-muted { background: rgba(127,127,127,.15); } .hp-j-peach { background: #FBEBD8; color: #13212C; } .hp-j-sky { background: #DDF1FA; color: #13212C; }
        .hp-j-navy { background: #13212C; color: #FFFBF4; } .hp-j-red { background: #CB1B32; color: #fff; }
        .hp-j-table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        .hp-j-table th, .hp-j-table td { text-align: left; padding: .55rem .5rem; border-bottom: 1px solid rgba(127,127,127,.2); vertical-align: top; }
        .hp-j-table thead th { font-size: .8rem; opacity: .75; }
        .hp-j-why { font-size: .82rem; opacity: .85; margin-top: .2rem; }
        .hp-j-box { border: 1px solid rgba(127,127,127,.25); border-radius: 1rem; padding: 1rem 1.1rem; }
        .hp-j-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); }
        .hp-j-wrap { overflow-x: auto; }
        a.hp-j-link { text-decoration: underline; font-weight: 700; }
    </style>

    <div class="hp-j-box">
        <p><b>{{ __('নিজে থেকে চলা কাজ') }}:</b> {{ $on ? __('চালু') : __('বন্ধ') }} · {{ __('সপ্তাহে তিনটি পাতা: সোম, বুধ, শুক্র সকাল ১০টা (ভারত)') }}</p>
        <div class="hp-j-chips" style="margin-top:.6rem">
            @foreach ($label as $k => $l)<span class="hp-j-chip {{ $cls[$k] }}">{{ $l }}: {{ $n($counts[$k] ?? 0) }}</span>@endforeach
        </div>
    </div>

    <h2 class="text-lg font-bold">{{ __('পাতাগুলো') }}</h2>
    <div class="hp-j-wrap">
        <table class="hp-j-table">
            <thead><tr><th>{{ __('পাতা') }}</th><th>{{ __('অবস্থা') }}</th><th>{{ __('প্রকাশ') }}</th></tr></thead>
            <tbody>
                @foreach ($pages as $p)
                    <tr>
                        <td>
                            @if ($p->status === 'published')<a class="hp-j-link" href="{{ $p->url() }}" target="_blank" rel="noopener">{{ $p->title }}</a>@else<b>{{ $p->title }}</b>@endif
                            <div class="hp-j-why">{{ $sec[$p->section] ?? $p->section }}@if ($why = \App\Filament\Pages\JanunBoard::reason($p)) · {{ $why }}@endif</div>
                        </td>
                        <td><span class="hp-j-chip {{ $cls[$p->status] ?? 'hp-j-muted' }}">{{ $label[$p->status] ?? $p->status }}</span></td>
                        <td>{{ $p->publish_at ? $p->publish_at->timezone('Asia/Kolkata')->format('j M, g:ia') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="text-lg font-bold">{{ __('পুজোর তিথি ও সময়') }}</h2>
    <div class="hp-j-grid">
        @foreach ($festivals as $f)
            <div class="hp-j-box">
                <b>{{ $f['name'] }}</b>
                @forelse ($f['years'] as $year => $rows)
                    @php($ok = $rows->where('status', 'verified'))
                    <p style="margin-top:.4rem">{{ $n($year) }}: {{ $n($ok->count()) }}/{{ $n($f['total']) }} {{ __('মিলেছে') }}
                        @if (FestivalPlan::yearRows($f['key'], $year)) · <a class="hp-j-link" href="{{ FestivalPlan::pageUrl($f['key'], $year) }}" target="_blank" rel="noopener">{{ __('পাতা') }}</a>@endif</p>
                    @foreach ($rows->where('status', 'held') as $t)<p class="hp-j-why">{{ $t->label }}: {{ __('আটকে') }} ({{ $t->note }})</p>@endforeach
                @empty
                    <p class="hp-j-why">{{ __('এখনও খোঁজা হয়নি') }}</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <h2 class="text-lg font-bold">{{ __('সরকারি ছুটির তালিকা') }}</h2>
    <div class="hp-j-grid">
        @forelse ($holidays as $hl)
            <div class="hp-j-box"><b>{{ $hl->name() }} {{ $n($hl->year) }}</b>: {{ $hl->isLive() ? __('প্রকাশিত') : ($hl->status === 'held' ? __('আটকে আছে') : __('প্রজ্ঞাপনের অপেক্ষায়')) }}
                <p class="hp-j-why">{{ $n($hl->holidays()->where('status', 'verified')->count()) }} {{ __('দিন মিলেছে') }}@if ($hl->note) · {{ $hl->note }}@endif @if ($hl->isLive()) · <a class="hp-j-link" href="{{ $hl->url() }}" target="_blank" rel="noopener">{{ __('পাতা') }}</a>@endif</p></div>
        @empty
            <p class="hp-j-why">{{ __('এখনও খোঁজা হয়নি') }}</p>
        @endforelse
    </div>

    <h2 class="text-lg font-bold">{{ __('আজ থেকে তিথি') }}</h2>
    <div class="hp-j-box">
        <p>{{ $n($tithi['ok']) }} {{ __('দিনের তিথি মিলেছে') }}@if ($tithi['last']) ({{ __('শেষ দিন') }} {{ \Carbon\Carbon::parse($tithi['last'])->format('j M Y') }})@endif</p>
        @foreach ($tithi['held'] as $h)<p class="hp-j-why">{{ $h->day->format('j M') }}: {{ __('আটকে') }} ({{ $h->note }})</p>@endforeach
    </div>

    <h2 class="text-lg font-bold">{{ __('বাংলা ক্যালেন্ডার (পশ্চিমবঙ্গ)') }}</h2>
    <div class="hp-j-grid">
        @foreach ($months as $year => $rows)
            <div class="hp-j-box">
                <b>{{ __('বঙ্গাব্দ') }} {{ $n($year) }}</b>: {{ $n($rows->where('status', 'verified')->count()) }}/{{ $n($rows->count()) }} {{ __('মাস মিলেছে') }}
                @foreach ($rows->where('status', '!=', 'verified') as $m)<p class="hp-j-why">{{ BanglaDate::MONTHS[$m->month - 1] }}: {{ $m->status === 'held' ? __('আটকে') : __('অপেক্ষায়') }}@if ($m->note) ({{ $m->note }})@endif</p>@endforeach
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
