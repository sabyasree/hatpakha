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
                if (kind === 'save') {
                    btn.classList.toggle('bg-navy', d.on); btn.classList.toggle('text-white', d.on);
                    hpOffline(d.on ? { type: 'keep', urls: [location.pathname] } : { type: 'drop', url: location.pathname });   // "পরে পড়ব" offline
                }
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
// "পরে পড়ব" works offline: the reading list's pieces are handed to the service worker to keep (D132).
function hpOffline(msg) {
    navigator.serviceWorker?.ready.then((r) => r.active?.postMessage(msg)).catch(() => {});
}
const hpSavedLinks = [...document.querySelectorAll('[data-saved-list] a[href]')].map((a) => new URL(a.href).pathname);
if (hpSavedLinks.length) hpOffline({ type: 'keep', urls: hpSavedLinks });
document.querySelectorAll('[data-saved-list] form[data-undoable]').forEach((f) => f.addEventListener('submit', () => {
    const a = f.closest('[data-row]')?.querySelector('a[href]');
    if (a) hpOffline({ type: 'drop', url: new URL(a.href).pathname });
}));

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

// ---- D127/D128: Hatpakha as an app -------------------------------------------------------------------
// The service worker lets the site open full screen from the home screen and keeps pieces already read for
// offline reading (never the desk, accounts or forms). /sw.js is served by the app, never cached.
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
}

const hpStandalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
// Opening Hatpakha clears the number on its icon and the notifications already seen (D128).
const hpClearBadge = () => {
    if (document.visibilityState !== 'visible') return;
    navigator.clearAppBadge?.().catch(() => {});
    navigator.serviceWorker?.getRegistration().then((r) => r?.getNotifications()).then((list) => list?.forEach((n) => n.close())).catch(() => {});
};
hpClearBadge();
document.addEventListener('visibilitychange', hpClearBadge);
const hpIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const hpStore = { get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } } };

// Chrome's own "install" offer: kept so any "যোগ করুন" button on the page can use it (the box, the footer, /app/).
let hpInstallEvent = null;
const hpInstallButtons = () => document.querySelectorAll('[data-install-now]');
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    hpInstallEvent = e;
    hpInstallButtons().forEach((b) => { if (b.tagName === 'BUTTON') b.hidden = false; });
    document.dispatchEvent(new Event('hp:installable'));
});
const hpInstall = async () => {
    if (!hpInstallEvent) return false;
    const e = hpInstallEvent;
    hpInstallEvent = null;
    e.prompt();
    const { outcome } = await e.userChoice;
    if (outcome !== 'accepted') hpStore.set('hp.install.later', String(Date.now()));
    return true;
};
document.addEventListener('click', async (ev) => {
    const b = ev.target.closest('[data-install-now]');
    if (!b || !hpInstallEvent) return;   // no offer from the browser: the link goes to /app/ with the steps
    ev.preventDefault();
    await hpInstall();
});
// Installed app: ask Chrome to fetch the newest pieces once or twice a day, for offline reading (D132).
if (hpStandalone() && 'serviceWorker' in navigator) {
    navigator.serviceWorker.ready.then(async (reg) => {
        if (!('periodicSync' in reg)) return;
        const perm = await navigator.permissions?.query({ name: 'periodic-background-sync' }).catch(() => null);
        if (perm?.state === 'granted') await reg.periodicSync.register('hp-fresh', { minInterval: 12 * 3600 * 1000 }).catch(() => {});
    }).catch(() => {});
}
if (hpStandalone()) {
    // Inside the app: no "add to phone" buttons; /app/ says it's done.
    document.querySelectorAll('[data-app-badges], [data-app-link]').forEach((el) => { el.hidden = true; });
    document.querySelector('[data-app-installed]')?.removeAttribute('hidden');
}
window.addEventListener('appinstalled', () => {
    document.querySelector('[data-install]')?.setAttribute('hidden', '');
    hpInstallButtons().forEach((b) => { if (b.tagName === 'BUTTON') b.hidden = true; });
});

