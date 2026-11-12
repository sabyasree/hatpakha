/* The issue as a magazine (2026-11-12): the text is laid out into real pages (A5 shape), shown as a book whose
   leaves turn in 3D. Two pages side by side on a wide screen, one on a phone. Full screen, font size, contents with
   page numbers, arrow keys, swipe, tap on the page edge. Without JavaScript the issue reads as one long page. */
(function () {
    const root = document.querySelector('[data-mag]');
    const source = document.querySelector('[data-mag-source]');
    if (!root || !source) return;
    const book = root.querySelector('[data-mag-book]');
    const count = root.querySelector('[data-mag-count]');
    const prevBtn = root.querySelector('[data-mag-prev]');
    const nextBtn = root.querySelector('[data-mag-next]');
    const bn = new Intl.NumberFormat('bn-BD');
    const key = 'hp.mag.' + root.dataset.mag;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let pages = [], leaves = [], cur = 0, spread = false, size = 17, busy = false;
    const phone = window.innerWidth < 760;
    size = phone ? 19 : 17;   // UX review: bigger letters on phones
    try { size = Number(localStorage.getItem('hp.mag.size')) || size; } catch (e) { /* private mode */ }

    // ---- 1. Page size: an A5-like page that fits the screen ----------------------------------------------------
    const measure = () => {
        const bar = [...root.querySelectorAll('.mag-bar, .mag-nav, .mag-hint')].reduce((t, el) => t + (el.hidden ? 0 : el.offsetHeight), 0) + 16, availH = window.innerHeight - bar, availW = window.innerWidth - 24;
        let h = Math.min(availH, 1000), w = Math.round(h / 1.41);
        spread = availW >= 2 * w + 40 && window.innerWidth >= 900;
        if (!spread && w > availW) { w = availW; h = Math.min(availH, Math.round(w * 1.75)); }   // phones: use the height
        if (spread && 2 * w > availW) { w = Math.floor(availW / 2); h = Math.round(w * 1.41); }
        return { w, h };
    };

    // ---- 2. Lay the text out into pages ----------------------------------------------------------------------------
    const newPage = (sec, cls) => {
        const p = document.createElement('div');
        p.className = 'mag-page ' + (cls || '');
        if (sec.dataset.head !== 'none') {
            const head = document.createElement('div');
            head.className = 'mag-head';
            head.innerHTML = '<span></span><span></span>';
            head.children[0].textContent = root.dataset.title || '';
            // After a piece's first page: its title and writer, so a reader never gets lost in a long story.
            head.children[1].textContent = (cls || '').includes('mag-next') && sec.dataset.running ? sec.dataset.running : (sec.dataset.section || '');
            p.appendChild(head);
        }
        const body = document.createElement('div');
        body.className = 'mag-body';
        p.appendChild(body);
        if (sec.dataset.head !== 'none') {   // owner: every page carries the name and the ISSN; the number comes later (a space holds its line while measuring)
            const f = document.createElement('div');
            f.className = 'mag-foot';
            f.innerHTML = '<span class="mag-brand">হাতপাখা</span><span class="mag-pn">\u00a0</span><span class="mag-issn">ISSN 2321-3035</span>';
            p.appendChild(f);
        }
        return p;
    };
    const fits = (body) => body.scrollHeight <= body.clientHeight + 1;

    // Split a paragraph that does not fit: as many words (or poem lines) as fit stay, the rest goes on.
    const split = (el, body) => {
        const tokens = [];
        el.childNodes.forEach((n) => {
            if (n.nodeType === 3) n.textContent.split(/(\s+)/).forEach((t) => { if (t) tokens.push(t); });
            else tokens.push(n.cloneNode(true));
        });
        const build = (k) => {
            const c = el.cloneNode(false);
            tokens.slice(0, k).forEach((t) => c.appendChild(typeof t === 'string' ? document.createTextNode(t) : t.cloneNode(true)));
            return c;
        };
        let lo = 0, hi = tokens.length;
        while (lo < hi) {
            const mid = Math.ceil((lo + hi) / 2);
            const c = build(mid);
            body.appendChild(c);
            const ok = fits(body);
            body.removeChild(c);
            if (ok) lo = mid; else hi = mid - 1;
        }
        if (lo === 0) return [null, el];
        const rest = el.cloneNode(false);
        rest.classList.add('mag-cont');
        tokens.slice(lo).forEach((t) => rest.appendChild(typeof t === 'string' ? document.createTextNode(t) : t.cloneNode(true)));
        while (rest.firstChild && rest.firstChild.nodeType === 3 && !rest.firstChild.textContent.trim()) rest.removeChild(rest.firstChild);
        while (rest.firstChild && rest.firstChild.nodeName === 'BR') rest.removeChild(rest.firstChild);
        return [build(lo), rest];
    };

    const paginate = (dim) => {
        book.innerHTML = '';
        const stage = document.createElement('div');
        stage.className = 'mag-stage-measure';
        stage.style.setProperty('--pw', dim.w + 'px');
        stage.style.setProperty('--ph', dim.h + 'px');
        stage.style.fontSize = size + 'px';
        document.body.appendChild(stage);
        const out = [];
        source.querySelectorAll(':scope > [data-kind]').forEach((sec) => {
            const fixed = ['cover', 'inside', 'opener', 'feature', 'back', 'blank'].includes(sec.dataset.kind);
            if (fixed) {
                const p = newPage(sec, 'mag-' + sec.dataset.kind);
                p.querySelector('.mag-body').innerHTML = sec.innerHTML;
                p.dataset.anchor = sec.dataset.anchor || '';
                out.push(p);
                return;
            }
            let p = newPage(sec, 'mag-' + sec.dataset.kind);
            p.dataset.anchor = sec.dataset.anchor || '';
            stage.appendChild(p);
            let body = p.querySelector('.mag-body');
            const queue = [...sec.children].map((c) => c.cloneNode(true));
            // The page designer's rule: a picture that does not fit waits for the top of the next page while the text
            // goes on filling this one (no half-empty pages); at most two wait at a time, in their order.
            const waiting = [];
            const turnPage = () => {
                out.push(p);
                stage.removeChild(p);
                p = newPage(sec, 'mag-' + sec.dataset.kind + ' mag-next');
                stage.appendChild(p);
                body = p.querySelector('.mag-body');
                while (waiting.length) {
                    const f = waiting[0];
                    body.appendChild(f);
                    if (!fits(body) && body.children.length > 1) { body.removeChild(f); break; }
                    waiting.shift();
                }
            };
            while (queue.length || waiting.length) {
                if (!queue.length) {   // only waiting pictures are left
                    const f = waiting.shift();
                    body.appendChild(f);
                    if (fits(body) || body.children.length === 1) continue;
                    body.removeChild(f);
                    waiting.unshift(f);
                    turnPage();
                    continue;
                }
                const el = queue.shift();
                if (waiting.length >= 2 && el.nodeName !== 'FIGURE') { queue.unshift(el); turnPage(); continue; }
                body.appendChild(el);
                if (fits(body)) continue;
                body.removeChild(el);
                if (el.nodeName === 'FIGURE' && body.children.length > 0) { waiting.push(el); continue; }
                const splittable = /^(P|BLOCKQUOTE|LI|DIV)$/.test(el.nodeName) && !el.querySelector('img,table') && el.textContent.trim().length > 0;
                if (splittable) {
                    const [head, rest] = split(el, body);
                    if (head) body.appendChild(head);
                    if (rest && !head && body.children.length === 0) { body.appendChild(rest); continue; }   // a word longer than a page
                    if (rest) queue.unshift(rest);
                } else if (body.children.length === 0) { body.appendChild(el); continue; }
                else queue.unshift(el);
                turnPage();
            }
            out.push(p);
            stage.removeChild(p);
        });
        stage.remove();
        // The back cover is the last left-hand page: pad with a blank page when needed.
        const back = out.findIndex((p) => p.classList.contains('mag-back'));
        if (back > -1 && back % 2 === 0) out.splice(back, 0, (() => { const b = document.createElement('div'); b.className = 'mag-page mag-blank'; b.innerHTML = '<div class="mag-body"><div class="mag-mark"><span class="mag-brand">হাতপাখা</span><span>ISSN 2321-3035</span></div></div>'; return b; })());
        // Page numbers (the cover is not counted) and the contents' numbers.
        out.forEach((p, i) => { const f = p.querySelector('.mag-pn'); if (f) f.textContent = bn.format(i); });
        const at = {};
        out.forEach((p, i) => { if (p.dataset.anchor && !(p.dataset.anchor in at)) at[p.dataset.anchor] = i; });
        out.forEach((p) => p.querySelectorAll('[data-pn]').forEach((s) => { if (s.dataset.pn in at) s.textContent = bn.format(at[s.dataset.pn]); }));
        out.forEach((p) => p.querySelectorAll('[data-goto]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); goPage(at[a.dataset.goto] ?? 0); })));
        return out;
    };

    // ---- 3. The book: leaves with a front and a back that turn in 3D -------------------------------------------
    const build = (dim) => {
        book.style.setProperty('--pw', dim.w + 'px');
        book.style.setProperty('--ph', dim.h + 'px');
        book.style.fontSize = size + 'px';
        book.classList.toggle('mag-spread', spread);
        leaves = [];
        const per = spread ? 2 : 1;
        for (let i = 0; i < pages.length; i += per) {
            const leaf = document.createElement('div');
            leaf.className = 'mag-leaf';
            const front = document.createElement('div'); front.className = 'mag-face mag-front'; front.appendChild(pages[i]);
            const back = document.createElement('div'); back.className = 'mag-face mag-back-face';
            if (spread && pages[i + 1]) back.appendChild(pages[i + 1]);
            leaf.append(front, back);
            leaf.addEventListener('click', (e) => { if (!e.target.closest('a,button')) { leaf.classList.contains('turned') ? prev() : next(); } });
            book.appendChild(leaf);
            leaves.push(leaf);
        }
        place();
    };
    const place = () => {
        leaves.forEach((leaf, i) => {
            const turned = i < cur;
            leaf.classList.toggle('turned', turned);
            leaf.style.zIndex = turned ? String(i + 1) : String(leaves.length - i + 1);
            leaf.classList.toggle('gone', !spread && i < cur - 1);
            // Owner (2026-11-12): a long issue crashed phone browsers. Every leaf is a full-page 3D layer, and hundreds of
            // them do not fit in a phone's graphics memory: only the leaves around the open page are drawn at all.
            leaf.classList.toggle('far', i < cur - 2 || i > cur + 1);
        });
        book.classList.toggle('mag-closed-front', spread && cur === 0);
        book.classList.toggle('mag-closed-back', spread && cur >= leaves.length);
        const first = spread ? Math.max(0, cur * 2 - 1) : cur;
        const shown = spread ? (cur === 0 ? '১' : bn.format(first) + '–' + bn.format(Math.min(pages.length - 1, first + 1))) : bn.format(cur);
        const last = spread ? leaves.length : leaves.length - 1;
        prevBtn.disabled = cur === 0;
        nextBtn.disabled = cur >= last;
        nextBtn.textContent = cur >= last ? 'শেষ পাতা' : 'পরের →';
        count.textContent = cur === 0 ? 'প্রচ্ছদ' : 'পাতা ' + shown + ' / ' + bn.format(pages.length - 1);
        try { localStorage.setItem(key, String(cur)); } catch (e) { /* private mode */ }
    };
    const turn = (to) => {
        to = Math.max(0, Math.min(spread ? leaves.length : leaves.length - 1, to));
        if (to === cur || busy) return;
        busy = true;
        cur = to;
        place();
        setTimeout(() => { busy = false; }, reduce ? 0 : 650);
    };
    const hint = root.querySelector('[data-mag-hint]');
    const hideHint = () => { if (hint && !hint.hidden) { hint.hidden = true; try { localStorage.setItem('hp.mag.hint', '1'); } catch (e) { /* private mode */ } setTimeout(render, 50); } };
    const next = () => { hideHint(); turn(cur + 1); };
    const prev = () => turn(cur - 1);
    const goPage = (n) => turn(spread ? Math.ceil(n / 2) : n);

    const render = () => {
        const dim = measure();
        const at = cur;
        pages = paginate(dim);
        build(dim);
        cur = Math.min(at, spread ? leaves.length : leaves.length - 1);
        place();
    };

    // ---- 4. Controls ----------------------------------------------------------------------------------------------
    nextBtn.addEventListener('click', next);
    prevBtn.addEventListener('click', prev);
    if (hint) {
        try { hint.hidden = localStorage.getItem('hp.mag.hint') === '1'; } catch (e) { hint.hidden = false; }
        root.querySelector('[data-mag-hint-close]').addEventListener('click', hideHint);
    }
    root.querySelector('[data-mag-contents]').addEventListener('click', () => {
        const i = pages.findIndex((p) => p.classList.contains('mag-contents'));
        goPage(Math.max(0, i));
    });
    root.querySelectorAll('[data-mag-font]').forEach((b) => b.addEventListener('click', () => {
        size = Math.max(14, Math.min(24, size + Number(b.dataset.magFont)));
        try { localStorage.setItem('hp.mag.size', String(size)); } catch (e) { /* private mode */ }
        render();
    }));
    const fs = root.querySelector('[data-mag-fullscreen]');
    if (document.fullscreenEnabled) {
        fs.hidden = false;
        fs.addEventListener('click', () => (document.fullscreenElement ? document.exitFullscreen() : root.requestFullscreen()).catch(() => {}));
        document.addEventListener('fullscreenchange', () => { fs.setAttribute('aria-pressed', document.fullscreenElement ? 'true' : 'false'); setTimeout(render, 150); });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight' || e.key === 'PageDown' || e.key === ' ') { e.preventDefault(); next(); }
        if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); prev(); }
    });
    let x0 = null;
    book.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
    book.addEventListener('touchend', (e) => {
        if (x0 === null) return;
        const dx = e.changedTouches[0].clientX - x0;
        if (Math.abs(dx) > 40) (dx < 0 ? next : prev)();
        x0 = null;
    });
    let t = null;
    window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(render, 200); });

    root.classList.add('mag-ready');
    source.hidden = true;
    // Pages are measured in the book's own fonts: load them first (a font not yet used on the screen is not loaded by
    // itself, and measuring with a stand-in font would push lines off the page later); lay out again if one comes late.
    const faces = ['400 17px "Noto Serif Bengali"', '700 17px "Noto Serif Bengali"', '600 17px "Anek Bangla"', '700 17px "Anek Bangla"', '800 17px "Anek Bangla"', '400 17px "Galada"'];
    const fontsReady = document.fonts ? Promise.all(faces.map((f) => document.fonts.load(f, 'অক').catch(() => null))).then(() => document.fonts.ready) : Promise.resolve();
    // A font that arrives late: lay out once more (not for every font, and not while the first layout is pending).
    let laidOut = false;
    if (document.fonts) document.fonts.addEventListener('loadingdone', () => { if (!laidOut) return; clearTimeout(t); t = setTimeout(render, 300); });
    fontsReady.then(() => {
        try { cur = Number(localStorage.getItem(key)) || 0; } catch (e) { cur = 0; }
        render();
        laidOut = true;
    });
})();
