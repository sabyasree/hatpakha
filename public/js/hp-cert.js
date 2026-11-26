/* Certificate page (D71): fit the 1600px sheet to the screen; PNG download (hp-card.js) and print-to-PDF. */
(function () {
    const frame = document.querySelector('[data-cert]');
    if (!frame) return;
    const sheet = frame.querySelector('.hp-cert-sheet');
    const fit = () => { sheet.style.transform = 'scale(' + frame.clientWidth / 1600 + ')'; };
    fit();
    window.addEventListener('resize', fit);
    const png = document.querySelector('[data-cert-png]');
    if (png) png.addEventListener('click', async () => {
        png.disabled = true;
        try { await window.hpCard.download(sheet, png.dataset.certPng); } finally { png.disabled = false; }
    });
    // 2026-11-25: sharing the certificate itself. A phone shares the picture as a file (pick Facebook); a computer saves
    // the picture and opens Facebook. The words are copied first: Facebook drops any text a site passes to it.
    const msg = document.querySelector('[data-cert-msg]');
    const say = (t) => { if (msg) msg.textContent = t; };
    const copy = async (t) => { try { await navigator.clipboard.writeText(t); return true; } catch (e) { return false; } };
    document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
        if (await copy(b.dataset.copy)) say(b.dataset.done || '✓ কপি হয়েছে');
    }));
    const fb = document.querySelector('[data-cert-fb]');
    // The writer's own certificate becomes the Facebook link preview (sent once; the server checks and keeps it).
    let picSent = null;
    const sendPicture = () => picSent ??= (async () => {
        if (!fb || !fb.dataset.picUrl) return;
        try {
            const c = await window.hpCard.file(sheet, 'c.png');
            const data = await new Promise((ok) => { const r = new FileReader(); r.onload = () => ok(r.result); r.readAsDataURL(c); });
            await fetch(fb.dataset.picUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': fb.dataset.picToken }, body: JSON.stringify({ png: data }) });
        } catch (e) { /* the piece's own picture stays the preview */ }
    })();
    if (fb && fb.dataset.picUrl) setTimeout(sendPicture, 1200);   // after the fonts have settled
    if (fb) fb.addEventListener('click', async () => {
        fb.disabled = true;
        const touch = matchMedia('(pointer: coarse)').matches;
        // A computer: Facebook's own share window, a ready post with the certificate's preview (opened on the click itself, or it is blocked).
        const tab = touch ? null : window.open('about:blank', '_blank');
        const copied = await copy(fb.dataset.caption);
        try {
            if (touch) {
                const f = await window.hpCard.file(sheet, fb.dataset.certFb);
                if (navigator.canShare && navigator.canShare({ files: [f] })) {
                    say(copied ? '✓ পোস্টের লেখা কপি হয়েছে। Facebook বেছে নিয়ে লেখার জায়গায় পেস্ট করুন।' : 'Facebook বেছে নিন।');
                    await navigator.share({ files: [f], text: fb.dataset.caption }).catch(() => {});
                    return;
                }
                await window.hpCard.download(sheet, fb.dataset.certFb);
                location.href = fb.dataset.fbShare;
                return;
            }
            await sendPicture();
            if (tab) { tab.opener = null; tab.location.href = fb.dataset.fbShare; } else { window.open(fb.dataset.fbShare, '_blank', 'noopener'); }
            say('✓ ফেসবুকের পোস্ট লেখার বাক্স খুলেছে, শংসাপত্রের ছবিসহ।' + (copied ? ' পোস্টের লেখা কপি হয়েছে: বাক্সে পেস্ট করে "Post" টিপুন।' : ' "Post" টিপুন।'));
        } finally { fb.disabled = false; }
    });
    // From the "certificate ready" e-mail (?share=1): straight to the share box.
    if (new URLSearchParams(location.search).has('share')) document.getElementById('share')?.scrollIntoView({ block: 'center' });
    const pr = document.querySelector('[data-cert-print]');
    if (pr) pr.addEventListener('click', () => window.print());
})();
