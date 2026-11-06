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
    hpFreshToken().then(() => (f.requestSubmit ? f.requestSubmit() : f.submit()));
}, true);
