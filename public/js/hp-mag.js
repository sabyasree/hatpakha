/* D330: "হাতপাখায় এই শব্দ": a real line from a magazine piece that uses the answer, with a link to the piece.
   window.hpMagLine(base64 JSON {u, t, s}) → an element. Shown only after the answer, so it gives nothing away. */
window.hpMagLine = function (b64) {
    let m = null;
    try { m = JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(b64), (c) => c.charCodeAt(0)))); } catch (e) { m = null; }
    const box = document.createElement('a');
    if (!m || !m.u) return box;
    box.href = m.u;
    box.className = 'mt-1 block rounded-xl border-l-4 border-red bg-white px-3 py-2 text-base leading-snug text-navy';
    const k = document.createElement('span'); k.className = 'block text-sm font-bold text-red'; k.textContent = 'হাতপাখায় এই শব্দ';
    const s = document.createElement('span'); s.className = 'block font-serif'; s.textContent = '“' + m.s + '”';
    const t = document.createElement('span'); t.className = 'mt-0.5 block text-sm font-bold text-sky-night underline'; t.textContent = m.t + ' পড়ুন →';
    box.append(k, s, t);
    return box;
};
