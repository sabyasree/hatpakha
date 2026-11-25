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
    if (fb) fb.addEventListener('click', async () => {
        fb.disabled = true;
        const touch = matchMedia('(pointer: coarse)').matches;
        const tab = touch ? null : window.open('https://www.facebook.com/', '_blank');   // opened on the click itself, or it is blocked
        const copied = await copy(fb.dataset.caption);
        try {
            const f = await window.hpCard.file(sheet, fb.dataset.certFb);
            if (touch && navigator.canShare && navigator.canShare({ files: [f] })) {
                say(copied ? '✓ পোস্টের লেখা কপি হয়েছে। Facebook বেছে নিয়ে লেখার জায়গায় পেস্ট করুন।' : 'Facebook বেছে নিন।');
                await navigator.share({ files: [f], text: fb.dataset.caption }).catch(() => {});
            } else {
                await window.hpCard.download(sheet, fb.dataset.certFb);
                if (tab) tab.opener = null;
                say('✓ ছবিটি নামানো হয়েছে' + (copied ? ', পোস্টের লেখাও কপি হয়েছে' : '') + '। ফেসবুকে "ছবি/ভিডিও" দিয়ে ছবিটি দিন, তারপর লেখাটি পেস্ট করুন।');
            }
        } finally { fb.disabled = false; }
    });
    // From the "certificate ready" e-mail (?share=1): straight to the share box.
    if (new URLSearchParams(location.search).has('share')) document.getElementById('share')?.scrollIntoView({ block: 'center' });
    const pr = document.querySelector('[data-cert-print]');
    if (pr) pr.addEventListener('click', () => window.print());
})();
