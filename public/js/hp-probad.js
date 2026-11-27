/* D243: প্রবাদ পূরণ. Four proverbs a day, one word missing; tap the right one of four. Two tries each.
   Progress stays in this browser (localStorage); a signed-in player's result goes to the leaderboard. */
(function () {
    const root = document.querySelector('[data-probad]');
    if (!root) return;
    const data = JSON.parse(root.querySelector('[data-probad-data]').textContent);
    const $ = (s) => root.querySelector('[data-probad-' + s + ']');
    const bn = (n) => String(n).replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[d]);
    const answer = (q) => new TextDecoder().decode(Uint8Array.from(atob(q.a), (c) => c.charCodeAt(0)));
    const KEY = 'hp-probad';
    let store = {};
    try { store = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { store = {}; }
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (e) { /* private window */ } };
    if (!store.day || store.day.date !== data.date) store.day = { date: data.date, i: 0, results: [], wrong: [], start: Date.now(), end: null };
    const day = store.day;
    const POINTS = { g: 25, y: 10, r: 0 };

    const dots = () => [...root.querySelectorAll('[data-dot]')].forEach((d, i) => {
        d.className = 'flex h-3 flex-1 rounded-full ' + ({ g: 'bg-sky-deep', y: 'bg-peach', r: 'bg-red' }[day.results[i]] || (i === day.i ? 'bg-navy' : 'bg-rule'));
    });
    const sentence = (q, word) => {
        const p = $('text'); p.textContent = '';
        if (q.before) p.append(q.before + ' ');
        const gap = document.createElement('span');
        gap.className = word ? 'rounded-lg bg-sky-tint px-2 text-sky-night' : 'inline-block min-w-20 border-b-4 border-dashed border-red align-baseline';
        gap.textContent = word || ' ';
        p.append(gap);
        if (q.after) p.append(' ' + q.after);
    };
    const optClass = 'flex min-h-16 items-center justify-center rounded-2xl border-2 border-navy px-2 text-center font-serif text-2xl font-bold';
    const render = () => {
        const q = data.items[day.i];
        $('count').textContent = 'প্রবাদ ' + bn(day.i + 1) + ' / ' + bn(data.items.length);
        sentence(q, null);
        $('msg').textContent = day.wrong.length ? 'হয়নি। আর একবার বাছুন।' : '';
        $('meaning').classList.add('hidden'); $('next').hidden = true;
        const box = $('options'); box.textContent = '';
        q.options.forEach((o) => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = o;
            const off = day.wrong.includes(o);
            b.className = optClass + (off ? ' bg-rule text-ink2 line-through' : ' bg-white shadow-[3px_3px_0_#13212C] active:translate-y-0.5');
            b.disabled = off;
            b.addEventListener('click', () => pick(o));
            box.appendChild(b);
        });
        dots();
    };
    const settle = (result) => {
        const q = data.items[day.i];
        day.results[day.i] = result; save();
        sentence(q, answer(q));
        [...$('options').children].forEach((b) => {
            b.disabled = true;
            b.className = optClass + (b.textContent === answer(q) ? ' bg-navy text-white' : ' bg-rule text-ink2');
        });
        $('msg').textContent = result === 'r' ? 'ঠিক শব্দ: ' + answer(q) : (result === 'g' ? '✓ প্রথম বারেই ঠিক! ২৫ পয়েন্ট' : '✓ দ্বিতীয় বারে ঠিক! ১০ পয়েন্ট');
        $('meaning').textContent = 'মানে: ' + q.meaning; $('meaning').classList.remove('hidden');
        const last = day.i >= data.items.length - 1;
        $('next').textContent = last ? 'ফল দেখুন →' : 'পরের প্রবাদ →'; $('next').hidden = false;
        dots();
    };
    const pick = (o) => {
        const q = data.items[day.i];
        if (day.results[day.i]) return;
        if (o === answer(q)) { settle(day.wrong.length ? 'y' : 'g'); return; }
        day.wrong.push(o); save();
        if (day.wrong.length >= 2) { settle('r'); return; }
        render();
    };
    $('next').addEventListener('click', () => {
        day.i++; day.wrong = [];
        if (day.i >= data.items.length) { day.end = Date.now(); save(); finish(); return; }
        save(); render(); $('board').scrollIntoView({ block: 'nearest' });
    });

    const total = () => day.results.reduce((s, r) => s + (POINTS[r] || 0), 0);
    const shareText = () => {
        const grid = day.results.map((r) => ({ g: '🟦', y: '🟨', r: '🟥' }[r])).join('');
        return { grid, text: 'হাতপাখা প্রবাদ পূরণ #' + bn(data.no) + '\n' + bn(total()) + ' / ১০০\n' + grid + '\nআপনি কত পান? খেলুন: ' + data.url };
    };
    const finish = () => {
        $('board').hidden = true; $('done').hidden = false; dots();
        const s = shareText(); const good = day.results.filter((r) => r === 'g').length;
        $('cheer').textContent = good === data.items.length ? 'সাবাশ! চারটেই প্রথম বারে' : (total() >= 50 ? 'বেশ হয়েছে!' : 'কাল আবার চেষ্টা করুন');
        $('score').textContent = bn(total()) + ' / ১০০ পয়েন্ট';
        const gridBox = $('grid'); gridBox.textContent = '';
        day.results.forEach((r) => { const i = document.createElement('i'); i.className = 'block h-8 w-8 rounded-lg border-2 border-navy ' + ({ g: 'bg-sky-deep', y: 'bg-peach', r: 'bg-red' }[r]); gridBox.appendChild(i); });
        $('wa').href = 'https://wa.me/?text=' + encodeURIComponent(s.text);
        const ul = $('answers'); ul.textContent = '';
        data.items.forEach((q) => {
            const li = document.createElement('li'); li.className = 'py-2';
            const b = document.createElement('strong'); b.className = 'font-serif text-lg'; b.textContent = [q.before, answer(q), q.after].filter(Boolean).join(' ');
            const c = document.createElement('span'); c.className = 'block text-sm text-ink2'; c.textContent = q.meaning;
            li.append(b, c); ul.appendChild(li);
        });
        const url = root.dataset.scoreUrl;
        if (url) {
            fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.scoreToken },
                body: JSON.stringify({ game: 'probad', day: data.date, seconds: Math.max(1, Math.round(((day.end || Date.now()) - day.start) / 1000)), results: day.results }) })
                .then((r) => r.ok ? r.json() : null).then((j) => { if (window.hpAfter) window.hpAfter(root, j); }).catch(() => {});
        }
    };
    $('share').addEventListener('click', async () => {
        const t = shareText().text;
        if (navigator.share) { try { await navigator.share({ text: t }); } catch (e) { /* closed */ } return; }
        try { await navigator.clipboard.writeText(t); $('share-msg').textContent = 'কপি হয়েছে। যেখানে খুশি পেস্ট করুন।'; } catch (e) { location.href = $('wa').href; }
    });

    if (day.i >= data.items.length) finish();
    else if (day.results[day.i]) { render(); settle(day.results[day.i]); }
    else { save(); render(); }
})();
