/*
 * D321: বাংলা শিখি on the phone. One file for the children's map, the lesson player, the parents' page and the certificate.
 * Progress lives in this browser only (localStorage, wrapped so the pages still work without it). Nothing is sent anywhere.
 * No sound is needed; a recording plays only where the desk has uploaded one (owner: human recordings, D321).
 */

import { drawGlyph, drawStrokes, draftStrokes, paintStrokeCanvases } from './strokes.js';

const KEY = 'hp-learn-v1';
const FACES = ['🐟', '🐦', '🦚', '🐘', '🦉', '🐯'];
const BN = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
const VOWEL_OF = { 'া': 'আ', 'ি': 'ই', 'ী': 'ঈ', 'ু': 'উ', 'ূ': 'ঊ', 'ৃ': 'ঋ', 'ে': 'এ', 'ৈ': 'ঐ', 'ো': 'ও', 'ৌ': 'ঔ' };
const CONSONANTS = ['ক', 'গ', 'ত', 'দ', 'ন', 'প', 'ব', 'ম', 'র', 'ল', 'স', 'হ', 'চ', 'জ', 'ট', 'ড'];

/* ---------- storage ---------- */
const store = {
    read() {
        try { return Object.assign({ profiles: [], current: null, data: {}, settings: { roman: true, en: true } }, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) { return { profiles: [], current: null, data: {}, settings: { roman: true, en: true } }; }
    },
    write(s) { try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) { /* private window: progress just isn't kept */ } },
};
let S = store.read();
const save = () => store.write(S);
// D322: instructions in English or Bengali. A child who is learning to read cannot read Bengali instructions either, so the
// default follows the device's language; parents switch it in the grown-ups' sheet or on the map. ?ui=en|bn sets it.
try { const q = new URLSearchParams(location.search).get('ui'); if (q === 'en' || q === 'bn') { S.settings.ui = q; store.write(S); } } catch (e) { /* ignore */ }
if (!S.settings.ui) S.settings.ui = (navigator.language || '').toLowerCase().startsWith('bn') ? 'bn' : 'en';
const EN = () => S.settings.ui === 'en';
const T = (bn, en) => (EN() ? en : bn);
const NUM = (n) => (EN() ? String(n) : BN(n));
/* elements written in Bengali on the server carry their English in data-en */
const swapUi = () => document.querySelectorAll('[data-en], [data-en-label], [data-en-placeholder]').forEach((el) => {
    if (el.dataset.en !== undefined) { if (el.dataset.bn === undefined) el.dataset.bn = el.textContent; el.textContent = EN() ? el.dataset.en : el.dataset.bn; }
    if (el.dataset.enPlaceholder) { if (el.dataset.bnPlaceholder === undefined) el.dataset.bnPlaceholder = el.getAttribute('placeholder') || ''; el.setAttribute('placeholder', EN() ? el.dataset.enPlaceholder : el.dataset.bnPlaceholder); }
    if (el.dataset.enLabel) { if (el.dataset.bnLabel === undefined) el.dataset.bnLabel = el.getAttribute('aria-label') || ''; el.setAttribute('aria-label', EN() ? el.dataset.enLabel : el.dataset.bnLabel); }
});
const me = () => {
    if (!S.profiles.length) { S.profiles.push({ id: 'p' + Date.now(), name: '', face: '🐟' }); S.current = S.profiles[0].id; save(); }
    return S.profiles.find((p) => p.id === S.current) || S.profiles[0];
};
const mine = () => { const id = me().id; S.data[id] = Object.assign({ done: {}, missed: [], week: {}, stickers: [], last: null }, S.data[id] || {}); return S.data[id]; };
const weekKey = () => { const d = new Date(); const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())); const day = t.getUTCDay() || 7; t.setUTCDate(t.getUTCDate() + 4 - day); const y = new Date(Date.UTC(t.getUTCFullYear(), 0, 1)); return t.getUTCFullYear() + '-' + Math.ceil(((t - y) / 864e5 + 1) / 7); };

/* ---------- small DOM helpers ---------- */
const h = (tag, attrs = {}, ...kids) => {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
        if (k === 'class') e.className = v + (attrs.tabindex === '-1' ? ' outline-none' : ''); else if (k.startsWith('on')) e.addEventListener(k.slice(2), v); else if (v !== false && v != null) e.setAttribute(k, v === true ? '' : v);
    }
    kids.flat().forEach((k) => k != null && e.append(k.nodeType ? k : document.createTextNode(String(k))));
    return e;
};
const shuffle = (a) => { a = a.slice(); for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; };
const pick = (a, n) => shuffle(a).slice(0, n);
const BTN = 'min-h-14 rounded-full px-6 font-display text-xl font-extrabold';
const PRIMARY = BTN + ' bg-red text-white shadow-[3px_3px_0_#13212C] active:translate-y-0.5';
const OUTLINE = BTN + ' border-2 border-navy bg-paper';

