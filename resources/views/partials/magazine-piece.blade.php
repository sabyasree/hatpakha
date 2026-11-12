{{-- One piece in the magazine, laid out by the page designer (App\Support\PageDesigner): feature, hero, vignette or none.
     body is XHTML from the sanitised body_html (D-sanitiser), pictures placed with their exact shape. --}}
@php
    $d = \App\Support\PageDesigner::design($p);
    $by = $p->authors->map->publicName()->implode(', ');
    $credit = $d['credit'] ? '<p class="mag-credit">'.e($d['credit']).'</p>' : '';
@endphp
@if ($d['layout'] === 'feature')
    {{-- A full-page picture opens the piece; the title sits on a navy panel --}}
    <section data-kind="feature" data-head="none" data-anchor="piece-{{ $p->id }}">
        <div class="mag-feat-in">
            {!! \App\Support\PageDesigner::img($d['hero'], 1, 'mag-feature-img') !!}
            <div class="mag-mark on-dark" style="top:3.5%;bottom:auto;text-shadow:0 1px 4px rgba(19,33,44,.7)"><span class="mag-brand">হাতপাখা</span><span>ISSN 2321-3035</span></div>
            <div class="mag-feature-panel">
                <p class="mag-kicker">{{ $sec }}</p>
                <h2 class="mag-h">{{ $p->title }}</h2>
                <p class="mag-feature-by">{{ $by }}</p>
                {!! $credit !!}
            </div>
        </div>
    </section>
@endif
<section data-kind="piece" class="{{ $p->isPoem() ? 'mag-poem' : '' }} mag-l-{{ $d['layout'] }}" data-section="{{ $sec }}" data-running="{{ $p->title }} · {{ $by }}" data-anchor="{{ $d['layout'] === 'feature' ? '' : 'piece-'.$p->id }}">
    @if ($d['layout'] === 'hero')
        <figure class="mag-hero">{!! \App\Support\PageDesigner::img($d['hero'], $p->isPoem() ? 0.26 : 0.34) !!}{!! $credit !!}</figure>
    @elseif ($d['layout'] === 'vignette')
        <figure class="mag-vignette">{!! \App\Support\PageDesigner::img($d['hero'], $p->isPoem() ? 0.3 : 0.36) !!}{!! $credit !!}</figure>
    @endif
    @if ($d['layout'] === 'feature')
        <p class="mag-kicker">{{ $sec }} · {{ $p->title }}</p>
        <p class="mag-by">{{ $by }}</p>
    @else
        <p class="mag-kicker">{{ $sec }}</p>
        <h2 class="mag-h">{{ $p->title }}</h2>
        <p class="mag-by">{{ $by }}</p>
    @endif
    {!! $d['body'] !!}
</section>
