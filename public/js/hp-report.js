/* D179: "report a word" under a finished game. window.hpReport(mount, game, [{ word, clue }]) */
window.hpReport = function (mount, game, list) {
    if (!mount || mount.dataset.ready) return;
    mount.dataset.ready = '1';
    const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text) e.textContent = text; return e; };
    const box = el('details', 'mt-4 rounded-2xl border border-rule bg-white p-3');
    const sum = el('summary', 'flex min-h-11 cursor-pointer items-center font-bold', 'কোনো শব্দ বা সূত্রে ভুল দেখলেন? জানান');
    const form = el('form', 'mt-2 grid gap-3');
    const pick = el('select', 'h-12 w-full rounded-xl border-2 border-navy bg-white px-3 text-lg');
    pick.setAttribute('aria-label', 'কোন শব্দ');
    list.forEach((w) => { const o = el('option', '', w.word + (w.clue ? ' — ' + w.clue.slice(0, 40) : '')); o.value = w.word; pick.appendChild(o); });
    const reasons = el('div', 'grid gap-1');
    [['spelling', 'বানান ভুল'], ['clue', 'সূত্র বা ধাঁধা ঠিক নয়'], ['hard', 'শব্দটা অচেনা বা খুব কঠিন'], ['other', 'অন্য কিছু']].forEach(([v, label], i) => {
        const l = el('label', 'flex min-h-11 items-center gap-3');
        const r = el('input', 'h-5 w-5 flex-none'); r.type = 'radio'; r.name = 'hp-report-reason'; r.value = v; r.checked = i === 0;
        l.append(r, el('span', '', label)); reasons.appendChild(l);
    });
    const note = el('input', 'h-12 w-full rounded-xl border-2 border-navy bg-white px-3');
    note.maxLength = 300; note.placeholder = 'ঠিকটা কী হবে? (ইচ্ছে হলে লিখুন)'; note.setAttribute('aria-label', 'ঠিকটা কী হবে');
    const send = el('button', 'h-12 rounded-full bg-navy px-6 font-bold text-white', 'জানান');
    const msg = el('p', 'min-h-5 text-sm font-bold text-sky-night'); msg.setAttribute('role', 'status');
    form.append(pick, reasons, note, send, msg);
    form.addEventListener('submit', async (e) => {
        e.preventDefault(); send.disabled = true;
        try {
            const t = await fetch('/csrf-token', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json());
            const res = await fetch('/khela/report', { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': t.token },
                body: JSON.stringify({ game, word: pick.value, reason: form.querySelector('input[type=radio]:checked').value, note: note.value }) });
            msg.textContent = res.ok ? 'ধন্যবাদ! দেখে ঠিক করে নেওয়া হবে।' : (res.status === 429 ? 'একটু পরে আবার চেষ্টা করুন।' : 'এখন নেওয়া গেল না।');
            if (res.ok) note.value = '';
        } catch (err) { msg.textContent = 'এখন নেওয়া গেল না। নেট আছে কি না দেখুন।'; }
        send.disabled = false;
    });
    box.append(sum, form); mount.appendChild(box);
};