// "Keep Hatpakha on your phone": phones only, from the third page a reader opens, and not again for 30 days after "পরে".
(() => {
    const box = document.querySelector('[data-install]');
    if (!box || hpStandalone() || !window.matchMedia('(max-width: 767px)').matches || location.pathname.startsWith('/app')) return;
    const views = Number(hpStore.get('hp.views') || 0) + 1;
    hpStore.set('hp.views', String(views));
    const later = Number(hpStore.get('hp.install.later') || 0);
    if (views < 3 || Date.now() - later < 30 * 864e5) return;
    const hide = () => { box.hidden = true; hpStore.set('hp.install.later', String(Date.now())); };
    box.querySelector('[data-install-later]').addEventListener('click', hide);
    box.querySelector('[data-install-ok]').addEventListener('click', hide);
    if (hpIos) {
        // Safari has no install button: show the steps instead (other iPhone browsers rarely can).
        if (!/safari/i.test(navigator.userAgent) || /crios|fxios|edgios/i.test(navigator.userAgent)) return;
        box.querySelector('[data-install-android]').hidden = true;
        box.querySelector('[data-install-ios]').hidden = false;
        box.querySelector('[data-install-go]').hidden = true;
        box.querySelector('[data-install-ok]').hidden = false;
        box.hidden = false;
        return;
    }
    const show = () => { box.hidden = false; };
    if (hpInstallEvent) show(); else document.addEventListener('hp:installable', show, { once: true });
    box.querySelector('[data-install-go]').addEventListener('click', () => { box.hidden = true; hpInstall(); });
})();

// Phone notifications (D128): any [data-push-topic] button turns one topic on or off for this browser,
// with or without an account ("account" = the signed-in reader's follows; "daily"; "serial:N"; "author:N").
const hpPush = (() => {
    const keyMeta = document.querySelector('meta[name="hp-push-key"]');
    const supported = !!keyMeta && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const post = async (path, body) => {
        const token = await hpFreshToken();
        const r = await fetch(path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token || '' }, body: JSON.stringify(body || {}) });
        return r.ok ? r.json() : Promise.reject(r.status);
    };
    const key = () => {
        const s = keyMeta.content.replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(s + '='.repeat((4 - (s.length % 4)) % 4)), (c) => c.charCodeAt(0));
    };
    const subscription = async () => (await navigator.serviceWorker.ready).pushManager.getSubscription();
    /** Ask permission (needs a tap) and turn these topics on. Returns the browser's topics, or null if not allowed. */
    const on = async (add) => {
        if ((await Notification.requestPermission()) !== 'granted') return null;
        const sub = (await subscription()) || await (await navigator.serviceWorker.ready).pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key() });
        return (await post('/push/', { ...sub.toJSON(), add })).topics || [];
    };
    const off = async (topic) => {
        const sub = await subscription();
        if (!sub) return [];
        const topics = (await post('/push/off/', { endpoint: sub.endpoint, topics: [topic] })).topics || [];
        if (!topics.length) await sub.unsubscribe().catch(() => {});
        return topics;
    };
    const state = async () => {
        const sub = await subscription();
        return sub ? ((await post('/push/state/', { endpoint: sub.endpoint }).catch(() => ({ topics: [] }))).topics || []) : [];
    };
    return { supported, post, subscription, on, off, state };
})();

