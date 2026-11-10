// Read counter (D54): one beacon per page view, after the page has been visible for 5 seconds.
// Sends only the piece id and the kind of source; no cookie, nothing about the reader.
const article = document.querySelector('[data-view]');
if (article && navigator.sendBeacon) {
    const ref = document.referrer ? new URL(document.referrer).hostname : '';
    const src = !ref ? '' : ref === location.hostname ? 'internal'
        : /(^|\.)(google|bing|duckduckgo|yahoo|yandex|ecosia)\./.test(ref) ? 'search'
        : /(^|\.)(facebook|fb|instagram|whatsapp|t|twitter|x|linkedin|reddit|youtube)\.(com|me|co)$/.test(ref) || /^l\.facebook\.com$/.test(ref) ? 'social' : '';
    let sent = false;
    const send = () => {
        if (sent || document.visibilityState !== 'visible') return;
        sent = true;
        const data = new FormData();
        data.append('s', src);
        navigator.sendBeacon('/v/' + article.dataset.view, data);
    };
    setTimeout(send, 5000);
}

// Profile photo / cover: show the chosen picture straight away (D59). The server re-checks and crops it.
document.querySelectorAll('[data-preview-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const kind = input.dataset.previewInput;
        const img = document.querySelector(`[data-preview="${kind}"]`);
        const file = input.files && input.files[0];
        if (!img || !file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
        // data: URL (the site's CSP allows data: images, not blob:)
        const reader = new FileReader();
        reader.onload = () => {
            img.src = reader.result;
            img.classList.remove('hidden');
            document.querySelector(`[data-preview-fallback="${kind}"]`)?.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    });
});

// Turnstile on the footer form (D60): loaded only when the visitor starts typing there, so ordinary
// page views never load Cloudflare's script. Explicit render keeps the widget's action.
document.querySelectorAll('.cf-turnstile-lazy').forEach((box) => {
    const form = box.closest('form');
    if (!form) return;
    let started = false;
    const render = () => window.turnstile.render(box, {
        sitekey: box.dataset.sitekey, action: box.dataset.action, size: box.dataset.size, theme: box.dataset.theme, language: 'bn',
        appearance: box.dataset.appearance || 'always',
    });
    // D74: the check is often invisible; if the visitor sends before it has finished, wait a moment for it.
    form.addEventListener('submit', (e) => {
        const token = form.querySelector('[name="cf-turnstile-response"]');
        if (!started || (token && token.value) || form.dataset.waited) return;
        e.preventDefault();
        const by = e.submitter && e.submitter.form === form ? e.submitter : undefined;
        form.dataset.waited = '1';
        let tries = 0;
        const wait = setInterval(() => {
            const t = form.querySelector('[name="cf-turnstile-response"]');
            if ((t && t.value) || ++tries > 40) { clearInterval(wait); form.requestSubmit ? form.requestSubmit(by) : form.submit(); }
        }, 250);
    });
    form.addEventListener('focusin', () => {
        if (started) return;
        started = true;
        if (window.turnstile) { render(); return; }
        if (document.querySelector('script[src*="turnstile/v0/api.js"]')) {
            // The page already loads it (e.g. the comment form): wait for it instead of loading twice.
            const wait = setInterval(() => { if (window.turnstile) { clearInterval(wait); render(); } }, 200);
            return;
        }
        window.hpTurnstileReady = render;
        const s = document.createElement('script');
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=hpTurnstileReady';
        s.async = true;
        document.head.appendChild(s);
    });
});

