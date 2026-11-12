/* Graphic designer's cards → PNG in the browser (D51). The browser lays out the Bengali text with our
   own fonts; we draw the card into an SVG <foreignObject>, then onto a canvas. No library needed. */
(function () {
    if (window.hpCard) return;
    const cache = {};
    const asData = (url) => cache[url] ??= fetch(url, { credentials: 'same-origin' }).then((r) => r.blob()).then((b) => new Promise((ok) => {
        const fr = new FileReader(); fr.onload = () => ok(fr.result); fr.readAsDataURL(b);
    }));
    const FONTS = [
        ['Anek Bangla', '/fonts/anek-bangla-bengali.woff2', '400 800'], ['Anek Bangla', '/fonts/anek-bangla-latin.woff2', '400 800'],
        ['Galada', '/fonts/galada-bengali.woff2', '400'], ['Noto Serif Bengali', '/fonts/noto-serif-bengali-bengali.woff2', '400 700'],
    ];
    async function canvas(card) {
        const w = card.offsetWidth, h = card.offsetHeight;
        const clone = card.cloneNode(true);
        clone.style.transform = 'none';
        for (const img of clone.querySelectorAll('img')) {
            try { img.src = await asData(img.src); } catch (e) { img.remove(); }
        }
        const faces = (await Promise.all(FONTS.map(async ([f, u, wt]) => `@font-face{font-family:'${f}';font-weight:${wt};src:url(${await asData(u)}) format('woff2')}`))).join('');
        const html = new XMLSerializer().serializeToString(clone);
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><foreignObject width="100%" height="100%"><div xmlns="http://www.w3.org/1999/xhtml"><style>${faces}</style>${html}</div></foreignObject></svg>`;
        const img = new Image();
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        await img.decode();
        await new Promise((r) => setTimeout(r, 150)); // fonts inside the SVG settle
        const cv = document.createElement('canvas');
        cv.width = w; cv.height = h;
        cv.getContext('2d').drawImage(img, 0, 0, w, h);
        return cv;
    }
    async function download(card, name) {
        const a = document.createElement('a');
        a.download = name; a.href = (await canvas(card)).toDataURL('image/png'); a.click();
    }
    /** The card as a PNG file, for the phone's share sheet (D87). */
    async function file(card, name) {
        const c = await canvas(card);
        const blob = await new Promise((ok) => c.toBlob(ok, 'image/png'));
        return new File([blob], name, { type: 'image/png' });
    }
    /** The card as a JPEG on the paper colour, for Instagram (it takes JPEG only; owner, 2026-11-12). */
    async function jpeg(card, name) {
        const c = await canvas(card);
        const out = document.createElement('canvas');
        out.width = c.width; out.height = c.height;
        const g = out.getContext('2d');
        g.fillStyle = '#FFFBF4'; g.fillRect(0, 0, out.width, out.height);
        g.drawImage(c, 0, 0);
        const blob = await new Promise((ok) => out.toBlob(ok, 'image/jpeg', 0.92));
        return new File([blob], name, { type: 'image/jpeg' });
    }
    window.hpCard = { download, file, jpeg };
})();
