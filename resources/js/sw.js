// Hatpakha's service worker (D127). Served from /sw.js by App\Http\Controllers\AppController, which puts the
// release version on the first line. It never touches the desk, accounts, forms or anything personal.
//   Pages:  network first; a guest page (Cache-Control: public) is kept so it can be read again offline (last 40);
//           "পরে পড়ব" pieces are kept too (up to 100), and the installed app fetches the newest pieces each morning.
//   Build files and fonts (hashed names): cache first, last 80.  Our own images: cache first, last 150.
//   A new release changes VERSION, so the browser installs the new worker (and refreshes the offline page).
//   Push:   shows Hatpakha's notification; a tap opens the piece (only our own addresses); the app icon shows how many wait.
/* global VERSION */
const PAGES = 'hp-pages-v1';
const STATIC = 'hp-static-v1';   // build files have hashed names, so old ones stay valid for pages kept offline
const IMAGES = 'hp-img-v1';
const SAVED = 'hp-saved-v1';   // "পরে পড়ব": pieces the reader saved, kept for offline reading (up to 100)
const OFFLINE = '/offline.html';
const PRIVATE = /^\/(push|desk|livewire|filament|account|login|logout|register|forgot-password|reset-password|email|submit|search|newsletter|csrf-token|chat|v|like|save|follow|up|issue-print|certificate|webhooks|chithi|view-as|sw\.js)(\/|$)/;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll([OFFLINE, '/images/app/icon-192.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((names) => Promise.all(names.filter((n) => n.startsWith('hp-') && ![PAGES, STATIC, IMAGES, SAVED].includes(n)).map((n) => caches.delete(n))))
        .then(() => self.clients.claim()));
});

const trim = async (name, max) => {
    const cache = await caches.open(name);
    const keys = await cache.keys();
    await Promise.all(keys.slice(0, Math.max(0, keys.length - max)).map((k) => cache.delete(k)));
};

const keepable = (res) => res.ok && res.type === 'basic' && /\bpublic\b/.test(res.headers.get('Cache-Control') || '')
    && (res.headers.get('Content-Type') || '').startsWith('text/html');

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin || PRIVATE.test(url.pathname)) return;

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).then((res) => {
            if (keepable(res)) {
                const copy = res.clone();
                const key = url.origin + url.pathname;   // one copy per page, whatever the utm_ labels
                event.waitUntil(caches.open(PAGES).then((c) => c.delete(key).then(() => c.put(key, copy))).then(() => trim(PAGES, 40)));
            }
            return res;
        }).catch(async () => (await caches.match(url.origin + url.pathname, { cacheName: SAVED }))
            || (await caches.match(url.origin + url.pathname, { cacheName: PAGES })) || caches.match(OFFLINE)));
        return;
    }

    if (/^\/(build|fonts)\//.test(url.pathname) || (req.destination === 'image' && /^\/(images|media|storage|wp-content)\//.test(url.pathname))) {
        const name = req.destination === 'image' ? IMAGES : STATIC;
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok && res.type === 'basic') {
                const copy = res.clone();
                event.waitUntil(caches.open(name).then((c) => c.put(req, copy)).then(() => trim(name, name === IMAGES ? 150 : 80)));
            }
            return res;
        })));
    }
});

// Store one of our pages for offline reading, fetched without cookies (the same page every guest sees).
const keep = async (cacheName, path) => {
    const url = new URL(path, self.location.origin);
    if (url.origin !== self.location.origin || PRIVATE.test(url.pathname)) return false;
    try {
        // cache: 'no-store': this guest copy goes only into our offline store, never into the browser's own cache,
        // or a reader who has just signed in could be shown it (2026-11-12, found in the end-to-end check).
        const res = await fetch(url.origin + url.pathname, { credentials: 'omit', cache: 'no-store' });
        if (!keepable(res)) return false;
        await (await caches.open(cacheName)).put(url.origin + url.pathname, res);
        return true;
    } catch (e) { return false; }
};

// Messages from the page: "keep these saved pieces" / "this one was removed from the list".
self.addEventListener('message', (event) => {
    const d = event.data || {};
    if (d.type === 'keep' && Array.isArray(d.urls)) {
        event.waitUntil((async () => {
            for (const u of d.urls.slice(0, 100)) await keep(SAVED, u);
            await trim(SAVED, 100);
        })());
    } else if (d.type === 'drop' && typeof d.url === 'string') {
        const url = new URL(d.url, self.location.origin);
        event.waitUntil(caches.open(SAVED).then((c) => c.delete(url.origin + url.pathname)));
    }
});

// Installed app on Android Chrome: each morning (when the phone allows) fetch the newest pieces, so they open offline.
self.addEventListener('periodicsync', (event) => {
    if (event.tag !== 'hp-fresh') return;
    event.waitUntil((async () => {
        try {
            const xml = await (await fetch('/feed/', { credentials: 'omit', cache: 'no-store' })).text();
            const links = [...xml.matchAll(/<item>[\s\S]*?<link>([^<]+)<\/link>/g)].map((m) => m[1].trim()).slice(0, 8);
            for (const l of links) await keep(PAGES, l);
            await trim(PAGES, 40);
        } catch (e) { /* try again next time */ }
    })());
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = {}; }
    const url = typeof data.url === 'string' && data.url.startsWith(self.location.origin + '/') ? data.url : self.location.origin + '/';
    event.waitUntil(self.registration.showNotification(data.title || 'হাতপাখা', {
        body: data.body || '',
        icon: '/images/app/icon-192.png',
        badge: '/images/app/badge-96.png',
        lang: 'bn',
        tag: data.tag || undefined,
        data: { url, kind: typeof data.kind === 'string' ? data.kind : 'other' },
    }).then(updateBadge));
});

// The number on the app icon (home screen): how many of our notifications are still waiting. iPhone (16.4+, added
// to the home screen) and Android/desktop Chrome show it; elsewhere this does nothing.
const updateBadge = async () => {
    if (!('setAppBadge' in self.navigator)) return;
    const waiting = (await self.registration.getNotifications()).length;
    try {
        if (waiting > 0) await self.navigator.setAppBadge(waiting); else await self.navigator.clearAppBadge();
    } catch (e) { /* not allowed here */ }
};
self.addEventListener('notificationclose', (event) => event.waitUntil(updateBadge()));

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(updateBadge());
    // Count the tap (only the kind of notice: no address, no person), for the desk's numbers.
    const kind = (event.notification.data && event.notification.data.kind) || 'other';
    event.waitUntil(fetch('/push/opened', { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ kind }) }).catch(() => {}));
    const url = event.notification.data && event.notification.data.url;
    if (!url || !url.startsWith(self.location.origin + '/')) return;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        const open = list.find((c) => c.url === url);
        return open ? open.focus() : self.clients.openWindow(url);
    }));
});