(() => {
    const buttons = [...document.querySelectorAll('[data-push-topic]')];
    if (!buttons.length || !document.querySelector('meta[name="hp-push-key"]')) return;
    const note = (sel, show) => document.querySelectorAll(sel).forEach((el) => { el.hidden = !show; });
    if (!hpPush.supported) {
        if (hpIos && !hpStandalone()) {
            // iPhone: notifications only after "Add to Home Screen". The button shows the way.
            buttons.forEach((b) => { b.hidden = false; b.addEventListener('click', () => { location.href = '/app/#iphone'; }); });
            note('[data-push-ios]', true);
        } else {
            note('[data-push-unsupported]', true);
        }
        return;
    }
    let topics = [];
    const testBtn = document.querySelector('[data-push-test]');
    const status = document.querySelector('[data-push-status]');
    const say = (t) => { if (status) status.textContent = t; };
    const paint = () => {
        buttons.forEach((b) => {
            const onNow = topics.includes(b.dataset.pushTopic);
            b.textContent = onNow ? b.dataset.on : b.dataset.off;
            b.setAttribute('aria-pressed', onNow ? 'true' : 'false');
            b.hidden = false;
            b.disabled = false;
        });
        if (testBtn) testBtn.hidden = !topics.length;
        if (Notification.permission === 'denied') say('এই ব্রাউজারে আগে "না" বলা আছে। ব্রাউজারের সেটিংসে hatpakha.com-এর Notifications চালু করে আবার আসুন।');
        else say(topics.length ? '✓ এই ফোনে খবর চালু আছে।' : 'এই ফোনে এখন বন্ধ।');
    };
    document.addEventListener('hp:push', (e) => { topics = e.detail; paint(); });
    buttons.forEach((b) => b.addEventListener('click', async () => {
        const topic = b.dataset.pushTopic;
        b.disabled = true;
        try {
            const r = topics.includes(topic) ? await hpPush.off(topic) : await hpPush.on([topic]);
            if (r) topics = r;
            paint();
        } catch (e) {
            b.disabled = false;
            say('এখন চালু করা গেল না। একটু পরে আবার চেষ্টা করুন।');
        }
    }));
    testBtn?.addEventListener('click', async () => {
        testBtn.disabled = true;
        const sub = await hpPush.subscription();
        const r = sub ? await hpPush.post('/push/test/', { endpoint: sub.endpoint }).catch(() => ({ ok: false })) : { ok: false };
        say(r.ok ? '✓ পাঠানো হল। কয়েক সেকেন্ডের মধ্যে খবরটি আসার কথা।' : 'পাঠানো গেল না। বন্ধ করে আবার চালু করে দেখুন।');
        testBtn.disabled = false;
    });
    hpPush.state().then((t) => { topics = t; paint(); }).catch(() => paint());
})();

// Right after adding Hatpakha to the phone, or on its first opening from the home screen: ask once, with one tap
// ("হ্যাঁ, জানান" = the daily note, plus the reader's follows when signed in). Never asked again after an answer.
(() => {
    const box = document.querySelector('[data-push-ask]');
    if (!box || !hpPush.supported) return;
    const answered = () => hpStore.get('hp.push.asked') || Notification.permission !== 'default';
    const show = async () => {
        if (answered() || (await hpPush.state()).length) return;
        document.querySelector('[data-install]')?.setAttribute('hidden', '');
        box.hidden = false;
    };
    box.querySelector('[data-push-ask-no]').addEventListener('click', () => { hpStore.set('hp.push.asked', 'no'); box.hidden = true; });
    box.querySelector('[data-push-ask-yes]').addEventListener('click', async (e) => {
        e.currentTarget.disabled = true;
        hpStore.set('hp.push.asked', 'yes');
        const topics = await hpPush.on(box.hasAttribute('data-account') ? ['daily', 'account'] : ['daily']).catch(() => null);
        box.querySelector('[data-push-ask-msg]').textContent = topics ? '✓ চালু হল। সন্ধ্যায় নতুন লেখা থাকলে খবর আসবে।' : 'চালু হল না। পরে "ফোনে হাতপাখা" পাতা থেকে চালু করতে পারবেন।';
        if (topics) document.dispatchEvent(new CustomEvent('hp:push', { detail: topics }));
        setTimeout(() => { box.hidden = true; }, 2500);
    });
    window.addEventListener('appinstalled', () => setTimeout(show, 800));
    if (hpStandalone()) setTimeout(show, 1500);
})();
