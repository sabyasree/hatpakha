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

// D166 "আমার পড়া": for a signed-in reader who keeps it on, how far they read this piece (the furthest point, sent when
// they leave), and "যেখানে থেমেছিলেন" opening the piece at that point (#resume-60).
const tracked = document.querySelector('[data-read-track]');
if (tracked) {
    let furthest = 0;
    let sentAt = -1;
    const measure = () => {
        const r = tracked.getBoundingClientRect();
        if (r.height <= 0) return;
        const seen = Math.min(r.height, window.innerHeight - r.top);
        furthest = Math.max(furthest, Math.round((100 * Math.max(0, seen)) / r.height));
    };
    const flush = () => {
        if (furthest < 3 || furthest === sentAt || !navigator.sendBeacon) return;
        sentAt = furthest;
        const data = new FormData();
        data.append('p', String(Math.min(100, furthest)));
        data.append('_token', tracked.dataset.readToken);
        navigator.sendBeacon(tracked.dataset.readTrack, data);
    };
    // Also save a few seconds after the reader stops scrolling: phones (iOS Safari above all) don't always send on leaving.
    let idle;
    addEventListener('scroll', () => {
        measure();
        clearTimeout(idle);
        if (furthest - Math.max(0, sentAt) >= 10) idle = setTimeout(flush, 3000);
    }, { passive: true });
    addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
    addEventListener('pagehide', flush);
    const resume = location.hash.match(/^#resume-(\d{1,3})$/);
    if (resume) {
        const r = tracked.getBoundingClientRect();
        scrollTo({ top: Math.max(0, scrollY + r.top + (r.height * Math.min(95, +resume[1])) / 100 - innerHeight * 0.6) });
    }
    measure();
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
    const en = document.documentElement.lang === 'en';   // D169
    btn.textContent = en ? 'Show' : 'দেখান';
    btn.className = 'absolute right-1 top-1/2 h-11 -translate-y-1/2 rounded-full px-3 text-sm font-bold text-sky-night hover:bg-sky-tint';
    btn.setAttribute('aria-pressed', 'false');
    btn.setAttribute('aria-label', en ? 'Show password' : 'পাসওয়ার্ড দেখান');
    btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? (en ? 'Hide' : 'লুকান') : (en ? 'Show' : 'দেখান');
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
            // D166: the message stays until the reader does something else (older readers need time), with its own ✕.
            toast.innerHTML = '<span class="flex-1"></span><button type="button" class="h-11 rounded-full bg-paper px-4 text-navy">ফিরিয়ে আনুন</button><button type="button" class="h-11 w-11 rounded-full text-xl" aria-label="বন্ধ করুন">✕</button>';
            toast.querySelector('span').textContent = form.dataset.undoable;
            const [undo, close] = toast.querySelectorAll('button');
            undo.addEventListener('click', () => send().then(() => { if (row) row.hidden = false; toast.remove(); }));
            close.addEventListener('click', () => toast.remove());
            document.body.appendChild(toast);
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

// D166: "আরও" in the phone's bottom bar opens the "সব কিছু" sheet. It closes with ✕ বন্ধ করুন, the dim area, Esc,
// "আরও" again, or the phone's back button (opening adds a history step, so back closes the sheet, not the page).
(() => {
    const sheet = document.querySelector('[data-more-sheet]');
    const opener = document.querySelector('[data-more-open]');
    if (!sheet || !opener) return;
    const panel = sheet.querySelector('[role=dialog]');
    let open = false;
    const focusables = () => [...panel.querySelectorAll('a[href], button, input, [tabindex]:not([tabindex="-1"])')].filter((el) => !el.hidden);
    const show = () => {
        if (open) return;
        open = true;
        sheet.hidden = false;
        opener.setAttribute('aria-expanded', 'true');
        document.documentElement.style.overflow = 'hidden';
        document.querySelectorAll('[data-install]').forEach((el) => { el.dataset.wasHidden = el.hidden ? '1' : ''; el.hidden = true; });
        history.pushState({ hpMore: true }, '');
        panel.querySelector('[data-more-close]')?.focus();
    };
    const hide = (fromHistory) => {
        if (!open) return;
        open = false;
        sheet.hidden = true;
        opener.setAttribute('aria-expanded', 'false');
        document.documentElement.style.overflow = '';
        document.querySelectorAll('[data-install]').forEach((el) => { if (el.dataset.wasHidden === '') el.hidden = false; });
        if (!fromHistory && history.state && history.state.hpMore) history.back();
        opener.focus();
    };
    opener.addEventListener('click', () => (open ? hide(false) : show()));
    sheet.querySelectorAll('[data-more-close]').forEach((b) => b.addEventListener('click', () => hide(false)));
    addEventListener('popstate', () => hide(true));
    addEventListener('keydown', (e) => {
        if (!open) return;
        if (e.key === 'Escape') hide(false);
        if (e.key === 'Tab') {
            const f = focusables();
            if (!f.length) return;
            if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
            else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
        }
    });
    // Following a link inside the sheet: first take back the history step the sheet added, then go (so "back" on the
    // next page returns to this page, not to this page with the sheet).
    panel.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || !(history.state && history.state.hpMore)) return;
        e.preventDefault();
        open = false;
        addEventListener('popstate', () => location.assign(a.href), { once: true });
        history.back();
    });
})();

