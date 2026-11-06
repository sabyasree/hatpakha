/* Page-turning book view (D68): the text flows into columns as wide as the book; we move one column at a time.
   Arrow keys, swipe and the buttons turn pages; contents links jump; font size can be changed. */
(function () {
    const pages = document.querySelector('[data-book-pages]');
    if (!pages) return;
    const nav = document.querySelector('[data-book-nav]'), tools = document.querySelector('[data-book-tools]'), count = document.querySelector('[data-book-count]');
    const bn = new Intl.NumberFormat('bn-BD');
    let page = 0, total = 1, size = 18;
    pages.classList.add('paged');
    nav.hidden = false; tools.hidden = false;
    const step = () => pages.clientWidth + parseFloat(getComputedStyle(pages).columnGap || 0);
    const layout = () => {
        total = Math.max(1, Math.round(pages.scrollWidth / step()));
        go(Math.min(page, total - 1));
    };
    const go = (n) => {
        page = Math.max(0, Math.min(total - 1, n));
        pages.scrollTo({ left: page * step(), behavior: 'instant' });
        count.textContent = 'পাতা ' + bn.format(page + 1) + ' / ' + bn.format(total);
    };
    document.querySelectorAll('[data-turn]').forEach((b) => b.addEventListener('click', () => go(page + Number(b.dataset.turn))));
    document.querySelectorAll('[data-font]').forEach((b) => b.addEventListener('click', () => {
        size = Math.max(15, Math.min(26, size + Number(b.dataset.font) * 2)); pages.style.fontSize = size + 'px'; layout();
    }));
    document.querySelectorAll('[data-goto]').forEach((a) => a.addEventListener('click', (e) => {
        const t = document.querySelector(a.getAttribute('href'));
        if (!t) return;
        e.preventDefault();
        go(Math.floor((t.offsetLeft - pages.offsetLeft + 2) / step()));
    }));
    document.addEventListener('keydown', (e) => { if (e.key === 'ArrowRight') go(page + 1); if (e.key === 'ArrowLeft') go(page - 1); });
    let x0 = null;
    pages.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
    pages.addEventListener('touchend', (e) => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) go(page + (dx < 0 ? 1 : -1)); x0 = null; });
    window.addEventListener('resize', layout);
    document.fonts?.ready.then(layout);
    layout();
})();
