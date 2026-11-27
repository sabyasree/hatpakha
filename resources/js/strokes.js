/*
 * D325: stroke order for বাংলা শিখি. Shared by the desk page (draft, look, redraw), the worksheets and the lesson's
 * tracing screen, so a drawing looks the same everywhere. Strokes are lists of [x, y] points in a 0–100 box laid over the
 * letter as drawGlyph() draws it.
 */
export const GLYPH_FONT = (S, weight = 500) => `${weight} ${Math.round(S * 0.74)}px "Anek Bangla", sans-serif`;

export function drawGlyph(ctx, S, glyph, color, weight = 500) {
    ctx.save(); ctx.fillStyle = color; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.font = GLYPH_FONT(S, weight); ctx.fillText(glyph, S / 2, S * 0.56); ctx.restore();
}

/* numbered strokes with an arrow at the end and a dot where each begins */
export function drawStrokes(ctx, S, strokes, { color = '#CB1B32', width = 0.035, numbers = true } = {}) {
    const k = S / 100;
    ctx.save(); ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    strokes.forEach((st, i) => {
        if (!st || st.length < 2) return;
        ctx.strokeStyle = color; ctx.lineWidth = Math.max(1.5, S * width);
        ctx.beginPath(); ctx.moveTo(st[0][0] * k, st[0][1] * k); st.slice(1).forEach(([x, y]) => ctx.lineTo(x * k, y * k)); ctx.stroke();
        const [x1, y1] = st[st.length - 1]; const [x0, y0] = st[Math.max(0, st.length - 3)];
        const a = Math.atan2(y1 - y0, x1 - x0); const L = S * 0.075;
        ctx.fillStyle = color; ctx.beginPath(); ctx.moveTo(x1 * k, y1 * k);
        ctx.lineTo(x1 * k - L * Math.cos(a - 0.5), y1 * k - L * Math.sin(a - 0.5)); ctx.lineTo(x1 * k - L * Math.cos(a + 0.5), y1 * k - L * Math.sin(a + 0.5)); ctx.closePath(); ctx.fill();
        ctx.fillStyle = '#13212C'; ctx.beginPath(); ctx.arc(st[0][0] * k, st[0][1] * k, S * 0.06, 0, Math.PI * 2); ctx.fill();
        if (numbers) { ctx.fillStyle = '#FFFFFF'; ctx.font = `700 ${Math.round(S * 0.08)}px sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(String(i + 1), st[0][0] * k, st[0][1] * k + 0.5); }
    });
    ctx.restore();
}

/* ---- the machine draft: the letter's centre lines, split into strokes, in a usual writing order ---- */
export async function draftStrokes(glyph, S = 160) {
    try { await document.fonts.load(GLYPH_FONT(S), glyph); } catch (e) { /* fallback font */ }
    const c = document.createElement('canvas'); c.width = S; c.height = S; const ctx = c.getContext('2d', { willReadFrequently: true });
    drawGlyph(ctx, S, glyph, '#000');
    const d = ctx.getImageData(0, 0, S, S).data;
    const g = new Uint8Array(S * S); for (let i = 0; i < S * S; i++) g[i] = d[i * 4 + 3] > 110 ? 1 : 0;
    const dots = smallParts(g, S, S * S * 0.012);   // the dot under ড়, ঢ়, য়: a small separate blob
    thin(g, S);
    const raw = segments(g, S);
    // tiny side branches left by thinning go; a short piece on its own (the dot of ড়, য়) stays as a dot
    const ends = (s) => [s[0], s[s.length - 1]];
    const touches = (s) => raw.some((o) => o !== s && ends(o).some((e) => ends(s).some((f) => Math.hypot(e[0] - f[0], e[1] - f[1]) < 3)));
    const segs = raw.filter((s) => length(s) > S * 0.09 || (!touches(s) && length(s) > 2)).map((s) => (length(s) <= S * 0.09 ? [s[0], s[s.length - 1]] : s));
    if (!segs.length) return [];
    const top = Math.min(...segs.flatMap((s) => s.map((p) => p[1])));
    const isMatra = (s) => { const ys = s.map((p) => p[1]); const xs = s.map((p) => p[0]); return Math.max(...ys) - Math.min(...ys) < S * 0.06 && Math.max(...xs) - Math.min(...xs) > S * 0.22 && Math.min(...ys) < top + S * 0.08; };
    const matra = segs.filter(isMatra); let body = segs.filter((s) => !isMatra(s));
    body = merge(body, S, 0.04, 1.1);
    body = joinNearest(body, S, 4);
    body = body.map((s) => orient(s));
    body.sort((a, b) => (a[0][0] + a[0][1] * 0.6) - (b[0][0] + b[0][1] * 0.6));
    const out = body.concat(matra.map((s) => (s[0][0] <= s[s.length - 1][0] ? s : s.slice().reverse())), dots.map(([x, y]) => [[x, y - S * 0.015], [x, y + S * 0.015]]));
    return out.map((s) => simplify(s, S * 0.012).map(([x, y]) => [Math.round((x / S) * 1000) / 10, Math.round((y / S) * 1000) / 10])).filter((s) => s.length >= 2).slice(0, 10);
}

function smallParts(g, S, maxArea) {   // centres of small separate blobs
    const seen = new Uint8Array(S * S); const out = [];
    for (let i = 0; i < S * S; i++) {
        if (!g[i] || seen[i]) continue;
        const q = [i]; seen[i] = 1; let n = 0; let sx = 0; let sy = 0;
        while (q.length) {
            const j = q.pop(); n++; const x = j % S; const y = (j - x) / S; sx += x; sy += y;
            for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) { const a = x + dx; const b = y + dy; const k = b * S + a; if (a >= 0 && b >= 0 && a < S && b < S && g[k] && !seen[k]) { seen[k] = 1; q.push(k); } }
        }
        if (n < maxArea) out.push([sx / n, sy / n]);
    }
    return out;
}

function thin(g, S) {   // Zhang–Suen
    const at = (x, y) => (x < 0 || y < 0 || x >= S || y >= S ? 0 : g[y * S + x]);
    let changed = true;
    while (changed) {
        changed = false;
        for (const pass of [0, 1]) {
            const del = [];
            for (let y = 1; y < S - 1; y++) for (let x = 1; x < S - 1; x++) {
                if (!g[y * S + x]) continue;
                const p = [at(x, y - 1), at(x + 1, y - 1), at(x + 1, y), at(x + 1, y + 1), at(x, y + 1), at(x - 1, y + 1), at(x - 1, y), at(x - 1, y - 1)];
                const b = p.reduce((a, v) => a + v, 0); if (b < 2 || b > 6) continue;
                let a = 0; for (let i = 0; i < 8; i++) if (!p[i] && p[(i + 1) % 8]) a++; if (a !== 1) continue;
                if (pass === 0 ? (p[0] * p[2] * p[4] || p[2] * p[4] * p[6]) : (p[0] * p[2] * p[6] || p[0] * p[4] * p[6])) continue;
                del.push(y * S + x);
            }
            if (del.length) { changed = true; del.forEach((i) => { g[i] = 0; }); }
        }
    }
}

function segments(g, S) {
    const N = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [-1, -1], [1, -1], [-1, 1]];
    const nb = (x, y) => N.map(([dx, dy]) => [x + dx, y + dy]).filter(([a, b]) => a >= 0 && b >= 0 && a < S && b < S && g[b * S + a]);
    const node = (x, y) => nb(x, y).length !== 2;
    const seen = new Set(); const key = (x, y, a, b) => `${x},${y}>${a},${b}`; const out = [];
    const walk = (x, y, nx, ny) => {
        const path = [[x, y]]; let px = x; let py = y; let cx = nx; let cy = ny;
        seen.add(key(x, y, nx, ny)); seen.add(key(nx, ny, x, y));
        for (let n = 0; n < S * 4; n++) {
            path.push([cx, cy]);
            if (node(cx, cy)) break;
            const nxt = nb(cx, cy).find(([a, b]) => !(a === px && b === py));
            if (!nxt) break;
            seen.add(key(cx, cy, nxt[0], nxt[1])); seen.add(key(nxt[0], nxt[1], cx, cy));
            px = cx; py = cy; [cx, cy] = nxt;
        }
        return path;
    };
    for (let y = 0; y < S; y++) for (let x = 0; x < S; x++) {
        if (!g[y * S + x] || !node(x, y)) continue;
        for (const [a, b] of nb(x, y)) if (!seen.has(key(x, y, a, b))) out.push(walk(x, y, a, b));
    }
    for (let y = 0; y < S; y++) for (let x = 0; x < S; x++) {   // closed loops with no end (ও, ০)
        if (!g[y * S + x]) continue;
        for (const [a, b] of nb(x, y)) if (!seen.has(key(x, y, a, b))) out.push(walk(x, y, a, b));
    }
    return out;
}

const length = (s) => s.reduce((t, p, i) => (i ? t + Math.hypot(p[0] - s[i - 1][0], p[1] - s[i - 1][1]) : 0), 0);
const dir = (s, end) => { const a = end ? s[Math.max(0, s.length - 6)] : s[0]; const b = end ? s[s.length - 1] : s[Math.min(s.length - 1, 5)]; return Math.atan2(b[1] - a[1], b[0] - a[0]); };

/* join pieces that meet and carry on in nearly the same direction (a pen would not lift there) */
function merge(segs, S, gap = 0.03, maxTurn = 0.7) {
    segs = segs.slice(); let joined = true;
    while (joined) {
        joined = false;
        outer: for (let i = 0; i < segs.length; i++) for (let j = 0; j < segs.length; j++) {
            if (i === j) continue;
            for (const [ai, bj] of [[segs[i], segs[j]], [segs[i], segs[j].slice().reverse()]]) {
                const e = ai[ai.length - 1]; const s0 = bj[0];
                if (Math.hypot(e[0] - s0[0], e[1] - s0[1]) > S * gap) continue;
                let turn = Math.abs(dir(ai, true) - dir(bj, false)); if (turn > Math.PI) turn = 2 * Math.PI - turn;
                if (turn < maxTurn) { segs[i] = ai.concat(bj.slice(1)); segs.splice(j, 1); joined = true; break outer; }
            }
        }
    }
    return segs;
}

/* still too many pieces? join the two whose ends are nearest, as a pen would carry on */
function joinNearest(segs, S, max) {
    segs = segs.slice();
    while (segs.length > max) {
        let best = null;
        for (let i = 0; i < segs.length; i++) for (let j = 0; j < segs.length; j++) {
            if (i === j) continue;
            for (const a of [segs[i], segs[i].slice().reverse()]) for (const b of [segs[j], segs[j].slice().reverse()]) {
                const d = Math.hypot(a[a.length - 1][0] - b[0][0], a[a.length - 1][1] - b[0][1]);
                if (!best || d < best.d) best = { d, i, j, s: a.concat(b) };
            }
        }
        if (!best || best.d > S * 0.12) break;
        segs = segs.filter((_, k) => k !== best.i && k !== best.j); segs.push(best.s);
    }
    return segs;
}

/* a stroke usually begins at its top-left end */
const orient = (s) => { const a = s[0]; const b = s[s.length - 1]; return (a[0] + a[1]) <= (b[0] + b[1]) ? s : s.slice().reverse(); };

function simplify(pts, eps) {   // Ramer–Douglas–Peucker
    if (pts.length < 3) return pts;
    const [a, b] = [pts[0], pts[pts.length - 1]]; let idx = 0; let max = 0;
    for (let i = 1; i < pts.length - 1; i++) {
        const p = pts[i]; const dd = Math.abs((b[1] - a[1]) * p[0] - (b[0] - a[0]) * p[1] + b[0] * a[1] - b[1] * a[0]) / (Math.hypot(b[0] - a[0], b[1] - a[1]) || 1);
        if (dd > max) { max = dd; idx = i; }
    }
    return max > eps ? simplify(pts.slice(0, idx + 1), eps).slice(0, -1).concat(simplify(pts.slice(idx), eps)) : [a, b];
}

/* canvases on a page that ask for a drawing: <canvas data-stroke-glyph="ক" data-strokes="[[...]]"> */
export function paintStrokeCanvases(root = document) {
    root.querySelectorAll('canvas[data-stroke-glyph]').forEach(async (c) => {
        const S = c.width; const ctx = c.getContext('2d'); const glyph = c.dataset.strokeGlyph;
        try { await document.fonts.load(GLYPH_FONT(S), glyph); } catch (e) { /* ignore */ }
        ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, S, S);
        // the letter sits in the middle of its 0–100 box: zoom in so it fills the picture (stroke points move with it)
        const z = 1.3; ctx.translate(S / 2, S / 2); ctx.scale(z, z); ctx.translate(-S / 2, -S * 0.48);
        drawGlyph(ctx, S, glyph, c.dataset.glyphColor || '#E8DCCB');
        let strokes = []; try { strokes = JSON.parse(c.dataset.strokes || '[]'); } catch (e) { /* none */ }
        drawStrokes(ctx, S, strokes, { width: 0.03 });
        ctx.setTransform(1, 0, 0, 1, 0, 0);
    });
}
