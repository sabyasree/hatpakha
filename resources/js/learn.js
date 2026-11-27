/*
 * D321: বাংলা শিখি on the phone. One file for the children's map, the lesson player, the parents' page and the certificate.
 * Progress lives in this browser only (localStorage, wrapped so the pages still work without it). Nothing is sent anywhere.
 * No sound is needed; a recording plays only where the desk has uploaded one (owner: human recordings, D321).
 */

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
const swapUi = () => document.querySelectorAll('[data-en]').forEach((el) => {
    if (el.dataset.bn === undefined) el.dataset.bn = el.textContent;
    el.textContent = EN() ? el.dataset.en : el.dataset.bn;
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
            h('span', { class: 'text-6xl', 'aria-hidden': 'true' }, item.ex.p || '📖'), h('span', {}, h('span', { class: 'block font-display text-5xl font-extrabold' }, item.ex.w),
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
        const ink = h('canvas', { width: size * 2, height: size * 2, class: 'absolute inset-0 h-full w-full touch-none', role: 'img', 'aria-label': glyph + T(' লেখো', ' — write it') });
        const box = h('div', { class: 'relative mt-4 rounded-3xl border-2 border-navy bg-white', style: `width:${size}px;height:${size}px` }, guide, ink);
        const msg = h('p', { class: 'mt-3 min-h-8 text-xl font-bold', role: 'status' }, '');
        const g = guide.getContext('2d'); const k = ink.getContext('2d');
        const drawGuide = () => {
            g.clearRect(0, 0, guide.width, guide.height); g.fillStyle = '#E8DCCB'; g.textAlign = 'center'; g.textBaseline = 'middle';
            g.font = `800 ${size * 1.2}px "Anek Bangla", sans-serif`; g.fillText(glyph, guide.width / 2, guide.height / 2 + size * 0.08);
            g.strokeStyle = '#E39A6A'; g.setLineDash([12, 12]); g.lineWidth = 3; g.beginPath(); g.moveTo(0, guide.height * 0.3); g.lineTo(guide.width, guide.height * 0.3); g.stroke();
        };
        (document.fonts ? document.fonts.load(`800 120px "Anek Bangla"`, glyph) : Promise.resolve()).then(drawGuide, drawGuide);
        let down = false; let last = null; let done = false;
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
            else if (got) msg.textContent = T('আরও একটু, ধূসর অক্ষরের ওপর দিয়ে।', 'A little more, over the grey letter.');
        };
        ink.addEventListener('pointerup', check); ink.addEventListener('pointercancel', check);
        put(h('h2', { class: 'font-display text-3xl font-extrabold', tabindex: '-1' }, T('আঙুল দিয়ে লেখো: ', 'Write it with your finger: '), glyph), box, msg);
        actions.append(h('button', { type: 'button', class: OUTLINE, onclick: () => { k.clearRect(0, 0, ink.width, ink.height); done = false; msg.textContent = ''; } }, T('মুছি', 'Clear')),
            h('button', { type: 'button', class: PRIMARY + ' opacity-50', onclick: next, 'data-next': true }, done ? T('পরের →', 'Next →') : T('পরের →', 'Next →')));
    };

    /* a question with choices; wrong answers come back once, at the end */
    const ask = (q, again = false) => () => {
        score.asked += again ? 0 : 1;
        let wrong = false;
        const msg = h('p', { class: 'mt-4 min-h-8 text-xl font-bold', role: 'status' }, '');
        const grid = h('div', { class: `mt-5 grid w-full max-w-md gap-3 ${q.options.length > 2 ? 'grid-cols-1 sm:grid-cols-3' : 'grid-cols-2'}` });
        q.options.forEach((o) => grid.append(h('button', {
            type: 'button', class: `min-h-20 rounded-2xl border-2 border-navy bg-white px-3 ${q.big === false ? 'text-2xl' : 'text-4xl'} font-display font-extrabold`, 'data-answer': o === q.answer ? 'yes' : null,
            onclick: (e) => {
                const b = e.currentTarget;
                if (o === q.answer) {
                    b.classList.add('bg-sky-light', 'border-sky-deep'); b.append(' ✓'); msg.textContent = wrong ? T('এবার ঠিক!', 'Right this time!') : T('ঠিক!', 'Right!');
                    if (!wrong && !again) score.first++;
                    grid.querySelectorAll('button').forEach((x) => { x.disabled = true; });
                    setTimeout(next, 750);
                } else {
                    if (!wrong && q.word) { const m = mine(); if (!m.missed.includes(q.word)) { m.missed.push(q.word); m.missed = m.missed.slice(-30); save(); } }
                    wrong = true; b.classList.add('animate-[hp-shake_0.35s]', 'border-red', 'text-ink2'); b.append(' ✗'); b.disabled = true;
                    msg.textContent = T('এটা নয়। আবার দেখো।', 'Not that one. Look again.');
                    if (!again && !q.requeued) { q.requeued = true; steps.splice(steps.length - 1, 0, ask(q, true)); }
                }
            },
        }, o)));
        put(h('h2', { class: 'font-display text-2xl font-extrabold', tabindex: '-1' }, q.prompt), q.show ? h('div', { class: `mt-4 ${q.showBig ? 'text-8xl' : 'font-display text-6xl font-extrabold'}` }, q.show) : null, grid, msg);
    };

    /* build a word (or a sentence) from its tiles */
    const build = (target, tiles, prompt, show, again = false) => () => {
        score.asked += again ? 0 : 1;
        let i = 0; let wrong = false;
        const slots = h('div', { class: 'mt-4 flex flex-wrap justify-center gap-2' }, tiles.map(() => h('span', { class: 'flex min-h-16 min-w-14 items-center justify-center rounded-xl border-2 border-dashed border-navy bg-paper px-2 font-display text-3xl font-extrabold' })));
        const msg = h('p', { class: 'mt-3 min-h-8 text-xl font-bold', role: 'status' }, '');
        const pool = h('div', { class: 'mt-5 flex flex-wrap justify-center gap-3' }, shuffle(tiles.map((t, k) => [t, k])).map(([t]) => h('button', {
            type: 'button', class: 'min-h-16 min-w-16 rounded-2xl border-2 border-navy bg-white px-3 font-display text-3xl font-extrabold',
            onclick: (e) => {
                const b = e.currentTarget;
                if (t === tiles[i]) {
                    slots.children[i].textContent = t; slots.children[i].classList.replace('border-dashed', 'border-solid'); b.disabled = true; b.classList.add('opacity-30'); i++;
                    if (i === tiles.length) { msg.textContent = T('ঠিক! ', 'Right! ') + target; if (!wrong && !again) score.first++; setTimeout(next, 900); }
                } else {
                    if (!wrong && !again) { const m = mine(); if (!m.missed.includes(target)) { m.missed.push(target); m.missed = m.missed.slice(-30); save(); } }
                    wrong = true; b.classList.add('animate-[hp-shake_0.35s]'); setTimeout(() => b.classList.remove('animate-[hp-shake_0.35s]'), 400); msg.textContent = T('আগে অন্যটা।', 'Another one comes first.');
                }
            },
        }, t)));
        put(h('h2', { class: 'font-display text-2xl font-extrabold', tabindex: '-1' }, prompt), show ? h('p', { class: 'mt-3 text-7xl', 'aria-hidden': 'true' }, show) : null, slots, pool, msg);
    };

    const readAloud = (items) => () => {
        put(h('h2', { class: 'font-display text-2xl font-extrabold', tabindex: '-1' }, T('জোরে পড়ো', 'Read it aloud')),
            h('div', { class: 'mt-5 space-y-4' }, items.map((t) => h('p', { class: `font-display ${t.length > 14 ? 'text-4xl' : 'text-6xl'} font-extrabold leading-snug` }, t))),
            h('p', { class: 'mt-6 text-base text-ink2' }, T('বাবা-মা: শিশু পড়তে পারলে ✓ টিপুন।', 'Grown-ups: tap ✓ when your child has read it.')));
        actions.append(h('button', { type: 'button', class: OUTLINE, onclick: next }, T('আবার দেখি', 'Not yet')), h('button', { type: 'button', class: PRIMARY, onclick: () => { score.read = true; next(); } }, T('পড়তে পেরেছে ✓', 'Read it ✓')));
    };

    const story = (st) => () => {
        stage.classList.replace('justify-center', 'justify-start');
        put(h('p', { class: 'text-7xl', 'aria-hidden': 'true' }, st.picture || '📖'), h('h1', { class: 'mt-2 font-display text-4xl font-extrabold', tabindex: '-1' }, D.title),
            h('div', { class: 'mt-4 w-full space-y-3 text-left font-serif text-2xl leading-loose' }, st.text.split('\n').map((p) => h('p', {}, p))));
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
        D.words.forEach((w) => { m.missed = m.missed.filter((x) => x !== w.w || !score.first); });
        save();
        bar.style.width = '100%';
        put(h('p', { class: 'text-7xl tracking-widest', 'aria-label': NUM(stars) + T('টি তারা', ' stars') }, '⭐'.repeat(stars) + '☆'.repeat(3 - stars)),
            h('h1', { class: 'mt-3 font-display text-5xl font-extrabold', tabindex: '-1' }, stars === 3 ? T('দারুণ!', 'Brilliant!') : stars === 2 ? T('খুব ভালো!', 'Very good!') : T('শেষ করেছ!', 'Done!')),
            h('p', { class: 'mt-2 text-xl' }, me().name ? me().name + ', ' : '', T('পাঠ ', 'Lesson '), NUM(D.n), T(' শেষ।', ' done.')),
            sticker ? h('div', { class: 'mt-6 rounded-3xl border-2 border-navy bg-white p-4' }, h('p', { class: 'text-7xl' }, sticker[0]), h('p', { class: 'mt-1 font-display text-2xl font-extrabold' }, T('নতুন স্টিকার: ', 'New sticker: '), sticker[1])) : null);
        actions.classList.add('flex-wrap');
        actions.append(h('a', { href: D.urls.next, class: PRIMARY + ' flex items-center' }, D.urls.nextId ? T('পরের পাঠ →', 'Next lesson →') : T('ম্যাপে ফিরি', 'Back to the map')),
            h('a', { href: D.urls.sheet, class: OUTLINE + ' flex items-center' }, T('ওয়ার্কশিট', 'Worksheet')),
            D.urls.certificate ? h('a', { href: D.urls.certificate, class: OUTLINE + ' flex items-center' }, T('শংসাপত্র', 'Certificate')) : h('a', { href: D.urls.map, class: OUTLINE + ' flex items-center' }, T('ম্যাপ', 'Map')));
    };

    /* ---- the questions this lesson can ask ---- */
    const all = D.words.concat(D.pool);
    const withPic = (list) => list.filter((w) => w.p);
    const others = (w, list, n) => pick(list.filter((x) => x.w !== w.w && x.p !== w.p), n);
    const qs = [];
    withPic(D.words).forEach((w, i) => {
        const opts = others(w, withPic(all), 2);
        if (!opts.length) return;
        if (i % 2 === 0) qs.push(ask({ prompt: T('কোনটা?', 'Which one?'), show: w.p, showBig: true, options: shuffle([w.w, ...opts.map((o) => o.w)]), answer: w.w, word: w.w }));
        else qs.push(ask({ prompt: T('কোন ছবি?', 'Which picture?'), show: w.w, options: shuffle([w.p, ...opts.map((o) => o.p)]), answer: w.p, word: w.w }));
    });
    D.new.filter((x) => x.kind === 'letter' || x.kind === 'joined').forEach((x) => {
        const has = D.words.filter((w) => w.w.includes(x.t)); const not = all.filter((w) => !w.w.includes(x.t));
        if (has.length && not.length >= 1) { const w = pick(has, 1)[0]; qs.push(ask({ prompt: T(`কোন শব্দে "${x.t}" আছে?`, `Which word has "${x.t}"?`), options: shuffle([w.w, ...pick(not, 2).map((o) => o.w)]), answer: w.w, word: w.w })); }
    });
    D.new.filter((x) => x.kind === 'sign' && VOWEL_OF[x.t]).forEach((x) => {
        const w = pick(D.words.filter((w) => w.tiles.some((t) => t.endsWith(x.t))), 1)[0];
        if (!w) return;
        const k = w.tiles.findIndex((t) => t.endsWith(x.t)); const base = w.tiles[k].slice(0, -1);
        const opts = [base + x.t, ...pick(D.signs.filter((s) => s !== x.t), 2).map((s) => base + s)];
        if (D.signs.length < 2) opts.push(base);
        const gap = w.tiles.map((t, j) => (j === k ? '_' : t)).join('');
        qs.push(ask({ prompt: T('ফাঁকে কোনটা বসবে?', 'Which one goes in the gap?'), show: h('span', {}, w.p ? h('span', { class: 'block text-7xl' }, w.p) : null, gap), options: shuffle([...new Set(opts)]), answer: base + x.t, word: w.w }));
    });
    D.new.filter((x) => x.kind === 'joined' && x.parts.length === 2).forEach((x) => {
        const right = x.parts.join(' + ');
        const wrong = [...new Set(pick(CONSONANTS.filter((c) => !x.parts.includes(c)), 2).map((c, i) => (i ? x.parts[0] + ' + ' + c : c + ' + ' + x.parts[1])))];
        qs.push(ask({ prompt: T(`${x.t} কোন দুটো অক্ষর?`, `Which two letters make ${x.t}?`), show: x.t, options: shuffle([right, ...wrong]), answer: right, big: false }));
    });
    (D.pairs || []).forEach(([a, b]) => qs.push(ask({ prompt: T('কোনটা আলাদা?', 'Which one is different?'), options: shuffle([a, a, a, b]), answer: b })));
    const buildable = D.words.filter((w) => w.tiles.length >= 2);
    pick(buildable, 2).forEach((w) => qs.push(build(w.w, w.tiles, T('অক্ষর সাজিয়ে শব্দ বানাও', 'Build the word'), w.p || null)));
    D.sentences.filter((s) => s.p).forEach((s) => { const opts = others(s, withPic(D.sentences.concat(all)), 2); if (opts.length) qs.push(ask({ prompt: T('কোন ছবি?', 'Which picture?'), show: s.w, options: shuffle([s.p, ...opts.map((o) => o.p)]), answer: s.p })); });
    pick(D.sentences, 2).forEach((s) => { const ws = s.w.replace(/[।?!]$/u, '').split(' '); if (ws.length >= 2) qs.push(build(s.w, ws, T('শব্দ সাজিয়ে বাক্য বানাও', 'Build the sentence'), s.p || null)); });
    const review = mine().missed.map((w) => all.find((x) => x.w === w)).filter((w) => w && w.p && !D.words.some((x) => x.w === w.w)).slice(-2)
        .map((w) => ask({ prompt: T('মনে আছে? কোনটা?', 'Remember? Which one?'), show: w.p, showBig: true, options: shuffle([w.w, ...others(w, withPic(all), 2).map((o) => o.w)]), answer: w.w, word: w.w }));

    /* ---- the order of the lesson ---- */
    D.new.forEach((x) => steps.push(meet(x)));
    D.words.forEach((w) => steps.push(word(w)));
    D.sentences.forEach((s) => steps.push(sentence(s)));
    const traceable = D.new.find((x) => x.kind !== 'phola' && !['ং', 'ঃ', 'ঁ'].includes(x.t));
    if (traceable) steps.push(trace(traceable.kind === 'sign' ? 'ক' + traceable.t : traceable.t));
    if (D.story) steps.push(story(D.story));
    steps.push(...review, ...shuffle(qs).slice(0, 7));
    if (D.story) steps.push(ask({ prompt: D.story.q[0], options: shuffle(D.story.q[1]), answer: D.story.q[1][D.story.q[2]], big: false }));
    const toRead = D.story ? [] : D.sentences.length ? D.sentences.slice(0, 2).map((s) => s.w) : D.words.slice(0, 3).map((w) => w.w);
    if (toRead.length) steps.push(readAloud(toRead));
    steps.push(finish);

    /* parents' sheet: tips, how to say the new things, settings */
    const dlg = document.querySelector('[data-parent]');
    const sounds = dlg.querySelector('[data-parent-sounds]');
    if (D.new.length) sounds.append(h('p', { class: 'font-bold' }, T('এই পাঠে নতুন:', 'New in this lesson:')), h('ul', { class: 'mt-1 space-y-1', lang: 'en' }, D.new.map((x) => h('li', {}, h('span', { class: 'font-display text-2xl font-extrabold', lang: 'bn' }, x.kind === 'phola' ? (x.t === 'র্' ? 'র্ক' : 'ক' + x.t) : x.kind === 'sign' ? (x.t === 'ঁ' ? 'কাঁ' : 'ক' + x.t) : x.t), ' — ', x.sound || x.parts.join(' + '), x.hint ? h('span', { class: 'block text-sm' }, x.hint) : null))));
    document.querySelector('[data-parent-open]').addEventListener('click', () => dlg.showModal());
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
            a.querySelector('[data-lesson-stars]').textContent = s ? '⭐'.repeat(s) : '';
            a.classList.toggle('bg-sky-tint', s > 0); a.classList.toggle('bg-white', !s);
            a.classList.remove('ring-4', 'ring-red');
            if (s) { done++; stars += s; } else if (!nextLink) nextLink = a;
        });
        if (nextLink) nextLink.classList.add('ring-4', 'ring-red');
        root.querySelectorAll('[data-unit]').forEach((u) => {
            const st = u.querySelector('[data-sticker]'); const got = m.stickers.includes(u.dataset.unit);
            st.classList.toggle('grayscale-[85%]', !got); st.classList.toggle('opacity-60', !got);
        });
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
        save(); e.target.reset(); paint(); fill(); dlg.close();
    });
    root.querySelector('[data-who]').addEventListener('click', () => { me(); fill(); dlg.showModal(); });
    root.querySelector('[data-ui-toggle]')?.addEventListener('click', () => { S.settings.ui = EN() ? 'bn' : 'en'; save(); swapUi(); paint(); });
    swapUi();
    dlg.querySelector('[data-who-close]').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    paint();
    if (!me().name) { fill(); }
    void D;
}