/* ---------- the lesson player ---------- */
function lesson(root) {
    const D = JSON.parse(document.getElementById('learn-data').textContent);
    const stage = root.querySelector('[data-stage]');
    const actions = root.querySelector('[data-actions]');
    const bar = root.querySelector('[data-progress-bar]');
    const started = Date.now();
    const put = (...n) => stage.append(...n.filter((x) => x != null && x !== false));
    const play = (text) => { const u = D.audio && D.audio[text]; if (u) { try { new Audio(u).play().catch(() => {}); } catch (e) { /* no sound */ } } };
    const speaker = (text) => (D.audio && D.audio[text]) ? h('button', { type: 'button', class: OUTLINE + ' text-2xl', 'aria-label': T('শোনো', 'Listen'), onclick: () => play(text) }, '🔊') : null;
    const extra = (r, e) => h('p', { class: 'mt-3 text-lg', lang: 'en' }, S.settings.roman && r ? h('span', { class: 'font-bold' }, r) : null, S.settings.roman && r && S.settings.en && e ? ' · ' : null, S.settings.en && e ? e : null);
    let score = { asked: 0, first: 0 };
    let steps = [];
    let at = 0;
    let render = () => {};

    const show = (i) => {
        at = i;
        bar.style.width = Math.round((i / steps.length) * 100) + '%';
        bar.parentElement.setAttribute('aria-valuenow', String(Math.round((i / steps.length) * 100)));
        stage.replaceChildren(); actions.replaceChildren();
        render = () => { stage.replaceChildren(); actions.replaceChildren(); steps[i](); };
        steps[i]();
        stage.querySelector('h1, h2, [data-focus]')?.focus?.({ preventScroll: true });
        window.scrollTo(0, 0);
    };
    const next = () => (at + 1 < steps.length ? show(at + 1) : null);
    const nextBtn = (label = T('পরের →', 'Next →')) => h('button', { type: 'button', class: PRIMARY, onclick: next, 'data-next': true }, label);

    /* meet a new letter, sign or joined letter */
    const meet = (item) => () => {
        const label = { letter: T('নতুন অক্ষর', 'New letter'), sign: T('নতুন চিহ্ন', 'New sign'), phola: T('নতুন চিহ্ন', 'New sign'), joined: T('নতুন যুক্তাক্ষর', 'New joined letter') }[item.kind];
        let big = item.t; let join = null;
        if (item.kind === 'sign') {
            if (VOWEL_OF[item.t]) { big = 'ক' + item.t; join = ['ক', VOWEL_OF[item.t], big]; } else { big = item.t === 'ঁ' ? 'কাঁ' : 'ক' + item.t; }
        } else if (item.kind === 'phola') {
            big = item.t === 'র্' ? 'র্ক' : 'ক' + item.t; join = item.t === 'র্' ? ['র', 'ক', big] : ['ক', item.t.replace('্', ''), big];
        } else if (item.kind === 'joined') {
            join = [item.parts.join(' + '), null, item.t];
        }
        const joinRow = join ? h('p', { class: 'mt-4 font-display text-4xl font-extrabold', 'data-join': true },
            h('span', {}, join[1] ? join[0] + ' + ' + join[1] : join[0]), h('span', { class: 'opacity-0 transition-opacity duration-700', 'data-join-to': true }, ' = ' + join[2])) : null;
        const card = item.ex ? h('button', { type: 'button', class: 'mt-6 flex w-full max-w-sm items-center gap-4 rounded-3xl border-2 border-navy bg-white p-4 text-left', onclick: (e) => { e.currentTarget.querySelector('[data-more]')?.classList.remove('hidden'); play(item.ex.w); } },
            h('span', { class: item.ex.p ? 'text-6xl' : 'font-display text-5xl font-extrabold text-red', 'aria-hidden': 'true' }, item.ex.p || big), h('span', {}, h('span', { class: 'block font-display text-5xl font-extrabold' }, item.ex.w),
                h('span', { class: 'hidden text-lg', 'data-more': true, lang: 'en' }, [S.settings.roman ? item.ex.r : '', S.settings.en ? item.ex.e : ''].filter(Boolean).join(' · ') || T('ছুঁয়ে দেখো', 'Tap to see')))) : null;
        put(h('p', { class: 'rounded-full bg-sky-tint px-4 py-1 font-bold text-sky-night' }, label),
            h('h1', { class: 'mt-4 font-display text-[clamp(7rem,40vw,11rem)] font-extrabold leading-none', tabindex: '-1' }, big),
            S.settings.roman && item.sound ? h('p', { class: 'mt-2 text-xl', lang: 'en' }, T('বলো: ', 'Say: '), h('b', {}, item.sound)) : null,
            EN() && item.hint ? h('p', { class: 'mt-1 max-w-md text-base', lang: 'en' }, item.hint) : null, joinRow, card);
        if (joinRow) setTimeout(() => joinRow.querySelector('[data-join-to]')?.classList.remove('opacity-0'), 600);
        actions.append(...[speaker(item.t), nextBtn()].filter(Boolean));
    };

    /* a word, read letter by letter, then whole */
    const word = (w) => () => {
        const tiles = h('div', { class: 'mt-4 flex flex-wrap justify-center gap-2', 'aria-hidden': 'true' }, w.tiles.map((t) => h('span', { class: 'min-w-12 rounded-xl border-2 border-navy bg-white px-2 py-1 font-display text-3xl font-extrabold transition-colors' }, t)));
        const more = h('div', { class: 'hidden' }, extra(w.r, w.e));
        const blend = () => {
            [...tiles.children].forEach((t, i) => setTimeout(() => { t.classList.add('bg-pink'); setTimeout(() => t.classList.remove('bg-pink'), 450); }, i * 500));
            setTimeout(() => more.classList.remove('hidden'), w.tiles.length * 500);
            play(w.w);
        };
        put(w.p ? h('p', { class: 'text-8xl', 'aria-hidden': 'true' }, w.p) : null,
            h('h2', { class: 'mt-2 font-display text-7xl font-extrabold', tabindex: '-1' }, w.w), tiles, more,
            h('button', { type: 'button', class: OUTLINE + ' mt-5', onclick: blend }, T('অক্ষর ধরে পড়ি', 'Read it letter by letter')));
        actions.append(...[speaker(w.w), nextBtn()].filter(Boolean));
        setTimeout(blend, 400);
    };

    const sentence = (s) => () => {
        put(s.p ? h('p', { class: 'text-7xl', 'aria-hidden': 'true' }, s.p) : null, h('h2', { class: 'mt-3 font-display text-5xl font-extrabold leading-snug', tabindex: '-1' }, s.w), extra(s.r, s.e));
        actions.append(...[speaker(s.w), nextBtn()].filter(Boolean));
    };

    /* trace with a finger over a grey letter */
    const trace = (glyph) => () => {
        const size = Math.min(320, Math.round(window.innerWidth * 0.8));
        const guide = h('canvas', { width: size * 2, height: size * 2, class: 'absolute inset-0 h-full w-full', 'aria-hidden': 'true' });
        const order = h('canvas', { width: size * 2, height: size * 2, class: 'absolute inset-0 h-full w-full opacity-70', 'aria-hidden': 'true' });
        const ink = h('canvas', { width: size * 2, height: size * 2, class: 'absolute inset-0 h-full w-full touch-none', role: 'img', 'aria-label': glyph + T(' লেখো', ' — write it') });
        const box = h('div', { class: 'relative mt-4 rounded-3xl border-2 border-navy bg-white', style: `width:${size}px;height:${size}px` }, guide, order, ink);
        const msg = h('p', { class: 'mt-3 min-h-8 text-xl font-bold', role: 'status' }, '');
        const g = guide.getContext('2d'); const k = ink.getContext('2d');
        const strokes = (D.strokes || {})[glyph];   // D325: only drawings the desk approved
        const drawGuide = () => {
            g.clearRect(0, 0, guide.width, guide.height); drawGlyph(g, guide.width, glyph, '#E8DCCB', 800);
            g.strokeStyle = '#E39A6A'; g.setLineDash([12, 12]); g.lineWidth = 3; g.beginPath(); g.moveTo(0, guide.height * 0.3); g.lineTo(guide.width, guide.height * 0.3); g.stroke();
            if (strokes) drawStrokes(order.getContext('2d'), order.width, strokes, { color: '#F08D90' });
        };
        (document.fonts ? document.fonts.load('800 120px "Anek Bangla"', glyph) : Promise.resolve()).then(drawGuide, drawGuide);
        let down = false; let last = null; let done = false; let tries = 0;
        const pos = (e) => { const r = ink.getBoundingClientRect(); return [(e.clientX - r.left) * 2, (e.clientY - r.top) * 2]; };
        ink.addEventListener('pointerdown', (e) => { down = true; last = pos(e); ink.setPointerCapture(e.pointerId); });
        ink.addEventListener('pointermove', (e) => {
            if (!down) return; const p = pos(e);
            k.strokeStyle = '#13212C'; k.lineWidth = size * 0.11; k.lineCap = 'round'; k.lineJoin = 'round'; k.beginPath(); k.moveTo(last[0], last[1]); k.lineTo(p[0], p[1]); k.stroke(); last = p;
        });
        const check = () => {
            down = false; if (done) return;
            const a = g.getImageData(0, 0, guide.width, guide.height).data; const b = k.getImageData(0, 0, ink.width, ink.height).data;
            let want = 0; let got = 0; let stray = 0;
            for (let i = 0; i < a.length; i += 16) { const inGlyph = a[i] === 0xE8 && a[i + 3] > 0; if (inGlyph) { want++; if (b[i + 3] > 0) got++; } else if (b[i + 3] > 0) stray++; }
            if (want && got / want > 0.45 && stray < want * 1.5) { done = true; msg.textContent = T('দারুণ! ✓', 'Well done! ✓'); actions.querySelector('[data-next]')?.classList.remove('opacity-50'); }
            else if (got) { tries++; msg.textContent = tries >= 3 ? T('বাবা-মা: আঙুল ধরে একবার ধূসর অক্ষরের ওপর দিয়ে টেনে দেখান। চাইলে "পরের" টিপে এগোনো যায়।', 'Grown-ups: guide the finger over the grey letter once. You can also tap "Next" to move on.') : T('আরও একটু, ধূসর অক্ষরের ওপর দিয়ে।', 'A little more, over the grey letter.'); }
        };
        ink.addEventListener('pointerup', check); ink.addEventListener('pointercancel', check);
        put(h('h2', { class: 'font-display text-3xl font-extrabold', tabindex: '-1' }, T('আঙুল দিয়ে লেখো: ', 'Write it with your finger: '), glyph),
            strokes ? h('p', { class: 'text-base' }, T('কালো ফোঁটা থেকে শুরু করো, নম্বর ধরে।', 'Start at the dark dot, follow the numbers.')) : null, box, msg);
        actions.append(h('button', { type: 'button', class: OUTLINE, onclick: () => { k.clearRect(0, 0, ink.width, ink.height); done = false; msg.textContent = ''; } }, T('মুছি', 'Clear')),
            h('button', { type: 'button', class: PRIMARY + ' opacity-50', onclick: next, 'data-next': true }, T('পরের →', 'Next →')));
    };

    /* the same picture before every prompt of a kind, so a child who can't read yet learns what each screen wants */
    const CUE = { word: '🖼️ ➜ 🔤', pic: '🔤 ➜ 🖼️', find: '🔍', gap: '🧩', odd: '👀', split: '✂️', build: '🧩', story: '❓' };
    const P = (p) => (Array.isArray(p) ? T(p[0], p[1]) : p);
    const rightFirst = new Set();
    const counter = () => { let done = false; return () => { if (!done) { done = true; score.asked++; } }; };

    /* a question with choices; a wrong answer shows the right one, and the question comes back once at the end */
    const ask = (q, again = false) => {
        const count = counter();
        return () => {
            if (!again) count();
            let wrong = false;
            const opts = q.allowSame ? q.options : [...new Set(q.options)];
            const long = opts.some((o) => String(o).length > 6);
            const msg = h('p', { class: 'mt-4 min-h-8 text-xl font-bold', role: 'status' }, '');
            const grid = h('div', { class: `mt-5 grid w-full max-w-md gap-3 ${q.allowSame ? 'grid-cols-2' : long || opts.length < 3 ? (opts.length < 3 && !long ? 'grid-cols-2' : 'grid-cols-1') : 'grid-cols-1 sm:grid-cols-3'}` });
            const reveal = () => { const r = grid.querySelector('[data-answer]'); if (r) { r.classList.add('ring-4', 'ring-sky-deep'); } };
            opts.forEach((o, i) => grid.append(h('button', {
                type: 'button', class: `min-h-20 rounded-2xl border-2 border-navy bg-white px-3 py-2 ${q.big === false || long ? 'text-2xl' : 'text-4xl'} font-display font-extrabold`, 'data-answer': o === q.answer && (!q.allowSame || true) ? 'yes' : null,
                'aria-label': q.allowSame ? `${o}, ${NUM(i + 1)}/${NUM(opts.length)}` : (q.labels && q.labels[o] ? `${o} (${q.labels[o]})` : null),
                onclick: (e) => {
                    const b = e.currentTarget;
                    if (o === q.answer) {
                        b.classList.add('bg-sky-light', 'border-sky-deep'); b.append(' ✓'); msg.textContent = wrong ? T('এবার ঠিক!', 'Right this time!') : T('ঠিক!', 'Right!');
                        if (!wrong && !again) { score.first++; if (q.word) rightFirst.add(q.word); }
                        grid.querySelectorAll('button').forEach((x) => { x.disabled = true; });
                        setTimeout(next, 750);
                    } else {
                        if (!wrong && q.word) { const m = mine(); if (!m.missed.includes(q.word)) { m.missed.push(q.word); m.missed = m.missed.slice(-30); save(); } }
                        wrong = true; b.classList.add('animate-[hp-shake_0.35s]', 'border-red', 'text-ink2'); b.append(' ✗'); b.disabled = true;
                        msg.textContent = T('এটা নয়। ঠিকটা নীল দাগে।', 'Not that one. The right one is marked in blue.');
                        setTimeout(reveal, 500);
                        if (!again && !q.requeued) { q.requeued = true; steps.splice(steps.length - 1, 0, ask(q, true)); }
                    }
                },
            }, o, q.sub && q.sub[o] && S.settings.en ? h('span', { class: 'block font-sans text-base font-normal', lang: 'en' }, q.sub[o]) : null)));
            put(h('p', { class: 'text-3xl', 'aria-hidden': 'true' }, CUE[q.cue] || ''), h('h2', { class: 'font-display text-2xl font-extrabold', tabindex: '-1' }, P(q.prompt)),
                q.promptEn && S.settings.en && !EN() ? h('p', { class: 'text-base', lang: 'en' }, q.promptEn) : null,
                q.show ? h('div', { class: `mt-4 ${q.showBig ? 'text-8xl' : 'font-display text-6xl font-extrabold'}` }, q.show) : null, grid, msg);
        };
    };

    /* build a word (or a sentence) from its tiles; after two wrong tiles the right one is shown */
    const build = (target, tiles, prompt, show, again = false) => {
        const count = counter();
        return () => {
            if (!again) count();
            let i = 0; let wrong = 0;
            const slots = h('div', { class: 'mt-4 flex flex-wrap justify-center gap-2' }, tiles.map(() => h('span', { class: 'flex min-h-16 min-w-14 items-center justify-center rounded-xl border-2 border-dashed border-navy bg-paper px-2 font-display text-3xl font-extrabold' })));
            const msg = h('p', { class: 'mt-3 min-h-8 text-xl font-bold', role: 'status' }, '');
            const pool = h('div', { class: 'mt-5 flex flex-wrap justify-center gap-3' });
            const hint = () => { pool.querySelectorAll('button').forEach((x) => x.classList.remove('ring-4', 'ring-sky-deep')); const r = [...pool.querySelectorAll('button:not([disabled])')].find((x) => x.dataset.t === tiles[i]); if (r) r.classList.add('ring-4', 'ring-sky-deep'); };
            shuffle(tiles.map((t, k) => [t, k])).forEach(([t]) => pool.append(h('button', {
                type: 'button', 'data-t': t, class: 'min-h-16 min-w-16 rounded-2xl border-2 border-navy bg-white px-3 font-display text-3xl font-extrabold',
                onclick: (e) => {
                    const b = e.currentTarget;
                    if (t === tiles[i]) {
                        slots.children[i].textContent = t; slots.children[i].classList.replace('border-dashed', 'border-solid'); b.disabled = true; b.classList.add('opacity-30'); b.classList.remove('ring-4', 'ring-sky-deep'); i++;
                        if (wrong >= 2 && i < tiles.length) hint();
                        if (i === tiles.length) { msg.textContent = T('ঠিক! ', 'Right! ') + target; if (!wrong && !again) { score.first++; rightFirst.add(target); } setTimeout(next, 900); }
                    } else {
                        if (!wrong && !again) { const m = mine(); if (!m.missed.includes(target)) { m.missed.push(target); m.missed = m.missed.slice(-30); save(); } }
                        wrong++; b.classList.add('animate-[hp-shake_0.35s]'); setTimeout(() => b.classList.remove('animate-[hp-shake_0.35s]'), 400); msg.textContent = T('আগে অন্যটা।', 'Another one comes first.');
                        if (wrong >= 2) hint();
                    }
                },
            }, t)));
            put(h('p', { class: 'text-3xl', 'aria-hidden': 'true' }, CUE.build), h('h2', { class: 'font-display text-2xl font-extrabold', tabindex: '-1' }, P(prompt)), show ? h('p', { class: 'mt-3 text-7xl', 'aria-hidden': 'true' }, show) : null, slots, pool, msg);
        };
    };

    let firstWordStep = 0;
    const readAloud = (items) => () => {
        put(h('p', { class: 'text-3xl', 'aria-hidden': 'true' }, '🗣️'), h('h2', { class: 'font-display text-2xl font-extrabold', tabindex: '-1' }, T('জোরে পড়ো', 'Read it aloud')),
            h('div', { class: 'mt-5 space-y-4' }, items.map((t) => h('p', { class: `font-display ${t.length > 14 ? 'text-4xl' : 'text-6xl'} font-extrabold leading-snug` }, t))),
            h('p', { class: 'mt-6 text-base text-ink2' }, T('বাবা-মা: শিশু পড়তে পারলে ✓ টিপুন। না পারলে "আবার দেখি": শব্দগুলো আবার আসবে।', 'Grown-ups: tap ✓ when your child has read it. If not, "Look again" shows the words once more.')));
        actions.append(h('button', { type: 'button', class: OUTLINE, onclick: () => show(firstWordStep) }, T('আবার দেখি', 'Look again')), h('button', { type: 'button', class: PRIMARY, onclick: () => { score.read = true; next(); } }, T('পড়তে পেরেছে ✓', 'Read it ✓')));
    };

    const story = (st) => () => {
        stage.classList.replace('justify-center', 'justify-start');
        const en = st.en ? h('details', { class: 'mt-4 w-full rounded-2xl border-2 border-navy bg-white p-3 text-left', lang: 'en', ...(EN() ? { open: true } : {}) },
            h('summary', { class: 'min-h-11 cursor-pointer font-bold' }, T('ইংরেজিতে (বড়দের জন্য)', 'In English (for grown-ups)')), h('div', { class: 'mt-2 space-y-2 text-lg' }, st.en.split('\n').map((p) => h('p', {}, p)))) : null;
        put(h('p', { class: 'text-7xl', 'aria-hidden': 'true' }, st.picture || '📖'), h('h1', { class: 'mt-2 font-display text-4xl font-extrabold', tabindex: '-1' }, D.title),
            h('div', { class: 'mt-4 w-full space-y-3 text-left font-serif text-2xl leading-loose' }, st.text.split('\n').map((p) => h('p', {}, p))), S.settings.en ? en : null);
        actions.append(nextBtn(T('পড়া হয়েছে →', 'Finished reading →')));
    };

    const finish = () => {
        stage.classList.replace('justify-start', 'justify-center');
        const ratio = score.asked ? score.first / score.asked : 1;
        const stars = ratio >= 0.8 ? 3 : ratio >= 0.5 ? 2 : 1;
        const m = mine();
        m.done[D.id] = Math.max(m.done[D.id] || 0, stars); m.last = D.id;
        const wk = weekKey(); m.week[wk] = (m.week[wk] || 0) + Math.max(1, Math.round((Date.now() - started) / 60000));
        const sticker = D.sticker && !m.stickers.includes(D.unit) ? D.sticker : null;
        if (D.sticker && !m.stickers.includes(D.unit)) m.stickers.push(D.unit);
        m.missed = m.missed.filter((x) => !rightFirst.has(x));   // only what was right first time leaves the review list
        save();
        bar.style.width = '100%';
        put(h('p', { class: 'text-7xl tracking-widest motion-safe:animate-bounce', 'aria-label': NUM(stars) + T('টি তারা', ' stars') }, '⭐'.repeat(stars) + '☆'.repeat(3 - stars)),
            h('h1', { class: 'mt-3 font-display text-5xl font-extrabold', tabindex: '-1' }, stars === 3 ? T('দারুণ!', 'Brilliant!') : stars === 2 ? T('খুব ভালো!', 'Very good!') : T('শেষ করেছ!', 'Done!')),
            h('p', { class: 'mt-2 text-xl' }, me().name ? me().name + ', ' : '', T('পাঠ ', 'Lesson '), NUM(D.n), T(' শেষ।', ' done.')),
            score.read ? h('p', { class: 'mt-1 text-lg' }, T('জোরে পড়েছ ✓', 'You read it aloud ✓')) : null,
            sticker ? h('div', { class: 'mt-6 rounded-3xl border-2 border-navy bg-white p-4' }, h('p', { class: 'text-7xl' }, sticker[0]), h('p', { class: 'mt-1 font-display text-2xl font-extrabold' }, T('নতুন স্টিকার: ', 'New sticker: '), sticker[1])) : null);
        actions.classList.add('flex-wrap');
        actions.append(h('a', { href: D.urls.next, class: PRIMARY + ' flex items-center' }, D.urls.nextId ? T('পরের পাঠ →', 'Next lesson →') : T('ম্যাপে ফিরি', 'Back to the map')),
            h('a', { href: D.urls.sheet, class: OUTLINE + ' flex items-center' }, T('ওয়ার্কশিট', 'Worksheet')),
            D.urls.certificate ? h('a', { href: D.urls.certificate, class: OUTLINE + ' flex items-center' }, T('শংসাপত্র', 'Certificate')) : h('a', { href: D.urls.map, class: OUTLINE + ' flex items-center' }, T('ম্যাপ', 'Map')));
    };

    /* ---- the questions this lesson can ask (each word once; wrong choices never look like each other) ---- */
    const all = [...new Map(D.words.concat(D.pool).map((w) => [w.w, w])).values()];
    const withPic = (list) => list.filter((w) => w.p);
    const others = (w, list, n) => {
        const out = []; const seenW = new Set([w.w]); const seenP = new Set([w.p]);
        for (const x of shuffle(list)) { if (out.length >= n) break; if (seenW.has(x.w) || (x.p && seenP.has(x.p))) continue; out.push(x); seenW.add(x.w); if (x.p) seenP.add(x.p); }
        return out;
    };
    const picQ = (w, kind, cue) => {
        const opts = others(w, withPic(all), 2);
        if (!opts.length) return null;
        return kind === 'word'
            ? ask({ cue: 'word', prompt: cue || ['কোনটা?', 'Which one?'], show: w.p, showBig: true, options: shuffle([w.w, ...opts.map((o) => o.w)]), answer: w.w, word: w.w })
            : ask({ cue: 'pic', prompt: ['কোন ছবি?', 'Which picture?'], show: w.w, options: shuffle([w.p, ...opts.map((o) => o.p)]), answer: w.p, word: w.w, labels: Object.fromEntries([w, ...opts].map((o) => [o.p, o.e])) });
    };
    const qs = [];
    withPic(D.words).forEach((w, i) => { const q = picQ(w, i % 2 ? 'pic' : 'word'); if (q) qs.push(q); });
    D.new.filter((x) => x.kind === 'letter' || x.kind === 'joined').forEach((x) => {
        const has = D.words.filter((w) => w.w.includes(x.t)); const not = all.filter((w) => !w.w.includes(x.t));
        if (has.length && not.length >= 1) { const w = pick(has, 1)[0]; qs.push(ask({ cue: 'find', prompt: [`কোন শব্দে "${x.t}" আছে?`, `Which word has "${x.t}"?`], options: shuffle([w.w, ...pick(not, 2).map((o) => o.w)]), answer: w.w, word: w.w })); }
    });
    D.new.filter((x) => x.kind === 'sign' && VOWEL_OF[x.t]).forEach((x) => {
        const w = pick(D.words.filter((w) => w.tiles.some((t) => t.endsWith(x.t))), 1)[0];
        if (!w) return;
        const k = w.tiles.findIndex((t) => t.endsWith(x.t)); const base = w.tiles[k].slice(0, -1);
        const opts = [base + x.t, ...pick(D.signs.filter((s) => s !== x.t), 2).map((s) => base + s)];
        if (D.signs.length < 2) opts.push(base);
        const gap = w.tiles.map((t, j) => (j === k ? '_' : t)).join('');
        qs.push(ask({ cue: 'gap', prompt: ['ফাঁকে কোনটা বসবে?', 'Which one goes in the gap?'], show: h('span', {}, w.p ? h('span', { class: 'block text-7xl' }, w.p) : null, gap), options: shuffle(opts), answer: base + x.t, word: w.w }));
    });
    D.new.filter((x) => x.kind === 'joined' && x.parts.length === 2).forEach((x) => {
        const right = x.parts.join(' + ');
        const wrong = [...new Set(pick(CONSONANTS.filter((c) => !x.parts.includes(c)), 2).map((c, i) => (i ? x.parts[0] + ' + ' + c : c + ' + ' + x.parts[1])))];
        qs.push(ask({ cue: 'split', prompt: [`${x.t} কোন দুটো অক্ষর?`, `Which two letters make ${x.t}?`], show: x.t, options: shuffle([right, ...wrong]), answer: right, big: false }));
    });
    (D.pairs || []).forEach(([a, b]) => qs.push(ask({ cue: 'odd', prompt: ['কোনটা আলাদা?', 'Which one is different?'], options: shuffle([a, a, a, b]), answer: b, allowSame: true })));
    pick(D.words.filter((w) => w.tiles.length >= 2), 2).forEach((w) => qs.push(build(w.w, w.tiles, ['অক্ষর সাজিয়ে শব্দ বানাও', 'Build the word'], w.p || null)));
    D.sentences.filter((s) => s.p).forEach((s) => { const opts = others(s, withPic(D.sentences.concat(all)), 2); if (opts.length) qs.push(ask({ cue: 'pic', prompt: ['কোন ছবি?', 'Which picture?'], show: s.w, options: shuffle([s.p, ...opts.map((o) => o.p)]), answer: s.p })); });
    pick(D.sentences, 2).forEach((s) => { const ws = s.w.replace(/[।?!]$/u, '').split(' '); if (ws.length >= 2) qs.push(build(s.w, ws, ['শব্দ সাজিয়ে বাক্য বানাও', 'Build the sentence'], s.p || null)); });
    /* too few? practise words already learnt (stories, short lessons) */
    for (const w of shuffle(withPic(D.pool))) { if (qs.length >= 5) break; const q = picQ(w, qs.length % 2 ? 'pic' : 'word'); if (q) qs.push(q); }
    const review = mine().missed.map((w) => all.find((x) => x.w === w)).filter((w) => w && w.p && !D.words.some((x) => x.w === w.w)).slice(-2)
        .map((w) => picQ(w, 'word', ['মনে আছে? কোনটা?', 'Remember? Which one?'])).filter(Boolean);

    /* ---- the order of the lesson ---- */
    D.new.forEach((x) => steps.push(meet(x)));
    firstWordStep = steps.length;
    D.words.forEach((w) => steps.push(word(w)));
    D.sentences.forEach((s) => steps.push(sentence(s)));
    const traceable = D.new.find((x) => x.kind !== 'phola' && !['ং', 'ঃ', 'ঁ'].includes(x.t));
    if (traceable) steps.push(trace(traceable.kind === 'sign' ? 'ক' + traceable.t : traceable.t));
    if (D.story) steps.push(story(D.story));
    steps.push(...review, ...shuffle(qs).slice(0, 7));
    if (D.story) {
        const st = D.story; const order = shuffle(st.q[1].map((_, i) => i));
        steps.push(ask({ cue: 'story', prompt: st.q_en ? [st.q[0], st.q_en[0]] : st.q[0], promptEn: st.q_en ? st.q_en[0] : null, options: order.map((i) => st.q[1][i]), answer: st.q[1][st.q[2]], big: false,
            sub: st.q_en ? Object.fromEntries(st.q[1].map((o, i) => [o, st.q_en[1][i]])) : null }));
    }
    const toRead = D.story ? [D.story.text.split(/(?<=।)\s*/u)[0]] : D.sentences.length ? D.sentences.slice(0, 2).map((s) => s.w) : D.words.slice(0, 3).map((w) => w.w);
    if (toRead.length) steps.push(readAloud(toRead));
    steps.push(finish);

    /* parents' sheet: tips, how to say the new things, settings */
    const dlg = document.querySelector('[data-parent]');
    const sounds = dlg.querySelector('[data-parent-sounds]');
    if (D.new.length) sounds.append(h('p', { class: 'font-bold' }, T('এই পাঠে নতুন:', 'New in this lesson:')), h('ul', { class: 'mt-1 space-y-1', lang: 'en' }, D.new.map((x) => h('li', {}, h('span', { class: 'font-display text-2xl font-extrabold', lang: 'bn' }, x.kind === 'phola' ? (x.t === 'র্' ? 'র্ক' : 'ক' + x.t) : x.kind === 'sign' ? (x.t === 'ঁ' ? 'কাঁ' : 'ক' + x.t) : x.t), ' — ', x.sound || x.parts.join(' + '), x.hint ? h('span', { class: 'block text-sm' }, x.hint) : null))));
    document.querySelector('[data-parent-open]').addEventListener('click', () => dlg.showModal());
    dlg.querySelector('[data-parent-x]')?.addEventListener('click', () => dlg.close());
    /* a small sum keeps children on the lesson; the parents' page has ads and a sign-up (plan §6) */
    dlg.querySelectorAll('[data-gate]').forEach((a) => a.addEventListener('click', (e) => {
        const x = 3 + Math.floor(Math.random() * 5); const y = 2 + Math.floor(Math.random() * 6);
        const ans = window.prompt(T(`বড়দের জন্য: ${BN(x)} + ${BN(y)} = ?`, `For grown-ups: ${x} + ${y} = ?`));
        if (ans === null || parseInt(String(ans).replace(/[০-৯]/g, (d) => '০১২৩৪৫৬৭৮৯'.indexOf(d)), 10) !== x + y) e.preventDefault();
    }));
    dlg.querySelector('[data-parent-close]').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    dlg.querySelectorAll('[data-setting]').forEach((c) => {
        c.checked = !!S.settings[c.dataset.setting];
        c.addEventListener('change', () => { S.settings[c.dataset.setting] = c.checked; save(); render(); });
    });
    dlg.querySelectorAll('[data-ui]').forEach((r) => {
        r.checked = r.value === S.settings.ui;
        r.addEventListener('change', () => { S.settings.ui = r.value; save(); swapUi(); render(); });
    });
    swapUi();
    show(0);
}

