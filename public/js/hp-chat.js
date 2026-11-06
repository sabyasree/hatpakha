/* Submission-page helper chat (D52). Text only: answers are inserted with textContent, never as HTML. */
(function () {
    const root = document.getElementById('hp-chat');
    if (!root) return;
    const box = document.getElementById('hp-chat-box'), log = document.getElementById('hp-chat-log');
    const form = document.getElementById('hp-chat-form'), input = document.getElementById('hp-chat-q');
    const history = [];
    const say = (text, mine) => {
        const p = document.createElement('p');
        p.className = mine ? 'ml-8 rounded-xl bg-peach-light p-2.5' : 'mr-8 rounded-xl bg-sky-tint p-2.5 text-sky-night';
        p.textContent = text;
        log.appendChild(p);
        log.scrollTop = log.scrollHeight;
        return p;
    };
    document.getElementById('hp-chat-open').addEventListener('click', () => { box.hidden = !box.hidden; if (!box.hidden) input.focus(); });
    document.getElementById('hp-chat-close').addEventListener('click', () => { box.hidden = true; });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const q = input.value.trim();
        if (!q) return;
        input.value = '';
        say(q, true);
        const wait = say('…', false);
        try {
            // The page may come from the shared cache (D65): get this visitor's own token first.
            if (!root.dataset.fresh) {
                const t = await fetch('/csrf-token', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((x) => x.json()).catch(() => null);
                if (t && t.token) { root.dataset.token = t.token; root.dataset.fresh = '1'; }
            }
            const r = await fetch(root.dataset.url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.token },
                body: JSON.stringify({ question: q, history: history.slice(-6) }),
            });
            const d = await r.json();
            wait.textContent = d.answer || 'দুঃখিত, এখন উত্তর দিতে পারছি না।';
            if (d.escalated) say('আপনার প্রশ্নটি সম্পাদককে পাঠানো হয়েছে; ই-মেলে উত্তর পাবেন।', false);
            history.push({ role: 'user', text: q }, { role: 'assistant', text: wait.textContent });
        } catch (err) {
            wait.textContent = 'দুঃখিত, সংযোগে সমস্যা। একটু পরে আবার চেষ্টা করুন।';
        }
    });
})();
