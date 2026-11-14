/* D177: শব্দজব্দ, the daily mini crossword. Tap a square, tap a letter. Progress stays in this browser. */
(function () {
    const root = document.querySelector('[data-jobdo]');
    if (!root) return;
    const data = JSON.parse(root.querySelector('[data-jobdo-data]').textContent);
    const $ = (s) => root.querySelector('[data-jobdo-' + s + ']');
    const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
    const dec = (b) => JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(b), (c) => c.charCodeAt(0))));
    const KEY = 'hp-jobdo' + (data.level && data.level !== 'medium' ? '-' + data.level : '');   // D201: each level keeps its own progress
    const FULL = data.full || 100, COST = data.cost || 10, GAME = data.key || 'cross';
    let st = {};
    try { st = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { st = {}; }
    if (st.date !== data.date) st = { date: data.date, fill: {}, hints: 0, start: Date.now(), end: null, gaveUp: false, sent: false };
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) { /* private window */ } };

    // The solution, the cells of each word and the clue numbers
    const sol = {};
    const words = data.words.map((w, i) => {
        const letters = dec(w.s);
        const cells = letters.map((l, k) => { const key = (w.row + (w.down ? k : 0)) + ',' + (w.col + (w.down ? 0 : k)); sol[key] = l; return key; });
        return { i, clue: w.clue, down: w.down, cells, start: cells[0] };
    });
    const starts = [...new Set(words.map((w) => w.start))].sort((a, b) => { const [ar, ac] = a.split(',').map(Number); const [br, bc] = b.split(',').map(Number); return ar - br || ac - bc; });
    words.forEach((w) => { w.no = starts.indexOf(w.start) + 1; });
    let cur = words[0];      // the chosen word
    let at = words[0].cells[0];   // the chosen square
    let wrong = new Set();

    const solved = (w) => w.cells.every((k) => st.fill[k] === sol[k]);
    const allDone = () => Object.keys(sol).every((k) => st.fill[k] === sol[k]);
    const select = (w, key) => { cur = w; at = key || w.cells.find((k) => !st.fill[k]) || w.cells[0]; render(); };

    const grid = $('grid');
    const cellEls = {};
    for (let r = 0; r < data.size; r++) {
        for (let c = 0; c < data.size; c++) {
            const key = r + ',' + c;
            if (!(key in sol)) { const gap = document.createElement('span'); gap.className = 'aspect-square'; gap.setAttribute('aria-hidden', 'true'); grid.appendChild(gap); continue; }
            const b = document.createElement('button');
            b.type = 'button';
            b.addEventListener('click', () => {
                const mine = words.filter((w) => w.cells.includes(key));
                const next = key === at && mine.length > 1 ? mine.find((w) => w !== cur) : (mine.includes(cur) ? cur : mine[0]);
                select(next, key);
            });
            cellEls[key] = b; grid.appendChild(b);
        }
    }
    const clueLists = { false: $('across'), true: $('down') };
    words.forEach((w) => {
        const li = document.createElement('li');
        const b = document.createElement('button');
        b.type = 'button'; b.dataset.w = w.i;
        b.addEventListener('click', () => { select(w); $('clue').scrollIntoView({ block: 'nearest', behavior: 'smooth' }); });
        li.appendChild(b); clueLists[String(w.down)].appendChild(li);
    });
    const bank = $('bank');
    data.bank.forEach((l) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'flex h-12 min-w-12 items-center justify-center rounded-xl border-2 border-navy bg-white px-2 font-serif text-xl font-bold shadow-[2px_2px_0_#13212C] active:translate-y-0.5';
        b.textContent = l;
        b.addEventListener('click', () => put(l));
        bank.appendChild(b);
    });

    const render = () => {
        Object.entries(cellEls).forEach(([key, b]) => {
            const inWord = cur.cells.includes(key);
            const num = starts.indexOf(key) + 1;
            b.className = 'relative flex aspect-square items-center justify-center rounded-lg border-2 font-serif font-bold leading-none ' + (data.size > 9 ? 'text-[clamp(.7rem,3vw,1.15rem)] ' : 'text-[clamp(.95rem,4.6vw,1.5rem)] ')
                + (key === at ? 'border-red bg-peach' : inWord ? 'border-navy bg-sky-tint' : 'border-navy bg-white')
                + (wrong.has(key) ? ' text-red' : '');
            b.textContent = st.fill[key] || '';
            if (num) { const s = document.createElement('span'); s.className = 'absolute left-0.5 top-0 font-sans text-[10px] font-bold text-ink2'; s.textContent = bn(num); b.appendChild(s); }
            b.setAttribute('aria-label', (num ? 'ঘর ' + bn(num) + ', ' : '') + (st.fill[key] || 'খালি'));
        });
        $('clue').textContent = bn(cur.no) + ' ' + (cur.down ? 'উপর-নিচ' : 'পাশাপাশি') + ' (' + bn(cur.cells.length) + ' অক্ষর): ' + cur.clue;
        root.querySelectorAll('[data-w]').forEach((b) => {
            const w = words[+b.dataset.w];
            b.className = 'flex min-h-11 w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left ' + (w === cur ? 'bg-sky-tint font-bold' : '') + (solved(w) ? ' text-ink2' : '');
            b.textContent = bn(w.no) + '. ' + w.clue + (solved(w) ? ' ✓' : '');
        });
    };
    const step = (dir) => { const i = cur.cells.indexOf(at) + dir; if (i >= 0 && i < cur.cells.length) at = cur.cells[i]; };
    const put = (l) => {
        if (st.end) return;
        st.fill[at] = l; wrong.delete(at); $('msg').textContent = '';
        const nextEmpty = cur.cells.slice(cur.cells.indexOf(at) + 1).find((k) => !st.fill[k]);
        if (nextEmpty) at = nextEmpty; else step(1);
        save();
        if (solved(cur) && !allDone()) { const w = words.find((x) => !solved(x)); if (w) { cur = w; at = w.cells.find((k) => !st.fill[k]) || w.cells[0]; } }
        render();
        if (allDone()) finish(true);
    };
    $('back').addEventListener('click', () => { if (!st.fill[at]) step(-1); delete st.fill[at]; wrong.delete(at); save(); render(); });
    $('reveal').addEventListener('click', () => { if (st.fill[at] === sol[at]) { $('msg').textContent = 'এই ঘরটা ঠিকই আছে।'; return; } st.hints++; put(sol[at]); });
    $('check').addEventListener('click', () => {
        wrong = new Set(Object.keys(st.fill).filter((k) => st.fill[k] !== sol[k]));
        $('msg').textContent = wrong.size ? bn(wrong.size) + 'টি ঘর মিলছে না (লাল)।' : 'এ পর্যন্ত সব ঠিক আছে।';
        render();
    });
    $('giveup').addEventListener('click', () => {
        if (!confirm('পুরো ছকের উত্তর দেখাব? আজকের পয়েন্ট শূন্য হবে।')) return;
        Object.assign(st.fill, sol); st.gaveUp = true; save(); render(); finish(false);
    });

    const result = () => {
        const secs = Math.max(1, Math.round(((st.end || Date.now()) - st.start) / 1000));
        const time = bn(Math.floor(secs / 60)) + ':' + bn(String(secs % 60).padStart(2, '0'));
        const points = st.gaveUp ? 0 : Math.max(10, FULL - COST * st.hints);
        return { secs, time, points, text: 'হাতপাখা শব্দজব্দ #' + bn(data.no) + (data.levelName ? ' (' + data.levelName + ')' : '') + '\n' + (st.gaveUp ? 'আজ পারিনি' : bn(points) + ' পয়েন্ট · ' + time + (st.hints ? ' · ' + bn(st.hints) + 'টি ঘর দেখে' : ' · কোনো সাহায্য ছাড়া')) + '\nআপনিও খেলুন: ' + data.url };
    };
    const finish = (ok) => {
        if (!st.end) { st.end = Date.now(); save(); }
        const r = result();
        $('done').hidden = false;
        ['bank', 'back', 'check', 'reveal', 'giveup'].forEach((k) => { $(k).hidden = true; });
        $('msg').textContent = '';
        $('cheer').textContent = st.gaveUp ? 'কাল আবার চেষ্টা করুন' : (st.hints ? 'বেশ হয়েছে!' : 'সাবাশ! নিজেই পুরো ছক ভরেছেন');
        $('score').textContent = st.gaveUp ? 'আজকের ছকের উত্তর উপরে' : bn(r.points) + ' পয়েন্ট · সময় ' + r.time;
        $('wa').href = 'https://wa.me/?text=' + encodeURIComponent(r.text);
        if (window.hpReport) window.hpReport(root.querySelector('[data-report]'), GAME, words.map((w) => ({ word: w.cells.map((k) => sol[k]).join(''), clue: w.clue })));
        const url = root.dataset.scoreUrl;
        if (url && !st.sent) {
            fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.scoreToken },
                body: JSON.stringify({ game: GAME, day: data.date, seconds: r.secs, hints: st.hints, solved: !st.gaveUp }) })
                .then((x) => x.ok ? x.json() : null).then((j) => {
                    if (!j || !j.ok) return;
                    st.sent = true; save();
                    const el = root.querySelector('[data-khela-board-msg]');
                    if (el) el.textContent = '✓ লিডারবোর্ডে উঠল: আজ ' + bn(j.points) + ' পয়েন্ট';
                }).catch(() => {});
        }
        if (ok) $('done').scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };
    $('share').addEventListener('click', async () => {
        const t = result().text;
        if (navigator.share) { try { await navigator.share({ text: t }); } catch (e) { /* closed */ } return; }
        try { await navigator.clipboard.writeText(t); $('share-msg').textContent = 'কপি হয়েছে। যেখানে খুশি পেস্ট করুন।'; } catch (e) { location.href = $('wa').href; }
    });

    save(); render();
    if (st.end) finish(false);
})();