/* ---------- the children's map ---------- */
function map(root) {
    const D = JSON.parse(document.getElementById('learn-data').textContent);
    const paint = () => {
        const p = me(); const m = mine();
        root.querySelector('[data-who-name]').textContent = p.name || T('কে শিখছে?', 'Who is learning?');
        root.querySelector('[data-who-face]').textContent = p.face || '🐟';
        let done = 0; let stars = 0; let nextLink = null;
        root.querySelectorAll('[data-lesson]').forEach((a) => {
            const s = m.done[a.dataset.lesson] || 0;
            a.querySelector('[data-lesson-stars]').textContent = s ? '⭐'.repeat(Math.min(3, s)) : '';
            const num = a.querySelector('[data-num]'); if (num) num.textContent = NUM(a.dataset.lesson.split('/')[1]);
            a.classList.toggle('bg-sky-tint', s > 0); a.classList.toggle('bg-white', !s);
            a.classList.remove('ring-4', 'ring-red');
            if (s) { done++; stars += s; } else if (!nextLink) nextLink = a;
        });
        if (nextLink) nextLink.classList.add('ring-4', 'ring-red');
        root.querySelectorAll('[data-unit]').forEach((u) => {
            const st = u.querySelector('[data-sticker]'); const got = m.stickers.includes(u.dataset.unit);
            st.classList.toggle('grayscale-[85%]', !got); st.classList.toggle('opacity-60', !got);
        });
        const book = root.querySelector('[data-sticker-book]');
        if (book) book.replaceChildren(...D.units.filter((u) => m.stickers.includes(u.slug)).map((u) => h('span', { class: 'text-3xl', title: u.sticker[1], role: 'img', 'aria-label': u.sticker[1] }, u.sticker[0])));
        root.querySelector('[data-done]').textContent = NUM(done); root.querySelector('[data-stars]').textContent = NUM(stars);
        const c = root.querySelector('[data-continue]');
        if (nextLink) { c.href = nextLink.href; c.textContent = done ? T('চালিয়ে যাও →', 'Continue →') : T('শুরু করো →', 'Start →'); } else { c.textContent = T('সব পাঠ শেষ! আবার খেলো', 'All lessons done! Play again'); }
    };
    const dlg = document.querySelector('[data-who-dialog]');
    const list = dlg.querySelector('[data-who-list]');
    const faces = dlg.querySelector('[data-faces]');
    let face = FACES[0];
    FACES.forEach((f, i) => faces.append(h('button', { type: 'button', role: 'radio', 'aria-checked': i ? 'false' : 'true', 'aria-label': T('ছবি ', 'Picture ') + f, class: 'flex h-12 w-12 items-center justify-center rounded-full border-2 border-navy text-2xl aria-checked:bg-navy',
        onclick: (e) => { face = f; faces.querySelectorAll('button').forEach((b) => b.setAttribute('aria-checked', String(b === e.currentTarget))); } }, f)));
    const fill = () => {
        list.replaceChildren(...S.profiles.map((p) => h('li', { class: 'flex items-center gap-2' },
            h('button', { type: 'button', class: `flex min-h-12 flex-1 items-center gap-2 rounded-full border-2 border-navy px-4 text-left font-bold ${p.id === S.current ? 'bg-navy text-white' : 'bg-white'}`, onclick: () => { S.current = p.id; save(); paint(); fill(); dlg.close(); } }, h('span', { class: 'text-2xl' }, p.face), p.name || T('নাম নেই', 'No name')),
            S.profiles.length > 1 ? h('button', { type: 'button', class: 'h-12 w-12 rounded-full border-2 border-navy', 'aria-label': (p.name || T('এই', 'This')) + T(' মুছে ফেলি', ' — delete'), onclick: () => {
                if (!confirm((p.name || T('এর', 'This one')) + T(' সব তারা আর স্টিকার মুছে যাবে। মুছব?', ': all stars and stickers will be deleted. Delete?'))) return;
                S.profiles = S.profiles.filter((x) => x.id !== p.id); delete S.data[p.id]; if (S.current === p.id) S.current = S.profiles[0].id; save(); paint(); fill();
            } }, '✕') : null)));
        dlg.querySelector('[data-who-add]').hidden = S.profiles.length >= 4 && S.profiles.every((p) => p.name);
    };
    dlg.querySelector('[data-who-add]').addEventListener('submit', (e) => {
        e.preventDefault(); const name = e.target.name.value.trim().slice(0, 20); if (!name) return;
        const blank = S.profiles.find((p) => !p.name);
        if (blank) { blank.name = name; blank.face = face; S.current = blank.id; } else if (S.profiles.length < 4) { const p = { id: 'p' + Date.now(), name, face }; S.profiles.push(p); S.current = p.id; }
        save(); e.target.reset(); face = FACES[0]; faces.querySelectorAll('button').forEach((b, i) => b.setAttribute('aria-checked', String(i === 0))); paint(); fill(); dlg.close();
    });
    root.querySelector('[data-who]').addEventListener('click', () => { me(); fill(); dlg.showModal(); });
    root.querySelector('[data-ui-toggle]')?.addEventListener('click', () => { S.settings.ui = EN() ? 'bn' : 'en'; save(); swapUi(); paint(); });
    swapUi();
    dlg.querySelector('[data-who-close]').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    paint();
    if (!me().name) { fill(); }
}

