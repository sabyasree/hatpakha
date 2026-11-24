{{-- D277: the issue cover, drawn from App\Support\IssueCover::data() ($c). One cover for the magazine, the issue page,
     the lists and the desk; $fill: fill the parent's height (the magazine page). Styles in public/css/issue-cover.css. --}}
@once
    <link rel="stylesheet" href="{{ asset('css/issue-cover.css') }}?v=1">
@endonce
@php
    $tag = fn (array $l) => $l['serial'] ? '<span class="tag">ধারাবাহিক</span>' : '';
    $lead = $c['lines'][0] ?? null;
    $rest = array_slice($c['lines'], 1);
@endphp
<span class="hp-cv {{ $c['style'] }} k-{{ $c['kind'] }} {{ ($fill ?? false) ? 'fill' : '' }}" role="img" aria-label="হাতপাখা · {{ $c['title'] }}">
    @if ($c['style'] === 'fan')
        <span class="abs ear hide-sm"><span>{{ $c['ear'] }}</span><span>ISSN {{ \App\Support\IssueCover::bn(\App\Support\IssueCover::ISSN) }}</span></span>
        <span class="abs mast">হাতপাখা</span>
        <span class="abs fanart">{!! \App\Support\IssueCover::fanSvg($c['art'], $c['kind']) !!}</span>
        <span class="abs ttl"><span class="main serif ell" style="display:block;font-size:{{ $c['hero'] }}cqw">{{ $c['main'] }}</span><span class="lbl" style="display:block">{{ $c['sub'] }}</span></span>
        @if ($lead)
            <span class="abs lines hide-sm">
                <span class="lead serif"><span class="ell">{!! $tag($lead) !!}{{ $lead['t'] }}</span>@if ($lead['w'] !== '')<span class="w">{{ $lead['w'] }}</span>@endif</span>
                @foreach ($rest as $l)
                    <span class="sec">{!! $tag($l) !!}<span class="t ell">{{ $l['t'] }}</span>@if ($l['w'] !== '')<span style="flex:none">· {{ $l['w'] }}</span>@endif</span>
                @endforeach
            </span>
        @endif
        <span class="abs foot hide-sm">hatpakha.com</span>
    @else
        <span class="art {{ $c['art'] ? '' : 'none' }}">
            @if ($c['art'])<img src="{{ $c['art'] }}" alt="">@else<span class="fanbg">{!! \App\Support\IssueCover::fanSvg(null, $c['kind']) !!}</span>@endif
        </span>
        <span class="abs scrim"></span>
        <span class="abs ear hide-sm"><span>বর্ষ {{ $c['vol'] }}{{ $c['no'] ? ' · সংখ্যা '.$c['no'] : '' }}</span><span>ISSN {{ \App\Support\IssueCover::bn(\App\Support\IssueCover::ISSN) }}</span></span>
        <span class="abs mast">হাতপাখা</span>
        <span class="stamp hide-sm"><span class="m">{{ $c['month'] }}</span><span class="y">{{ $c['year'] }}</span><span class="v">বর্ষ {{ $c['vol'] }}</span></span>
        <span class="flag"><span class="kind" style="display:block">{{ $c['label'] }}</span><span class="main serif ell" style="display:block;font-size:{{ $c['hero'] }}cqw">{{ $c['main'] }}</span></span>
        <span class="abs panel hide-sm">
            <span class="ls">
                <span class="lead-rule" style="display:block"></span>
                @if ($lead)
                    <span class="lead serif" style="display:block"><span class="clamp2">{!! $tag($lead) !!}{{ $lead['t'] }}</span>@if ($lead['w'] !== '')<span class="w">{{ $lead['w'] }}</span>@endif</span>
                    @foreach ($rest as $l)
                        <span class="sec">{!! $tag($l) !!}<span class="t ell">{{ $l['t'] }}</span>@if ($l['w'] !== '')<span class="w">{{ $l['w'] }}</span>@endif</span>
                    @endforeach
                @else
                    <span class="lead serif" style="display:block">{{ $c['monthYear'] }}</span>
                @endif
            </span>
            <span class="bar">{!! \App\Support\IssueCover::barcodeSvg() !!}<small>ISSN {{ \App\Support\IssueCover::ISSN }}</small><span class="url">hatpakha.com</span></span>
        </span>
    @endif
</span>
