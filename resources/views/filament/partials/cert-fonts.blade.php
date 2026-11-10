{{-- The site's own fonts for certificate previews inside /desk (D73). --}}
@once
<style>
    @font-face { font-family: 'Anek Bangla'; font-weight: 400 800; font-display: swap; src: url('/fonts/anek-bangla-bengali.woff2') format('woff2'); unicode-range: U+0951-0952, U+0964-0965, U+0980-09FE, U+1CD0, U+1CD2, U+1CD5-1CD6, U+1CD8, U+1CE1, U+1CEA, U+1CED, U+1CF2, U+1CF5-1CF7, U+200C-200D, U+20B9, U+25CC, U+A8F1; }
    @font-face { font-family: 'Anek Bangla'; font-weight: 400 800; font-display: swap; src: url('/fonts/anek-bangla-latin.woff2') format('woff2'); }
    @font-face { font-family: 'Galada'; font-weight: 400; font-display: swap; src: url('/fonts/galada-bengali.woff2') format('woff2'); }
    @font-face { font-family: 'Noto Serif Bengali'; font-weight: 400 700; font-display: swap; src: url('/fonts/noto-serif-bengali-bengali.woff2') format('woff2'); }
    .hp-cert-fit { width: 100%; aspect-ratio: 1600 / 1131; position: relative; overflow: hidden; border-radius: .6rem; box-shadow: 0 1px 3px rgba(0,0,0,.15); }
    .hp-cert-fit > .hp-cert-sheet { position: absolute; top: 0; left: 0; transform-origin: top left; }
</style>
@endonce
