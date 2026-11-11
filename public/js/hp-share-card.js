/* "আমার লেখা হাতপাখায়" (2026-11-12): a 1080×1350 card for Facebook/Instagram, drawn on a canvas in the browser so
   Bengali letters are joined correctly. Logo palette only. Nothing leaves the phone until the writer shares it. */
(function () {
    const dlg = document.querySelector('[data-card-dialog]');
    if (!dlg) return;
    const cv = dlg.querySelector('[data-card-canvas]'), ctx = cv.getContext('2d');
    const msg = dlg.querySelector('[data-card-msg]');
    let current = null;
    const W = 1080, H = 1350;
    const logo = new Image();
    logo.src = '/images/app/icon-192.png';

    const wrap = (text, font, maxW, maxLines) => {
        ctx.font = font;
        const words = text.split(/\s+/), lines = [];
        let line = '';
        for (const w of words) {
            const t = line ? line + ' ' + w : w;
            if (ctx.measureText(t).width > maxW && line) { lines.push(line); line = w; } else line = t;
        }
        if (line) lines.push(line);
        if (lines.length > maxLines) { lines.length = maxLines; lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…'; }
        return lines;
    };

    const draw = async (d) => {
        await Promise.all(['800 90px "Anek Bangla"', '700 44px "Anek Bangla"', '400 60px "Galada"'].map((f) => document.fonts?.load(f, 'বাংলা')));
        ctx.fillStyle = '#FFFBF4'; ctx.fillRect(0, 0, W, H);
        // the fan in the corner
        const cx = W + 40, cy = H + 40, r = 560;
        for (let i = 0; i < 26; i++) {
            ctx.beginPath(); ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, Math.PI + i * (Math.PI / 2 / 26), Math.PI + (i + 1) * (Math.PI / 2 / 26));
            ctx.closePath(); ctx.fillStyle = i % 2 ? '#F08D90' : '#CB1B32'; ctx.fill();
        }
        ctx.beginPath(); ctx.arc(cx, cy, 170, 0, Math.PI * 2); ctx.fillStyle = '#EFC08D'; ctx.fill();
        // band
        ctx.fillStyle = '#13212C'; ctx.fillRect(0, 0, W, 190);
        ctx.fillStyle = '#FFFBF4'; ctx.font = '700 58px "Anek Bangla"'; ctx.textBaseline = 'middle';
        ctx.fillText('আমার লেখা হাতপাখায়', 80, 98);
        if (logo.complete && logo.naturalWidth) ctx.drawImage(logo, W - 80 - 120, 35, 120, 120);
        // section, title, writer
        let y = 330;
        if (d.section) {
            ctx.font = '700 40px "Anek Bangla"';
            const sw = ctx.measureText(d.section).width + 56;
            ctx.fillStyle = '#CB1B32'; ctx.beginPath(); ctx.roundRect(80, y - 40, sw, 76, 38); ctx.fill();
            ctx.fillStyle = '#FFFFFF'; ctx.fillText(d.section, 108, y);
            y += 110;
        }
        let size = 104;
        let lines = wrap(d.title, `800 ${size}px "Anek Bangla"`, W - 160, 4);
        while (lines.length > 3 && size > 72) { size -= 8; lines = wrap(d.title, `800 ${size}px "Anek Bangla"`, W - 160, 4); }
        ctx.fillStyle = '#13212C'; ctx.font = `800 ${size}px "Anek Bangla"`; ctx.textBaseline = 'alphabetic';
        lines.forEach((l) => { y += size * 1.25; ctx.fillText(l, 80, y); });
        y += 90;
        ctx.fillStyle = '#4D5A63'; ctx.font = '700 50px "Anek Bangla"';
        ctx.fillText('— ' + d.by, 80, y);
        // footer
        ctx.fillStyle = '#13212C'; ctx.font = '700 44px "Anek Bangla"';
        ctx.fillText('পড়ুন: hatpakha.com', 80, H - 90);
    };

    const blob = () => new Promise((res) => cv.toBlob(res, 'image/png'));
    document.querySelectorAll('[data-share-card]').forEach((b) => b.addEventListener('click', async () => {
        current = b.dataset; msg.textContent = '';
        dlg.showModal();
        await draw(current);
        const share = dlg.querySelector('[data-card-share]');
        try {
            const f = new File([await blob()], 'hatpakha.png', { type: 'image/png' });
            share.hidden = !(navigator.canShare && navigator.canShare({ files: [f] }));
        } catch (e) { share.hidden = true; }
    }));
    dlg.querySelector('[data-card-close]').addEventListener('click', () => dlg.close());
    dlg.querySelector('[data-card-download]').addEventListener('click', async () => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(await blob()); a.download = 'hatpakha-' + (current.title || 'lekha').slice(0, 30) + '.png';
        a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        msg.textContent = 'ছবি নামানো হল।';
    });
    dlg.querySelector('[data-card-copy]').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(current.url); msg.textContent = 'লিংক কপি হয়েছে।'; } catch (e) { msg.textContent = current.url; }
    });
    dlg.querySelector('[data-card-share]').addEventListener('click', async () => {
        try {
            const f = new File([await blob()], 'hatpakha.png', { type: 'image/png' });
            await navigator.share({ files: [f], text: current.title + ' — ' + current.by + '\n' + current.url });
        } catch (e) { /* the writer closed the share sheet */ }
    });
})();
