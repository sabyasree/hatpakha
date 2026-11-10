{{-- One certificate, scaled to its box (D73). --}}
@include('filament.partials.cert-fonts')
<div class="hp-cert-fit" x-data x-init="const s = $el.firstElementChild, f = () => s.style.transform = 'scale(' + ($el.clientWidth / 1600) + ')'; f(); new ResizeObserver(f).observe($el)">
    <x-certificate-sheet :certificate="$certificate" :preview="true" />
</div>
