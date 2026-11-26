/* D302: festival times in the reader's own time zone. Times are stored once (UTC); the page shows India time until
   this runs, then the zone the browser reports (or the one the reader picked, remembered on this device). */
(() => {
    const boxes = [...document.querySelectorAll('[data-tz-box]')];
    if (!boxes.length || !window.Intl) return;
    const KEY = 'hp-tz';
    const guess = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Kolkata';
    let zone = guess;
    try { zone = localStorage.getItem(KEY) || guess; } catch (e) {}
    const all = (Intl.supportedValuesOf && Intl.supportedValuesOf('timeZone')) || [];
    const part = (h) => (h >= 4 && h < 12) ? 'সকাল' : (h >= 12 && h < 16) ? 'দুপুর' : (h >= 16 && h < 18) ? 'বিকেল' : (h >= 18 && h < 20) ? 'সন্ধ্যা' : 'রাত';
    const valid = (z) => { try { new Intl.DateTimeFormat('en', { timeZone: z }); return true; } catch (e) { return false; } };
    if (!valid(zone)) zone = 'Asia/Kolkata';

    const show = (iso, z) => {
        const d = new Date(iso);
        const date = new Intl.DateTimeFormat('bn-IN', { timeZone: z, day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' }).formatToParts(d);
        const get = (t) => (date.find((p) => p.type === t) || {}).value || '';
        const h = Number(new Intl.DateTimeFormat('en-GB', { timeZone: z, hour: '2-digit', hourCycle: 'h23' }).format(d));
        const hm = new Intl.DateTimeFormat('bn-IN', { timeZone: z, hour: 'numeric', minute: '2-digit', hour12: true }).formatToParts(d)
            .filter((p) => p.type === 'hour' || p.type === 'minute' || (p.type === 'literal' && p.value.trim() === ':')).map((p) => p.value).join('');
        return `${get('day')} ${get('month')} ${get('year')}, ${get('weekday')}, ${part(h)} ${hm}`;
    };

    const fill = () => boxes.forEach((box) => {
        box.querySelectorAll('time[data-utc]').forEach((t) => { t.textContent = show(t.dataset.utc, zone); });
        const sel = box.querySelector('[data-tz]');
        if (sel) {
            if (![...sel.options].some((o) => o.value === zone)) sel.add(new Option(zone.replace(/_/g, ' '), zone), sel.options[0]);
            sel.value = zone;
        }
        box.querySelectorAll('[data-tz-name]').forEach((n) => { n.textContent = (sel && sel.selectedOptions[0] ? sel.selectedOptions[0].text : zone); });
    });

    boxes.forEach((box) => {
        const sel = box.querySelector('[data-tz]');
        if (!sel) return;
        const more = document.createElement('optgroup');
        more.label = 'সব দেশ ও শহর';
        all.filter((z) => ![...sel.options].some((o) => o.value === z)).forEach((z) => more.appendChild(new Option(z.replace(/_/g, ' '), z)));
        if (more.children.length) sel.appendChild(more);
        sel.addEventListener('change', () => {
            zone = sel.value;
            try { localStorage.setItem(KEY, zone); } catch (e) {}
            fill();
        });
    });
    fill();
})();