// Like / save / follow without a page reload (D64). Without JavaScript the forms post normally.
document.querySelectorAll('form[data-toggle]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button');
        btn.disabled = true;
        try {
            const r = await fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value } });
            if (!r.ok) { form.submit(); return; }
            const d = await r.json();
            btn.setAttribute('aria-pressed', d.on ? 'true' : 'false');
            const kind = form.dataset.toggle;
            if (kind === 'like') {
                btn.classList.toggle('bg-red', d.on); btn.classList.toggle('text-white', d.on); btn.classList.toggle('border-red', d.on);
                btn.querySelector('[aria-hidden]').textContent = d.on ? '♥' : '♡';
                const c = btn.querySelector('[data-count]');
                if (c) c.textContent = d.count ? new Intl.NumberFormat('bn-BD').format(d.count) : '';
            } else {
                btn.textContent = d.on ? btn.dataset.on : btn.dataset.off;
                if (kind === 'save') { btn.classList.toggle('bg-navy', d.on); btn.classList.toggle('text-white', d.on); }
                if (kind === 'follow') {
                    btn.classList.toggle('bg-red', !d.on); btn.classList.toggle('text-white', !d.on); btn.classList.toggle('border-2', d.on); btn.classList.toggle('border-navy', d.on);
                }
            }
        } catch (err) { form.submit(); } finally { btn.disabled = false; }
    });
});

// Pages for guests can come from the shared cache (D65), so their form token may be someone else's
// (or none). As soon as a visitor touches a form, fetch their own token and put it in every form.
let hpToken = null;
let hpTokenReady = null;
const hpFreshToken = () => hpTokenReady ??= fetch('/csrf-token', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then((r) => r.json()).then((d) => {
        hpToken = d.token;
        document.querySelectorAll('input[name="_token"]').forEach((i) => { i.value = d.token; });
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', d.token);
        return d.token;
    }).catch(() => null);
const hpIsPostForm = (el) => el?.closest?.('form[method="POST"], form[method="post"]');
['focusin', 'pointerdown', 'keydown'].forEach((t) => document.addEventListener(t, (e) => { if (hpIsPostForm(e.target)) hpFreshToken(); }, true));
document.addEventListener('submit', (e) => {
    const f = e.target;
    if (hpToken || !(f instanceof HTMLFormElement) || f.method.toLowerCase() !== 'post') return;   // token in place: carry on
    e.preventDefault();
    e.stopImmediatePropagation();
    // Keep the button that was pressed (e.g. "জমা দিন" vs "খসড়া রাখুন"): the form and the server both need it.
    const by = e.submitter && e.submitter.form === f ? e.submitter : undefined;
    hpFreshToken().then(() => (f.requestSubmit ? f.requestSubmit(by) : f.submit()));
}, true);

// Reading progress on a piece (mobile UX review): a thin red bar at the very top.
const progress = document.querySelector('[data-progress]');
const readArticle = document.querySelector('article[data-view], article');
if (progress && readArticle) {
    let ticking = false;
    const update = () => {
        const r = readArticle.getBoundingClientRect();
        const total = r.height - window.innerHeight;
        const done = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : 0;
        progress.style.transform = `scaleX(${done})`;
        ticking = false;
    };
    window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    update();
}

// Share: the phone's own share sheet when it has one; otherwise the WhatsApp link works as it is.
document.querySelectorAll('[data-share]').forEach((a) => {
    if (!navigator.share) return;
    a.addEventListener('click', (e) => {
        e.preventDefault();
        navigator.share({ title: a.dataset.shareTitle || document.title, url: a.dataset.share }).catch(() => {});
    });
});

// "দেখান" on password fields (mobile UX review): typing a password on a phone without seeing it is hard.
document.querySelectorAll('input[type="password"]').forEach((input) => {
    const wrap = document.createElement('span');
    wrap.className = 'relative block';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    input.classList.add('pr-24');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'দেখান';
    btn.className = 'absolute right-1.5 top-1/2 h-10 -translate-y-1/2 rounded-full px-3 text-sm font-bold text-sky-deep hover:bg-sky-tint';
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('aria-label', 'পাসওয়ার্ড দেখান');
    btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'লুকান' : 'দেখান';
        btn.setAttribute('aria-pressed', String(show));
        input.focus();
    });
    wrap.appendChild(btn);
});

