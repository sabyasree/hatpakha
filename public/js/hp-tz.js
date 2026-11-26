/* D302: festival times in the reader's own time zone. Times are stored once (UTC); the page shows India time until
   this runs, then the zone the browser reports (or the one the reader picked, remembered on this device). */
(() => {
    const boxes = [...document.querySelectorAll('[data-tz-box]')];
    if (!boxes.length || !window.Intl) return;
    const KEY = 'hp-tz';
    const guess = Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Kolkata';
    let zone = guess;
    try { zone = localStorage.getItem(KEY) || guess; } catch (e) {}
    // browsers still report some old names (Chromium: Asia/Calcutta) (UX review)
    const ALIAS = { 'Asia/Calcutta': 'Asia/Kolkata', 'Asia/Dacca': 'Asia/Dhaka', 'Asia/Katmandu': 'Asia/Kathmandu', 'Asia/Rangoon': 'Asia/Yangon' };
    zone = ALIAS[zone] || zone;
    const all = (Intl.supportedValuesOf && Intl.supportedValuesOf('timeZone')) || [];
    // "বৃহস্পতিবার রাত ১২:৫০" reads as Thursday night (really Friday): after midnight says so (UX review)
    const part = (h) => h < 4 ? 'মধ্যরাতের পর' : h < 6 ? 'ভোর' : h < 12 ? 'সকাল' : (h >= 12 && h < 16) ? 'দুপুর' : (h >= 16 && h < 18) ? 'বিকেল' : (h >= 18 && h < 20) ? 'সন্ধ্যা' : 'রাত';
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

    const ymd = (iso, z) => new Intl.DateTimeFormat('en-CA', { timeZone: z, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso));
    const offset = (z) => { try { return (new Intl.DateTimeFormat('en-US', { timeZone: z, timeZoneName: 'shortOffset' }).formatToParts(new Date()).find((p) => p.type === 'timeZoneName') || {}).value || ''; } catch (e) { return ''; } };
    const nice = (z) => `${z.split('/').pop().replace(/_/g, ' ')} · ${z.split('/')[0].replace(/_/g, ' ')} (${offset(z).replace('GMT', 'UTC') || 'UTC'})`;

    const fill = () => boxes.forEach((box) => {
        box.querySelectorAll('time[data-utc]').forEach((t) => { t.textContent = show(t.dataset.utc, zone); });
        // a date abroad that is not Kolkata's date: say so under that festival (UX review)
        box.querySelectorAll('[data-festival]').forEach((f) => {
            const differs = zone !== 'Asia/Kolkata' && [...f.querySelectorAll('time[data-utc]')].some((t) => ymd(t.dataset.utc, zone) !== ymd(t.dataset.utc, 'Asia/Kolkata'));
            const n = f.querySelector('[data-tz-differs]');
            if (n) n.hidden = !differs;
        });
        const sel = box.querySelector('[data-tz]');
        if (sel) {
            if (![...sel.options].some((o) => o.value === zone)) sel.add(new Option(nice(zone), zone), sel.options[0]);
            sel.value = zone;
        }
        box.querySelectorAll('[data-tz-name]').forEach((n) => { n.textContent = (sel && sel.selectedOptions[0] ? sel.selectedOptions[0].text : zone); });
    });

    boxes.forEach((box) => {
        const sel = box.querySelector('[data-tz]');
        if (!sel) return;
        const more = document.createElement('optgroup');
        more.label = 'সব দেশ ও শহর';
        all.filter((z) => ![...sel.options].some((o) => o.value === z)).forEach((z) => more.appendChild(new Option(nice(z), z)));
        if (more.children.length) sel.appendChild(more);
        box.querySelectorAll('[data-tz-js]').forEach((el) => { el.hidden = false; });
        box.querySelectorAll('[data-tz-nojs]').forEach((el) => { el.hidden = true; });
        const find = box.querySelector('[data-tz-find]');
        find?.addEventListener('input', () => {
            // English city or country names only, two letters at least; no match keeps the zone and says so (UX review)
            const q = find.value.trim().toLowerCase();
            const msg = box.querySelector('[data-tz-none]');
            const opts = [...sel.querySelectorAll('option')];
            const hit = q.length < 2 ? opts : opts.filter((o) => o.value.replace(/_/g, ' ').toLowerCase().includes(q));
            opts.forEach((o) => { o.hidden = q.length >= 2 && !hit.includes(o); });
            if (msg) msg.hidden = !(q.length >= 2 && !hit.length);
            if (q.length >= 2 && hit.length && sel.value !== hit[0].value) { sel.value = hit[0].value; sel.dispatchEvent(new Event('change')); }
        });
        sel.addEventListener('change', () => {
            zone = sel.value;
            try { localStorage.setItem(KEY, zone); } catch (e) {}
            fill();
        });
    });
    fill();
})();
