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
