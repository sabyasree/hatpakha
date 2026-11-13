/* D174: শব্দ-খেলা, the daily word jumble. Taps only. Progress and the streak stay in this browser (localStorage). */
(function () {
    const root = document.querySelector('[data-khela]');
    if (!root) return;
    const data = JSON.parse(root.querySelector('[data-khela-data]').textContent);
    const $ = (s) => root.querySelector('[data-khela-' + s + ']');
    const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
    const answer = (w) => new TextDecoder().decode(Uint8Array.from(atob(w.a), (c) => c.charCodeAt(0)));
    const KEY = 'hp-khela';
    let store = {};
    try { store = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { store = {}; }
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (e) { /* private window: play without saving */ } };
    if (!store.day || store.day.date !== data.date) store.day = { date: data.date, i: 0, results: [], hinted: false, start: Date.now(), end: null };
    const day = store.day;
    const streakNow = () => {
        const s = store.streak || { n: 0, last: null, best: 0 };
        const y = new Date(data.date + 'T00:00:00'); y.setDate(y.getDate() - 1);
        const yesterday = y.toISOString().slice(0, 10);
        return s.last === data.date || s.last === yesterday ? s.n : 0;
    };
    const showStreak = () => { const n = streakNow(); $('streak').textContent = n > 0 ? '🔥 টানা ' + bn(n) + ' দিন' : ''; };

    let placed = [];   // indexes into the word's tiles, in order
    const word = () => data.words[day.i];
    const dots = () => [...root.querySelectorAll('[data-dot]')].forEach((d, i) => {
        d.className = 'flex h-3 flex-1 rounded-full ' + ({ g: 'bg-sky-deep', y: 'bg-peach', r: 'bg-red' }[day.results[i]] || (i === day.i ? 'bg-navy' : 'bg-rule'));
    });
    const tileClass = 'flex h-14 min-w-14 items-center justify-center rounded-2xl border-2 border-navy px-3 font-serif text-2xl font-bold';
    const render = () => {
        const w = word();
        $('count').textContent = 'শব্দ ' + bn(day.i + 1) + ' / ' + bn(data.words.length) + ' · ' + bn(w.n) + ' অক্ষরের';
        $('clue').textContent = 'সূত্র: ' + w.clue;
        const slots = $('slots'); slots.textContent = '';
        for (let k = 0; k < w.n; k++) {
            const b = document.createElement('button');
            b.type = 'button';
            const has = placed[k] !== undefined;
            b.className = tileClass + (has ? ' bg-sky-tint' : ' border-dashed bg-paper text-transparent');
            b.textContent = has ? w.tiles[placed[k]] : '·';
            b.disabled = !has;
            b.setAttribute('aria-label', has ? w.tiles[placed[k]] + ', ফেরত নিন' : 'খালি ঘর');
            if (has) b.addEventListener('click', () => { placed.splice(k, 1); $('msg').textContent = ''; render(); });
            slots.appendChild(b);
        }
        const tiles = $('tiles'); tiles.textContent = '';
        w.tiles.forEach((t, idx) => {
            const b = document.createElement('button');
            b.type = 'button';
            const used = placed.includes(idx);
            b.className = tileClass + (used ? ' invisible' : ' bg-white shadow-[2px_2px_0_#13212C] active:translate-y-0.5');
            b.textContent = t;
            b.disabled = used;
            b.addEventListener('click', () => { if (placed.length < w.n) { placed.push(idx); render(); check(); } });
            tiles.appendChild(b);
        });
        dots();
    };
    const next = (result) => {
        day.results[day.i] = result;
        day.i++; day.hinted = false; placed = [];
        if (day.i >= data.words.length) {
            day.end = Date.now();
            const s = store.streak || { n: 0, last: null, best: 0 };
            if (s.last !== data.date) { s.n = streakNow() + 1; s.last = data.date; s.best = Math.max(s.best || 0, s.n); store.streak = s; }
            save(); finish(); return;
        }
        save(); $('msg').textContent = ''; render();
    };
    const check = () => {
        const w = word();
        if (placed.length < w.n) return;
        const made = placed.map((i) => w.tiles[i]).join('');
        if (made === answer(w)) {
            $('msg').textContent = '✓ ঠিক! ' + made;
            $('msg').className = 'mt-3 min-h-6 text-center font-bold text-sky-night';
            [...$('tiles').children, ...$('slots').children].forEach((b) => { b.disabled = true; });
            setTimeout(() => { $('msg').className = 'mt-3 min-h-6 text-center font-bold'; next(day.hinted ? 'y' : 'g'); }, 900);
        } else {
            $('msg').textContent = 'হয়নি। আর একবার সাজিয়ে দেখুন।';
            $('slots').animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-8px)' }, { transform: 'translateX(8px)' }, { transform: 'translateX(0)' }], { duration: 250 });
            setTimeout(() => { placed = day.hinted ? placed.slice(0, 1) : []; render(); }, 600);
        }
    };
    $('clear').addEventListener('click', () => { placed = day.hinted ? placed.slice(0, 1) : []; $('msg').textContent = ''; render(); });
    $('hint').addEventListener('click', () => {
        const w = word();
        const first = w.tiles.indexOf(new TextDecoder().decode(Uint8Array.from(atob(w.f), (c) => c.charCodeAt(0))));
        if (first < 0) return;
        day.hinted = true; placed = [first]; save();
        $('msg').textContent = 'প্রথম অক্ষর বসিয়ে দেওয়া হল।';
        render();
    });
    $('skip').addEventListener('click', () => {
        const w = word();
        $('msg').textContent = 'উত্তর: ' + answer(w);
        [...$('tiles').children].forEach((b) => { b.disabled = true; });
        setTimeout(() => next('r'), 1600);
    });

    const shareText = () => {
        const good = day.results.filter((r) => r !== 'r').length;
        const secs = Math.max(1, Math.round(((day.end || Date.now()) - day.start) / 1000));
        const time = bn(Math.floor(secs / 60)) + ':' + bn(String(secs % 60).padStart(2, '0'));
        const grid = day.results.map((r) => ({ g: '🟦', y: '🟨', r: '🟥' }[r])).join('');
        return { good, time, grid, text: 'হাতপাখা শব্দ-খেলা #' + bn(data.no) + '\n' + bn(good) + '/' + bn(data.words.length) + ' · ' + time + '\n' + grid + (streakNow() > 1 ? '\n🔥 টানা ' + bn(streakNow()) + ' দিন' : '') + '\nআপনিও খেলুন: ' + data.url };
    };
    const finish = () => {
        $('board').hidden = true; $('done').hidden = false; dots(); showStreak();
        const s = shareText();
        $('cheer').textContent = s.good === data.words.length ? 'সাবাশ! সব কটা পেরেছেন' : (s.good >= 3 ? 'বেশ হয়েছে!' : 'কাল আবার চেষ্টা করুন');
        $('score').textContent = bn(s.good) + ' / ' + bn(data.words.length) + ' · সময় ' + s.time;
        $('grid').textContent = s.grid;
        $('streak-line').textContent = streakNow() > 0 ? '🔥 টানা ' + bn(streakNow()) + ' দিন খেলছেন। কাল এলে ' + bn(streakNow() + 1) + ' হবে।' : '';
        $('wa').href = 'https://wa.me/?text=' + encodeURIComponent(s.text);
        const ul = $('answers'); ul.textContent = '';
        data.words.forEach((w) => {
            const li = document.createElement('li'); li.className = 'py-2';
            const b = document.createElement('strong'); b.className = 'font-serif text-lg'; b.textContent = answer(w);
            const c = document.createElement('span'); c.className = 'block text-sm text-ink2'; c.textContent = w.clue;
            li.append(b, c); ul.appendChild(li);
        });
        const tick = () => {
            const now = new Date(); const ist = new Date(now.getTime() + (330 + now.getTimezoneOffset()) * 60000);
            const left = 86400 - (ist.getHours() * 3600 + ist.getMinutes() * 60 + ist.getSeconds());
            $('next').textContent = '(আর ' + bn(Math.floor(left / 3600)) + ' ঘণ্টা ' + bn(Math.floor((left % 3600) / 60)) + ' মিনিট)';
        };
        tick(); setInterval(tick, 60000);
    };
    $('share').addEventListener('click', async () => {
        const t = shareText().text;
        if (navigator.share) { try { await navigator.share({ text: t }); } catch (e) { /* closed */ } return; }
        try { await navigator.clipboard.writeText(t); $('share-msg').textContent = 'কপি হয়েছে। যেখানে খুশি পেস্ট করুন।'; } catch (e) { location.href = $('wa').href; }
    });

    showStreak();
    if (day.i >= data.words.length) finish(); else { save(); render(); }
})();
