@props(['rays' => 16, 'colors' => ['#CB1B32', '#F08D90'], 'hub' => '#EFC08D', 'stroke' => null])
{{-- The hatpakha (hand-fan) motif from the logo: a half-sun of alternating rays. Decorative. --}}
@php
    $paths = [];
    for ($k = 0; $k < $rays; $k++) {
        $a1 = deg2rad(180 + 180 * $k / $rays);
        $a2 = deg2rad(180 + 180 * ($k + 1) / $rays);
        $p = fn ($a) => round(100 + 98 * cos($a), 2).' '.round(100 + 98 * sin($a), 2);
        $paths[] = ['d' => "M100 100 L{$p($a1)} A98 98 0 0 1 {$p($a2)} Z", 'c' => $colors[$k % count($colors)]];
    }
@endphp
<svg viewBox="0 0 200 100" aria-hidden="true" focusable="false" {{ $attributes }}>
    @foreach ($paths as $path)
        <path d="{{ $path['d'] }}" fill="{{ $path['c'] }}" @if ($stroke) stroke="{{ $stroke }}" stroke-width="0.5" @endif />
    @endforeach
    @if ($hub)<path d="M62 100 A38 38 0 0 1 138 100 Z" fill="{{ $hub }}" @if ($stroke) stroke="{{ $stroke }}" stroke-width="0.6" @endif />@endif
</svg>