// Account pages (owner + UX review).
// Settings: open the card the link names (#profile / #security / #letters), one card open at a time.
const cards = [...document.querySelectorAll('[data-settings-card]')];
if (cards.length) {
    const openFromHash = () => {
        const c = cards.find((x) => '#' + x.id === location.hash);
        if (c) { c.open = true; c.scrollIntoView({ block: 'start' }); }
    };
    cards.forEach((c) => c.addEventListener('toggle', () => { if (c.open) cards.forEach((o) => { if (o !== c) o.open = false; }); }));
    if (location.hash) openFromHash(); else if (document.querySelector('[role="alert"]')) cards[0].open = true;
    window.addEventListener('hashchange', openFromHash);
}
// ⋯ menus: one at a time; tapping elsewhere closes them.
document.addEventListener('click', (e) => {
    document.querySelectorAll('details[data-row-menu][open], details[data-menu][open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
});
// Picking an audio file sends it straight away (no second button to find).
document.querySelectorAll('input[data-auto-submit]').forEach((i) => i.addEventListener('change', () => { if (i.files?.length) i.form.requestSubmit ? i.form.requestSubmit() : i.form.submit(); }));
// "আরও দেখুন": add the next 20 rows in place; without JavaScript it is a normal link to the next page.
document.querySelectorAll('a[data-more-rows]').forEach((more) => {
    more.addEventListener('click', async (e) => {
        e.preventDefault();
        const list = document.querySelector('[data-list]');
        more.setAttribute('aria-busy', 'true');
        try {
            const html = await (await fetch(more.href, { credentials: 'same-origin' })).text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            doc.querySelectorAll('[data-list] > [data-row]').forEach((row) => list.appendChild(document.importNode(row, true)));
            const next = doc.querySelector('a[data-more-rows]');
            if (next) { more.href = next.href; more.textContent = next.textContent; } else more.remove();
        } catch { location.href = more.href; }
        more.removeAttribute('aria-busy');
    });
});
// Bottom sheet "বন্ধ" and backdrop close the ⋯ menu.
document.addEventListener('click', (e) => {
    const c = e.target.closest?.('[data-close-menu]');
    if (c) { e.preventDefault(); c.closest('details').open = false; }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('details[data-row-menu][open], details[data-menu][open]').forEach((d) => { d.open = false; });
});
// One-tap remove with "ফিরিয়ে আনুন" (UX review): the row hides, a small message offers undo for a few seconds.
document.querySelectorAll('form[data-undoable]').forEach((form) => {
    const send = () => fetch(form.action, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value },
    }).then((r) => (r.ok ? r.json() : Promise.reject(r.status)));
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const row = form.closest('[data-row]');
        send().then(() => {
            if (row) row.hidden = true;
            document.querySelector('[data-undo-toast]')?.remove();
            const toast = document.createElement('div');
            toast.setAttribute('data-undo-toast', '');
            toast.setAttribute('role', 'status');
            toast.className = 'fixed inset-x-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-50 mx-auto flex max-w-md items-center gap-3 rounded-2xl bg-navy px-4 py-3 font-bold text-white shadow-[4px_4px_0_#CB1B32] md:bottom-6';
            toast.innerHTML = '<span class="flex-1"></span><button type="button" class="h-10 rounded-full bg-paper px-4 text-navy">ফিরিয়ে আনুন</button>';
            toast.querySelector('span').textContent = form.dataset.undoable;
            toast.querySelector('button').addEventListener('click', () => send().then(() => { if (row) row.hidden = false; toast.remove(); }));
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 6000);
        }).catch(() => form.submit());
    });
});
// "সব দেখুন" for followed writers: show the rest in place.
document.querySelector('[data-show-follow]')?.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelectorAll('[data-more-follow]').forEach((li) => { li.hidden = false; });
    e.currentTarget.remove();
});