// D166: "লিংক কপি" (the writer page's address): copy, then say so on the button.
document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
    const done = () => { const t = b.textContent; b.textContent = b.dataset.copied || '✓'; setTimeout(() => { b.textContent = t; }, 2500); };
    if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done).catch(() => {});
}));

// D173 (owner): "share this line". A reader selects a line in a piece; we draw a Hatpakha quote card on a canvas
// (plain canvas text, so it works on iPhones too) and hand it to the phone's share sheet, or download it.
(() => {
    const article = document.querySelector('article[data-quote-url]');
    const pill = document.querySelector('[data-quote-pill]');
    const dialog = document.querySelector('[data-quote-dialog]');
    if (!article || !pill || !dialog) return;
    const canvas = dialog.querySelector('[data-quote-canvas]');
    const msg = dialog.querySelector('[data-quote-msg]');
    const { quoteBy: by, quoteTitle: title, quoteUrl: url } = article.dataset;
    const MAX = 280;
    let quote = '';
    let opener = null;

    const picked = () => {
        const sel = getSelection();
        if (!sel || sel.isCollapsed || !sel.rangeCount) return '';
        const body = sel.getRangeAt(0).commonAncestorContainer;
        const el = body.nodeType === 1 ? body : body.parentElement;
        if (!el || !el.closest('.prose-bn, .poem-bn') || !article.contains(el)) return '';
        const text = sel.toString().replace(/[ \t ]+/g, ' ').replace(/\s*\n\s*/g, '\n').trim();
        if (text.length > MAX) return 'long';
        return text.length >= 8 ? text : '';
    };
    const label = pill.innerHTML;
    let timer;
    document.addEventListener('selectionchange', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (!dialog.hidden) return;
            const p = picked();
            pill.hidden = !p;
            if (p === 'long') pill.textContent = 'একটু ছোট অংশ বেছে নিন (২-৪ লাইন)'; else pill.innerHTML = label;
        }, 250);
    });

    const wrap = (g, text, width) => {
        const lines = [];
        for (const para of text.split('\n')) {
            let line = '';
            for (const word of para.split(' ')) {
                const next = line ? line + ' ' + word : word;
                if (line && g.measureText(next).width > width) { lines.push(line); line = word; } else line = next;
            }
            lines.push(line);
        }
        return lines;
    };
    const draw = async () => {
        const g = canvas.getContext('2d');
        const W = 1080;
        try {
            await Promise.all([document.fonts.load("600 60px 'Noto Serif Bengali'", quote), document.fonts.load("64px 'Galada'", 'হাতপাখা'), document.fonts.load("700 30px 'Anek Bangla'", by + title)]);
        } catch (e) { /* the system's Bengali font is used */ }
        g.fillStyle = '#FBEBD8'; g.fillRect(0, 0, W, W);
        g.textBaseline = 'alphabetic';
        g.fillStyle = '#CB1B32'; g.font = "64px 'Galada', 'Anek Bangla', sans-serif"; g.fillText('হাতপাখা', 64, 124);
        let size = 68; let lines = [];
        for (; size >= 34; size -= 4) {
            g.font = `600 ${size}px 'Noto Serif Bengali', serif`;
            lines = wrap(g, quote, W - 176);
            if (lines.length * size * 1.5 <= 600) break;
        }
        const lh = size * 1.5;
        let y = 230 + (600 - lines.length * lh) / 2 + size;
        g.fillStyle = '#CB1B32'; g.font = "700 150px 'Noto Serif Bengali', serif"; g.fillText('“', 76, y - size * 0.35);
        g.fillStyle = '#13212C'; g.font = `600 ${size}px 'Noto Serif Bengali', serif`;
        for (const l of lines) { g.fillText(l, 88, y); y += lh; }
        g.fillStyle = '#CB1B32'; g.fillRect(88, 880, W - 176, 4);
        g.fillStyle = '#13212C'; g.font = "700 32px 'Anek Bangla', sans-serif";
        let credit = by ? by + ' · ' + title : title;
        while (g.measureText(credit).width > W - 176 && credit.length > 10) credit = credit.slice(0, -2).trimEnd() + '…';
        g.fillText(credit, 88, 940);
        g.fillStyle = '#4D5A63'; g.font = "600 26px 'Anek Bangla', sans-serif";
        g.fillText('hatpakha.com', 88, 1010);
        const issn = 'ISSN 2321-3035'; g.fillText(issn, W - 88 - g.measureText(issn).width, 1010);
    };
    const text = () => `“${quote.replace(/\n/g, ' / ')}”\n— ${by ? by + ', ' : ''}${title}\n${url}`;
    const file = () => new Promise((ok) => canvas.toBlob((b) => ok(b ? new File([b], 'hatpakha-line.png', { type: 'image/png' }) : null), 'image/png'));
    const close = () => { dialog.hidden = true; document.documentElement.style.overflow = ''; opener?.focus?.(); };

    pill.addEventListener('click', async () => {
        quote = picked();
        if (quote === 'long') return;
        if (!quote) { pill.hidden = true; return; }
        opener = pill; pill.hidden = true; msg.textContent = '';
        dialog.querySelector('[data-quote-wa]').href = 'https://wa.me/?text=' + encodeURIComponent(text());
        dialog.hidden = false; document.documentElement.style.overflow = 'hidden';
        await draw();
        dialog.querySelector('[data-quote-share]').focus();
    });
    dialog.addEventListener('click', (e) => { if (e.target === dialog) close(); });
    dialog.querySelector('[data-quote-close]').addEventListener('click', close);
    addEventListener('keydown', (e) => { if (e.key === 'Escape' && !dialog.hidden) close(); });
    const download = async () => {
        const a = document.createElement('a');
        a.download = 'hatpakha-line.png'; a.href = canvas.toDataURL('image/png'); a.click();
        msg.textContent = 'ছবিটি নামানো হয়েছে। WhatsApp, ফেসবুক বা ইনস্টাগ্রামে দিন।';
    };
    dialog.querySelector('[data-quote-download]').addEventListener('click', download);
    dialog.querySelector('[data-quote-share]').addEventListener('click', async () => {
        const f = await file();
        if (f && navigator.canShare && navigator.canShare({ files: [f] })) {
            try { await navigator.share({ files: [f], text: text() }); } catch (e) { /* closed the sheet */ }
            return;
        }
        if (navigator.share) {
            try { await navigator.share({ text: text(), url }); return; } catch (e) { return; }
        }
        download();
    });
})();
