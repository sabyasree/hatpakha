/* D328: অক্ষরজাল. Drag across touching tiles (or tap them one by one, then "জমা দিন") to make a word; the day's
   words are known in advance, so a find is checked at once. Three minutes from "শুরু করুন"; the clock keeps its
   time across a reload (the start is kept in this browser). */
(function () {
    const root = document.querySelector('[data-jal]');
    if (!root) return;
    const data = JSON.parse(root.querySelector('[data-jal-data]').textContent);
    const $ = (s) => root.querySelector('[data-jal-' + s + ']');
    const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
    const words = new Map(JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(data.w), (c) => c.charCodeAt(0)))));
    const pointsFor = (n) => (n >= 4 ? 20 : n === 3 ? 10 : n === 2 ? 5 : 0);
    const KEY = 'hp-jal';
    let store = {};
    try { store = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { store = {}; }
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (e) { /* private window */ } };
    if (store.date !== data.date) store = { date: data.date, start: null, found: [], ended: false };
    const total = () => Math.min(100, store.found.reduce((s, w) => s + pointsFor(words.get(w) || 0), 0));

    // the grid
    const gridBox = $('grid');
    const tiles = data.grid.map((t, i) => {
        const b = document.createElement('button');
        b.type = 'button'; b.textContent = t; b.dataset.i = i;
        b.setAttribute('aria-label', t + ', সারি ' + bn(Math.floor(i / 4) + 1) + ', ঘর ' + bn((i % 4) + 1));
        gridBox.appendChild(b);
        return b;
    });
    const near = (a, b) => a !== b && Math.abs(Math.floor(a / 4) - Math.floor(b / 4)) <= 1 && Math.abs((a % 4) - (b % 4)) <= 1;
    let path = [];
    let mode = null;   // 'drag' while a finger is down; 'tap' after a single tap
    let moved = false;
    const paint = () => {
        tiles.forEach((b, i) => {
            const on = path.includes(i);
            b.className = 'flex items-center justify-center rounded-2xl border-2 font-serif text-[clamp(26px,8vw,38px)] font-bold transition-colors '
                + (on ? (i === path[path.length - 1] ? 'border-navy bg-sky text-navy' : 'border-sky-deep bg-sky-light text-navy') : 'border-peach-deep bg-peach-light text-navy');
        });
        $('word').textContent = path.map((i) => data.grid[i]).join('');
        const svg = $('line'); const wrap = $('wrap').getBoundingClientRect();
        svg.setAttribute('viewBox', '0 0 ' + wrap.width + ' ' + wrap.height);
        const pts = path.map((i) => { const r = tiles[i].getBoundingClientRect(); return (r.left - wrap.left + r.width / 2) + ',' + (r.top - wrap.top + r.height / 2); });
        svg.innerHTML = pts.length > 1 ? '<polyline points="' + pts.join(' ') + '" fill="none" stroke="#CB1B32" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" opacity=".6"/>' : '';
    };
    const say = (text, good) => { const m = $('msg'); m.textContent = text; m.className = 'mt-2 min-h-6 text-center text-lg font-bold ' + (good ? 'text-sky-night' : 'text-red'); };
    const foundList = () => {
        const ul = $('found'); ul.textContent = '';
        [...store.found].reverse().forEach((w) => { const li = document.createElement('li'); li.className = 'rounded-full border-2 border-navy bg-white px-3 py-1 font-serif text-lg'; li.textContent = w; ul.appendChild(li); });
        $('count').textContent = '(' + bn(store.found.length) + ')';
        $('points').textContent = bn(total());
    };
    const submit = () => {
        const w = path.map((i) => data.grid[i]).join('');
        const n = path.length;
        path = []; mode = null; paint();
        if (n < 2) { if (n) say('অন্তত দুটো অক্ষর লাগবে'); return; }
        if (store.found.includes(w)) { say(w + ': আগেই পেয়েছেন'); return; }
        if (!words.has(w)) { say(w + ': এই শব্দটা আমাদের তালিকায় নেই'); return; }
        store.found.push(w); save(); foundList();
        say('✓ ' + w + '  +' + bn(pointsFor(n)), true);
    };
    const tileAt = (x, y) => {
        const el = document.elementFromPoint(x, y);
        const b = el && el.closest && el.closest('[data-jal-grid] button');
        if (!b) return null;
        // only the middle of a tile counts while dragging, so a diagonal does not catch its neighbours
        const r = b.getBoundingClientRect(); const dx = x - (r.left + r.width / 2); const dy = y - (r.top + r.height / 2);
        return Math.hypot(dx, dy) < r.width * 0.42 ? Number(b.dataset.i) : null;
    };
    const wrapEl = $('wrap');
    wrapEl.addEventListener('pointerdown', (e) => {
        if (store.ended) return;
        const b = e.target.closest('button'); if (!b) return;
        e.preventDefault();
        const i = Number(b.dataset.i);
        if (mode === 'tap' && path.length) {
            if (i === path[path.length - 1]) path.pop();
            else if (near(path[path.length - 1], i) && !path.includes(i)) path.push(i);
            else path = [i];
            if (!path.length) mode = null;
            paint(); return;
        }
        path = [i]; mode = 'drag'; moved = false;
        try { wrapEl.setPointerCapture(e.pointerId); } catch (err) { /* old browser */ }
        paint();
    });
    wrapEl.addEventListener('pointermove', (e) => {
        if (mode !== 'drag') return;
        const i = tileAt(e.clientX, e.clientY);
        if (i === null || i === path[path.length - 1]) return;
        if (path.length > 1 && i === path[path.length - 2]) { path.pop(); paint(); return; }   // back one step
        if (!path.includes(i) && near(path[path.length - 1], i)) { path.push(i); moved = true; paint(); }
    });
    const up = () => {
        if (mode !== 'drag') return;
        if (moved && path.length >= 2) submit();
        else { mode = 'tap'; say('এবার পাশের অক্ষরগুলো টিপুন, তারপর "জমা দিন"', true); }
    };
    wrapEl.addEventListener('pointerup', up);
    wrapEl.addEventListener('pointercancel', up);
    $('submit').addEventListener('click', submit);
    $('clear').addEventListener('click', () => { path = []; mode = null; paint(); say(''); });

    // the clock
    let timer = null;
    const left = () => Math.max(0, data.seconds - Math.floor((Date.now() - store.start) / 1000));
    const tick = () => {
        const s = left();
        $('clock').textContent = '⏱ ' + bn(Math.floor(s / 60)) + ':' + bn(String(s % 60).padStart(2, '0'));
        if (s <= 0) finish();
    };
    const begin = () => {
        if (!store.start) { store.start = Date.now(); save(); }
        $('intro').hidden = true; $('board').hidden = false;
        $('board').scrollIntoView({ block: 'start', behavior: 'smooth' });   // the clock and the whole grid in view
        paint(); foundList(); tick(); timer = setInterval(tick, 250);
    };
    $('go').addEventListener('click', begin);
    $('stop').addEventListener('click', () => { if (confirm('এখনই শেষ করবেন? বাকি সময় আর থাকবে না।')) finish(); });

    const shareText = () => {
        const longest = [...store.found].sort((a, b) => (words.get(b) || 0) - (words.get(a) || 0))[0];
        return 'হাতপাখা অক্ষরজাল #' + bn(data.no) + '\n' + bn(store.found.length) + 'টা শব্দ · ' + bn(total()) + ' পয়েন্ট' + (longest ? '\nসবচেয়ে বড়: ' + longest : '') + '\nআপনিও খেলুন: ' + data.url;
    };
    function finish() {
        if (timer) clearInterval(timer);
        store.ended = true; save(); path = []; mode = null;
        $('intro').hidden = true; $('board').hidden = true; $('done').hidden = false;
        const n = store.found.length;
        $('cheer').textContent = n >= 12 ? 'দারুণ! শব্দের জাদুকর' : (n >= 6 ? 'বেশ হয়েছে!' : (n ? 'ভালো শুরু' : 'কাল আবার চেষ্টা করুন'));
        $('score').textContent = bn(total()) + ' / ১০০ পয়েন্ট';
        $('sum').textContent = 'জালে ছিল ' + bn(words.size) + 'টা শব্দ, আপনি পেয়েছেন ' + bn(n) + 'টা।';
        const all = $('all'); all.textContent = '';
        [...words.keys()].forEach((w) => {
            const li = document.createElement('li');
            const got = store.found.includes(w);
            li.className = 'rounded-full border-2 px-3 py-1 font-serif text-lg ' + (got ? 'border-navy bg-navy text-white' : 'border-rule bg-white text-ink2');
            li.textContent = (got ? '✓ ' : '') + w; all.appendChild(li);
        });
        $('wa').href = 'https://wa.me/?text=' + encodeURIComponent(shareText());
        const url = root.dataset.scoreUrl;
        if (url) {
            fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.scoreToken },
                body: JSON.stringify({ game: 'jal', day: data.date, seconds: Math.max(1, Math.min(data.seconds, Math.round((Date.now() - store.start) / 1000))), words: store.found }) })
                .then((r) => r.ok ? r.json() : null).then((j) => { if (window.hpAfter) window.hpAfter(root, j); }).catch(() => {});
        }
    }
    $('share').addEventListener('click', async () => {
        const t = shareText();
        if (navigator.share) { try { await navigator.share({ text: t }); } catch (e) { /* closed */ } return; }
        try { await navigator.clipboard.writeText(t); $('share-msg').textContent = 'কপি হয়েছে। যেখানে খুশি পেস্ট করুন।'; } catch (e) { location.href = $('wa').href; }
    });
    window.addEventListener('resize', paint);

    if (store.ended) finish();
    else if (store.start) begin();
    else paint();
})();
