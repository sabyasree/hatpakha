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
    const pr = document.querySelector('[data-cert-print]');
    if (pr) pr.addEventListener('click', () => window.print());
})();
