// Hatpakha's service worker (D127). Served from /sw.js by App\Http\Controllers\AppController, which puts the
// release version on the first line. It never touches the desk, accounts, forms or anything personal.
//   Pages:  network first; a guest page (Cache-Control: public) is kept so it can be read again offline (last 40).
//   Build files and fonts (hashed names): cache first, last 80.  Our own images: cache first, last 150.
//   A new release changes VERSION, so the browser installs the new worker (and refreshes the offline page).
//   Push:   shows Hatpakha's notification; a tap opens the piece (only our own addresses); the app icon shows how many wait.
/* global VERSION */
const PAGES = 'hp-pages-v1';
const STATIC = 'hp-static-v1';   // build files have hashed names, so old ones stay valid for pages kept offline
const IMAGES = 'hp-img-v1';
const OFFLINE = '/offline.html';
const PRIVATE = /^\/(push|desk|livewire|filament|account|login|logout|register|forgot-password|reset-password|email|submit|search|newsletter|csrf-token|chat|v|like|save|follow|up|issue-print|certificate|webhooks|chithi|view-as|sw\.js)(\/|$)/;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll([OFFLINE, '/images/app/icon-192.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((names) => Promise.all(names.filter((n) => n.startsWith('hp-') && ![PAGES, STATIC, IMAGES].includes(n)).map((n) => caches.delete(n))))
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
        }).catch(async () => (await caches.match(url.origin + url.pathname, { cacheName: PAGES })) || caches.match(OFFLINE)));
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
        data: { url },
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
    const url = event.notification.data && event.notification.data.url;
    if (!url || !url.startsWith(self.location.origin + '/')) return;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        const open = list.find((c) => c.url === url);
        return open ? open.focus() : self.clients.openWindow(url);
    }));
});