/* ---------- the parents' page: progress per child, letters known, move to another phone (English or Bengali) ---------- */
function parents(root) {
    const D = JSON.parse(document.getElementById('learn-data').textContent);
    const out = root.querySelector('[data-progress-out]');
    const msg = root.querySelector('[data-code-msg]');
    const paint = () => {
        swapUi();
        out.replaceChildren();
        if (!S.profiles.length || !Object.keys(S.data).length) { out.append(h('p', { class: 'mt-3 text-lg' }, T('এই ফোনে এখনও কোনো পাঠ খেলা হয়নি। ', 'No lesson has been played on this phone yet. '), h('a', { href: D.start, class: 'font-bold underline' }, T('প্রথম পাঠ শুরু করুন →', 'Start the first lesson →')))); return; }
        S.profiles.forEach((p) => {
            const m = Object.assign({ done: {}, missed: [], week: {}, stickers: [] }, S.data[p.id] || {});
            const doneIds = Object.keys(m.done);
            const known = new Set(D.lessons.filter((l) => m.done[l.id]).flatMap((l) => l.new));
            const shaky = new Set(m.missed.flatMap((w) => [...w]).filter((c) => known.has(c)));
            const next = D.lessons.find((l) => !m.done[l.id]);
            out.append(h('section', { class: 'mt-4 rounded-2xl border-2 border-navy bg-white p-4' },
                h('h3', { class: 'font-display text-2xl font-extrabold' }, (p.face || '🐟') + ' ' + (p.name || T('নাম নেই', 'No name'))),
                h('p', { class: 'mt-1 text-lg' }, NUM(doneIds.length), '/', NUM(D.lessons.length), T(' পাঠ · ', ' lessons · '), NUM(Object.values(m.done).reduce((a, b) => a + b, 0)), T(' ⭐ · এই সপ্তাহে ', ' ⭐ · this week '), NUM(m.week[weekKey()] || 0), T(' মিনিট', ' minutes')),
                next ? h('p', { class: 'mt-2' }, T('আজ: ', 'Today: '), h('a', { href: next.url, class: 'font-bold underline' }, (EN() ? next.unitEn : next.unit) + ' · ' + next.title)) : h('p', { class: 'mt-2 font-bold' }, T('সব পাঠ শেষ!', 'All lessons done!')),
                h('p', { class: 'mt-3 font-bold' }, T('অক্ষর ও চিহ্ন', 'Letters and signs')),
                h('div', { class: 'mt-1 flex flex-wrap gap-1', role: 'list' }, D.items.map((t) => h('span', { role: 'listitem', class: `flex h-11 min-w-11 items-center justify-center rounded-lg border px-1 font-display text-xl font-extrabold ${shaky.has(t) ? 'border-red bg-pink' : known.has(t) ? 'border-sky-deep bg-sky-tint' : 'border-rule text-ink2'}`, 'aria-label': t + ': ' + (shaky.has(t) ? T('আরেকবার দেখা দরকার', 'needs another look') : known.has(t) ? T('শেখা হয়েছে', 'learnt') : T('এখনও নয়', 'not yet')) }, t))),
                h('p', { class: 'mt-2 text-sm text-ink2' }, T('নীল: শেখা হয়েছে · গোলাপি: আরেকবার দেখা দরকার · সাদা: এখনও নয়', 'Blue: learnt · pink: needs another look · white: not yet'))));
        });
    };
    const clean = (d) => {   // a code from anywhere: keep only what the course knows, numbers capped
        const ids = new Set(D.lessons.map((l) => l.id)); const units = new Set(D.lessons.map((l) => l.id.split('/')[0]));
        const profiles = (Array.isArray(d.profiles) ? d.profiles : []).slice(0, 4).map((p) => ({ id: String(p && p.id).slice(0, 20), name: String((p && p.name) || '').slice(0, 20), face: FACES.includes(p && p.face) ? p.face : '🐟' }));
        const data = {};
        profiles.forEach((p) => {
            const x = (d.data && d.data[p.id]) || {}; const done = {}; const week = {};
            Object.entries(x.done || {}).forEach(([k, v]) => { if (ids.has(k)) done[k] = Math.max(1, Math.min(3, parseInt(v, 10) || 1)); });
            Object.entries(x.week || {}).slice(-20).forEach(([k, v]) => { if (/^\d{4}-\d{1,2}$/.test(k)) week[k] = Math.max(0, Math.min(10000, parseInt(v, 10) || 0)); });
            data[p.id] = { done, week, missed: (Array.isArray(x.missed) ? x.missed : []).map((w) => String(w).slice(0, 40)).slice(-30), stickers: (Array.isArray(x.stickers) ? x.stickers : []).filter((u) => units.has(u)), last: ids.has(x.last) ? x.last : null };
        });
        return { profiles, data };
    };
    root.querySelector('[data-export]')?.addEventListener('click', () => {
        const code = btoa(unescape(encodeURIComponent(JSON.stringify({ v: 1, profiles: S.profiles, data: S.data }))));
        const box = root.querySelector('[data-code]'); box.value = code; box.select();
        msg.textContent = T('কোড তৈরি। ওপরের বাক্স থেকে কপি করুন, বা "পাঠান" টিপুন।', 'Code ready. Copy it from the box above, or tap "Send".');
        navigator.clipboard?.writeText(code).then(() => { msg.textContent = T('কোড কপি হয়েছে। অন্য ফোনে এই পাতায় বসান।', 'Code copied. Paste it on this page on the other phone.'); }, () => {});
        const sh = root.querySelector('[data-share-code]'); if (sh && navigator.share) sh.hidden = false;
    });
    root.querySelector('[data-share-code]')?.addEventListener('click', () => { const code = root.querySelector('[data-code]').value; if (code) navigator.share({ title: 'বাংলা শিখি', text: code }).catch(() => {}); });
    root.querySelector('[data-import]')?.addEventListener('click', () => {
        try {
            const d = JSON.parse(decodeURIComponent(escape(atob(root.querySelector('[data-code]').value.trim()))));
            if (d.v !== 1) throw new Error('version');
            const c = clean(d); if (!c.profiles.length) throw new Error('empty');
            if (!confirm(T('এই ফোনের অগ্রগতির জায়গায় কোডের অগ্রগতি বসবে। ঠিক আছে?', 'The progress from the code will replace the progress on this phone. OK?'))) return;
            S.profiles = c.profiles; S.data = c.data; S.current = S.profiles[0].id; save(); paint(); msg.textContent = T('অগ্রগতি বসানো হয়েছে।', 'Progress moved.');
        } catch (e) { msg.textContent = T('কোডটা ঠিক নেই। পুরোটা কপি করে আবার বসান।', 'That code is not right. Copy all of it and try again.'); }
    });
    root.querySelector('[data-print-card]')?.addEventListener('click', () => window.print());
    root.querySelector('[data-ui-toggle]')?.addEventListener('click', () => { S.settings.ui = EN() ? 'bn' : 'en'; save(); paint(); });
    paint();
}

/* ---------- the certificate: the child's name from this phone, printed, nothing sent ---------- */
function certificate(root) {
    const name = root.querySelector('[data-cert-name]');
    const input = root.querySelector('[data-cert-input]');
    if (S.profiles.length) input.value = me().name || '';
    swapUi();
    const sync = () => { name.textContent = input.value.trim() || ' '; };
    input.addEventListener('input', sync); sync();
    root.querySelector('[data-cert-print]')?.addEventListener('click', () => window.print());
}

const pages = { '[data-learn-lesson]': lesson, '[data-learn-map]': map, '[data-learn-parents]': parents, '[data-learn-cert]': certificate };
for (const [sel, fn] of Object.entries(pages)) { const el = document.querySelector(sel); if (el) fn(el); }
document.querySelectorAll('[data-print]').forEach((b) => { b.hidden = false; b.addEventListener('click', () => window.print()); });
paintStrokeCanvases();   // worksheets: the first box shows the approved stroke order
window.hpStrokes = { draftStrokes, drawGlyph, drawStrokes, paintStrokeCanvases };   // the desk's stroke-order page
