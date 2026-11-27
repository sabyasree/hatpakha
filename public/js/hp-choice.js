/* D328: the four-choice games. One engine for বানান-যাচাই and এক শব্দে প্রকাশ (daily), the পত্রিকা-কুইজ (weekly) and
   সাপ্তাহিক লড়াই (a friend's link). One try a question; the right answer and a short note are shown after each.
   Progress stays in this browser (localStorage), so a reload or a second visit carries on where it stopped. */
(function () {
    const root = document.querySelector('[data-choice]');
    if (!root) return;
    const data = JSON.parse(root.querySelector('[data-c-data]').textContent);
    const $ = (s) => root.querySelector('[data-c-' + s + ']');
    const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
    const dec = (b) => new TextDecoder().decode(Uint8Array.from(atob(b), (c) => c.charCodeAt(0)));
    const answer = (q) => dec(q.a);
    const time = (s) => (s >= 60 ? bn(Math.floor(s / 60)) + ' মিনিট ' : '') + bn(s % 60) + ' সেকেন্ড';
    const KEY = 'hp-c-' + data.key;
    let store = {};
    try { store = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { store = {}; }
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (e) { /* private window */ } };
    if (!store.day || store.day.id !== data.id) store.day = { id: data.id, i: 0, results: [], picked: [], start: null, end: null, sent: false };
    const day = store.day;
    const total = () => day.results.filter((r) => r === 'g').length * data.points;
    const max = data.items.length * data.points;

    const dots = () => [...root.querySelectorAll('[data-dot]')].forEach((d, i) => {
        d.className = 'flex h-3 flex-1 rounded-full ' + ({ g: 'bg-sky-deep', r: 'bg-red' }[day.results[i]] || (i === day.i ? 'bg-navy' : 'bg-rule'));
    });
    const optClass = 'flex min-h-16 items-center justify-center rounded-2xl border-2 border-navy px-2 py-2 text-center font-serif text-[22px] font-bold leading-snug';
    const render = () => {
        const q = data.items[day.i];
        $('count').textContent = data.label + ' ' + bn(day.i + 1) + ' / ' + bn(data.items.length);
        $('prompt').textContent = q.p;
        $('msg').textContent = ''; $('note').hidden = true; $('next').hidden = true;
        const link = $('link'); if (link) link.hidden = true;
        const box = $('options'); box.textContent = '';
        q.o.forEach((o) => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = o;
            b.className = optClass + ' bg-white shadow-[3px_3px_0_#13212C] active:translate-y-0.5';
            b.addEventListener('click', () => pick(o));
            box.appendChild(b);
        });
        dots();
    };
    const settle = () => {
        const q = data.items[day.i];
        const right = answer(q);
        const chosen = day.picked[day.i];
        [...$('options').children].forEach((b) => {
            b.disabled = true;
            b.className = optClass + (b.textContent === right ? ' bg-navy text-white' : (b.textContent === chosen ? ' bg-red text-white line-through' : ' bg-rule text-ink2'));
            if (b.textContent === right) b.textContent = '✓ ' + right;
        });
        $('msg').textContent = day.results[day.i] === 'g' ? '✓ ঠিক! ' + bn(data.points) + ' পয়েন্ট' : 'হয়নি। ঠিক উত্তর: ' + right;
        $('msg').className = 'mt-3 min-h-6 text-center text-lg font-bold ' + (day.results[day.i] === 'g' ? 'text-sky-night' : 'text-red');
        if (q.n) { $('note').textContent = q.n; $('note').hidden = false; }
        const link = $('link');
        if (link && q.l) { link.href = q.l.u; link.textContent = 'লেখাটা পড়ুন: ' + q.l.t + ' →'; link.hidden = false; }
        const last = day.i >= data.items.length - 1;
        $('next').textContent = last ? 'ফল দেখুন →' : 'পরের প্রশ্ন →'; $('next').hidden = false;
        $('next').focus({ preventScroll: true });
        dots();
    };
    const pick = (o) => {
        if (day.results[day.i]) return;
        if (!day.start) day.start = Date.now();
        day.picked[day.i] = o;
        day.results[day.i] = o === answer(data.items[day.i]) ? 'g' : 'r';
        save(); settle();
    };
    $('next').addEventListener('click', () => {
        day.i++;
        if (day.i >= data.items.length) { day.end = day.end || Date.now(); save(); finish(); return; }
        save(); render(); $('board').scrollIntoView({ block: 'nearest' });
    });

    const seconds = () => Math.max(1, Math.round(((day.end || Date.now()) - (day.start || Date.now())) / 1000));
    const shareText = () => {
        const grid = day.results.map((r) => (r === 'g' ? '🟦' : '🟥')).join('');
        return data.title + (data.no ? ' #' + bn(data.no) : '') + '\n' + bn(total()) + ' / ' + bn(max) + '\n' + grid + '\n' + (data.shareLine || 'আপনিও খেলুন: ') + (store.link || data.url);
    };
    const token = () => fetch('/csrf-token', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json()).then((j) => j.token);
    const finish = () => {
        $('board').hidden = true; $('done').hidden = false; dots();
        const good = day.results.filter((r) => r === 'g').length;
        $('cheer').textContent = good === data.items.length ? 'সাবাশ! সবকটাই ঠিক' : (good * 2 >= data.items.length ? 'বেশ হয়েছে!' : (data.again || 'পরের বার আরও ভালো হবে'));
        $('score').textContent = bn(total()) + ' / ' + bn(max) + ' পয়েন্ট';
        const gridBox = $('grid'); gridBox.textContent = '';
        day.results.forEach((r) => { const i = document.createElement('i'); i.className = 'block h-7 w-7 rounded-lg border-2 border-navy ' + (r === 'g' ? 'bg-sky-deep' : 'bg-red'); gridBox.appendChild(i); });
        const ul = $('answers'); ul.textContent = '';
        data.items.forEach((q, i) => {
            const li = document.createElement('li'); li.className = 'py-2';
            const top = document.createElement('span'); top.className = 'flex items-start gap-2';
            const mark = document.createElement('span'); mark.className = 'font-bold ' + (day.results[i] === 'g' ? 'text-sky-night' : 'text-red'); mark.textContent = day.results[i] === 'g' ? '✓' : '✗';
            const b = document.createElement('strong'); b.className = 'font-serif text-lg';
            b.textContent = (q.p && data.showPrompt ? q.p + ': ' : '') + answer(q);
            top.append(mark, b); li.append(top);
            if (q.n) { const c = document.createElement('span'); c.className = 'block ps-6 text-sm text-ink2'; c.textContent = q.n; li.append(c); }
            if (q.l) { const a = document.createElement('a'); a.className = 'block ps-6 text-sm font-bold text-sky-night underline'; a.href = q.l.u; a.textContent = q.l.t; li.append(a); }
            ul.appendChild(li);
        });
        const wa = $('wa'); if (wa) wa.href = 'https://wa.me/?text=' + encodeURIComponent(shareText());
        if (data.mode === 'lorai') { lorai(); return; }
        if (window.hpReport && data.report) window.hpReport(root.querySelector('[data-report]'), data.game, data.items.map((q) => ({ word: answer(q), clue: q.p })));
        const url = root.dataset.scoreUrl;
        if (url) {   // sent again on a later visit too: the server keeps the first, and the answer fills today's points below
            fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.scoreToken },
                body: JSON.stringify({ game: data.game, day: data.date, seconds: seconds(), results: day.results }) })
                .then((r) => r.ok ? r.json() : null).then((j) => { if (j && j.ok) { day.sent = true; save(); } if (window.hpAfter) window.hpAfter(root, j); }).catch(() => {});
        }
    };

    // সাপ্তাহিক লড়াই: the result goes on the link; the maker gets a new link to send
    const table = (plays) => {
        const t = $('table'); if (!t) return; t.textContent = '';
        plays.forEach((p, i) => {
            const li = document.createElement('li');
            li.className = 'flex items-center gap-3 py-2 ' + (p.mine ? 'font-extrabold' : '');
            const rank = document.createElement('span'); rank.className = 'flex h-8 w-8 flex-none items-center justify-center rounded-full ' + (i === 0 ? 'bg-red text-white' : 'border-2 border-navy'); rank.textContent = bn(i + 1);
            const name = document.createElement('span'); name.className = 'min-w-0 flex-1 truncate'; name.textContent = p.name + (p.started ? ' (ডেকেছেন)' : '') + (p.mine ? ' · আপনি' : '');
            const sc = document.createElement('span'); sc.className = 'text-right'; sc.textContent = bn(p.score) + ' · ' + time(p.seconds);
            li.append(rank, name, sc); t.appendChild(li);
        });
    };
    const lorai = async () => {
        const box = $('link-box');
        if (store.link) { showLink(); }
        if (day.sent) { if (data.plays) table(data.plays); const own = $('own'); if (own && !store.link) own.hidden = false; return; }
        try {
            const res = await fetch(data.post, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': await token() },
                body: JSON.stringify({ name: store.name || '', score: total(), seconds: seconds() }) });
            const j = await res.json();
            if (!j.ok) { $('share-msg').textContent = j.error === 'closed' ? 'এই লড়াইয়ের সপ্তাহ শেষ। নতুন লড়াই শুরু করুন।' : 'এখন নম্বর জমা হল না। একটু পরে পাতাটা আবার খুলুন।'; return; }
            day.sent = true;
            if (!data.code) store.link = j.url;
            save(); table(j.plays); if (!data.code) showLink();
            const own = $('own'); if (own && !store.link) own.hidden = false;
        } catch (e) { $('share-msg').textContent = 'ইন্টারনেট নেই মনে হচ্ছে। পাতাটা আবার খুললে নম্বর জমা হবে।'; }
    };
    const showLink = () => {
        const box = $('link-box'); if (!box) return;
        box.hidden = false;
        const u = $('url'); if (u) u.value = store.link;
        const wa = $('wa'); if (wa) wa.href = 'https://wa.me/?text=' + encodeURIComponent(shareText());
    };
    // a friend who answered a challenge makes their own link with the same result, without playing again
    const own = $('own');
    if (own) own.addEventListener('click', async () => {
        own.disabled = true;
        try {
            const res = await fetch(data.create, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': await token() },
                body: JSON.stringify({ name: store.name || '', score: total(), seconds: seconds() }) });
            const j = await res.json();
            if (j.ok) { store.link = j.url; save(); own.hidden = true; showLink(); $('link-box').scrollIntoView({ block: 'nearest' }); return; }
        } catch (e) { /* below */ }
        own.disabled = false; $('share-msg').textContent = 'এখন লিঙ্ক তৈরি হল না। একটু পরে আবার টিপুন।';
    });
    const copy = $('copy');
    if (copy) copy.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText($('url').value); $('share-msg').textContent = 'লিঙ্ক কপি হয়েছে। যেখানে খুশি পেস্ট করুন।'; } catch (e) { $('url').select(); }
    });
    const share = $('share');
    if (share) share.addEventListener('click', async () => {
        const t = shareText();
        if (navigator.share) { try { await navigator.share({ text: t }); } catch (e) { /* closed */ } return; }
        try { await navigator.clipboard.writeText(t); $('share-msg').textContent = 'কপি হয়েছে। যেখানে খুশি পেস্ট করুন।'; } catch (e) { location.href = $('wa').href; }
    });

    // a guest answering a challenge says their name first
    const start = $('start');
    const begin = () => { if (start) start.hidden = true; $('board').hidden = false; render(); };
    if (start && !day.results.length && !store.name && data.needName) {
        $('board').hidden = true; start.hidden = false;
        const input = $('name');
        $('go').addEventListener('click', () => {
            const v = input.value.trim().replace(/\s+/g, ' ');
            if (v.length < 2) { $('name-msg').textContent = 'অন্তত দুই অক্ষরের একটা নাম লিখুন।'; input.focus(); return; }
            store.name = v.slice(0, 30); save(); begin();
        });
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('go').click(); } });
        return;
    }
    if (day.i >= data.items.length) finish();
    else if (day.results[day.i]) { render(); settle(); }
    else { save(); begin(); }
})();