/* ---------- the parents' page: progress per child, letters known, move to another phone ---------- */
function parents(root) {
    const D = JSON.parse(document.getElementById('learn-data').textContent);
    const out = root.querySelector('[data-progress-out]');
    const paint = () => {
        out.replaceChildren();
        if (!S.profiles.length || !Object.keys(S.data).length) { out.append(h('p', { class: 'text-lg' }, 'এই ফোনে এখনও কোনো পাঠ খেলা হয়নি। ', h('a', { href: D.start, class: 'font-bold underline' }, 'প্রথম পাঠ শুরু করুন →'))); return; }
        S.profiles.forEach((p) => {
            const m = Object.assign({ done: {}, missed: [], week: {}, stickers: [] }, S.data[p.id] || {});
            const doneIds = Object.keys(m.done);
            const known = new Set(D.lessons.filter((l) => m.done[l.id]).flatMap((l) => l.new));
            const shaky = new Set(m.missed.flatMap((w) => [...w]).filter((c) => known.has(c)));
            const next = D.lessons.find((l) => !m.done[l.id]);
            out.append(h('section', { class: 'mt-4 rounded-2xl border-2 border-navy bg-white p-4' },
                h('h3', { class: 'font-display text-2xl font-extrabold' }, (p.face || '🐟') + ' ' + (p.name || 'নাম নেই')),
                h('p', { class: 'mt-1 text-lg' }, BN(doneIds.length), '/', BN(D.lessons.length), ' পাঠ · ', BN(Object.values(m.done).reduce((a, b) => a + b, 0)), ' ⭐ · এই সপ্তাহে ', BN(m.week[weekKey()] || 0), ' মিনিট'),
                next ? h('p', { class: 'mt-2' }, 'আজ: ', h('a', { href: next.url, class: 'font-bold underline' }, next.unit + ' · ' + next.title)) : h('p', { class: 'mt-2 font-bold' }, 'সব পাঠ শেষ!'),
                h('p', { class: 'mt-3 font-bold' }, 'অক্ষর ও চিহ্ন'),
                h('div', { class: 'mt-1 flex flex-wrap gap-1', role: 'list' }, D.items.map((t) => h('span', { role: 'listitem', class: `flex h-11 min-w-11 items-center justify-center rounded-lg border px-1 font-display text-xl font-extrabold ${shaky.has(t) ? 'border-red bg-pink' : known.has(t) ? 'border-sky-deep bg-sky-tint' : 'border-rule text-ink2'}`, title: shaky.has(t) ? 'আরেকবার দেখা দরকার' : known.has(t) ? 'শেখা হয়েছে' : 'এখনও নয়' }, t))),
                h('p', { class: 'mt-2 text-sm text-ink2' }, 'নীল: শেখা হয়েছে · গোলাপি: আরেকবার দেখা দরকার · সাদা: এখনও নয়')));
        });
    };
    root.querySelector('[data-export]')?.addEventListener('click', () => {
        const code = btoa(unescape(encodeURIComponent(JSON.stringify({ v: 1, profiles: S.profiles, data: S.data }))));
        const box = root.querySelector('[data-code]'); box.value = code; box.select();
        navigator.clipboard?.writeText(code).then(() => { root.querySelector('[data-code-msg]').textContent = 'কোড কপি হয়েছে। অন্য ফোনে এই পাতায় বসান।'; }, () => {});
    });
    root.querySelector('[data-import]')?.addEventListener('click', () => {
        const msg = root.querySelector('[data-code-msg]');
        try {
            const d = JSON.parse(decodeURIComponent(escape(atob(root.querySelector('[data-code]').value.trim()))));
            if (d.v !== 1 || !Array.isArray(d.profiles) || typeof d.data !== 'object') throw new Error('shape');
            if (!confirm('এই ফোনের অগ্রগতির জায়গায় কোডের অগ্রগতি বসবে। ঠিক আছে?')) return;
            S.profiles = d.profiles.slice(0, 4).map((p) => ({ id: String(p.id).slice(0, 20), name: String(p.name || '').slice(0, 20), face: FACES.includes(p.face) ? p.face : '🐟' }));
            S.data = {}; S.profiles.forEach((p) => { if (d.data[p.id]) S.data[p.id] = d.data[p.id]; });
            S.current = S.profiles[0]?.id || null; save(); paint(); msg.textContent = 'অগ্রগতি বসানো হয়েছে।';
        } catch (e) { msg.textContent = 'কোডটা ঠিক নেই। পুরোটা কপি করে আবার বসান।'; }
    });
    root.querySelector('[data-print-card]')?.addEventListener('click', () => window.print());
    paint();
}

/* ---------- the certificate: the child's name from this phone, printed, nothing sent ---------- */
function certificate(root) {
    const name = root.querySelector('[data-cert-name]');
    const input = root.querySelector('[data-cert-input]');
    if (S.profiles.length) input.value = me().name || '';
    const sync = () => { name.textContent = input.value.trim() || ' '; };
    input.addEventListener('input', sync); sync();
    root.querySelector('[data-cert-print]')?.addEventListener('click', () => window.print());
}

const pages = { '[data-learn-lesson]': lesson, '[data-learn-map]': map, '[data-learn-parents]': parents, '[data-learn-cert]': certificate };
for (const [sel, fn] of Object.entries(pages)) { const el = document.querySelector(sel); if (el) fn(el); }
document.querySelectorAll('[data-print]').forEach((b) => { b.hidden = false; b.addEventListener('click', () => window.print()); });
